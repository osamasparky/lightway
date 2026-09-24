<?php

namespace Tests\Feature;

use App\Http\Middleware\SessionValidity;
use App\Models\Cart;
use App\Models\Product;
use App\Models\UserLoginHistory;
use App\Models\Verification;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression tests for the purchase cycle:
 * guest adds to cart -> checkout needs login -> login / register (+ verification) -> back to the cart,
 * still signed in, with the guest cart moved into the account.
 *
 * Runs against the configured database inside a transaction (nothing is kept).
 */
class PurchaseAuthRedirectTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Buyer@12345';

    protected function setUp(): void
    {
        parent::setUp();

        // UserLoginHistoryMixin reads $_SERVER['REMOTE_ADDR'] directly (always set on real web requests).
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    }

    private function guestCartCookie(): ?array
    {
        $product = Product::where('status', Product::$active)->first();

        if (empty($product)) {
            return null;
        }

        return [
            'product' => $product,
            'value' => json_encode([
                ['item_id' => $product->id, 'item_name' => 'product_id', 'quantity' => 1],
            ]),
        ];
    }

    private function makeUser(): User
    {
        return User::create([
            'role_name' => 'user',
            'role_id' => 1,
            'full_name' => 'Regression Buyer',
            'email' => 'regression.buyer.' . uniqid() . '@example.com',
            'password' => Hash::make(self::PASSWORD),
            'status' => User::$active,
            'access_content' => 1,
            'affiliate' => 0,
            'created_at' => time(),
        ]);
    }

    public function test_guest_opening_the_cart_is_sent_to_login_and_the_cart_is_remembered()
    {
        $response = $this->get('/cart');

        $response->assertRedirect('/login');
        $this->assertStringEndsWith('/cart', session('url.intended'));
    }

    public function test_login_returns_to_the_cart_and_keeps_the_session()
    {
        $user = $this->makeUser();

        $this->get('/cart');

        $response = $this->post('/login', [
            'type' => 'email',
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(url('/cart'));
        $this->assertAuthenticatedAs($user);

        // SessionValidity logs out sessions without a login-history row.
        $this->assertTrue(UserLoginHistory::where('user_id', $user->id)->exists());
    }

    public function test_login_from_the_header_with_a_guest_cart_goes_to_the_cart()
    {
        $cart = $this->guestCartCookie();
        if (empty($cart)) {
            $this->markTestSkipped('No active product to put in the cart.');
        }

        $user = $this->makeUser();

        $response = $this->withCookie('carts', $cart['value'])->post('/login', [
            'type' => 'email',
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(url('/cart'));
        $this->assertTrue(
            Cart::where('creator_id', $user->id)->whereNotNull('product_order_id')->exists(),
            'The guest cart must be moved into the account after login.'
        );
    }

    public function test_plain_login_without_cart_still_goes_to_the_panel()
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'type' => 'email',
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(url('/panel'));
    }

    public function test_guest_buy_now_is_resumed_after_login()
    {
        $user = $this->makeUser();

        $response = $this->post('/course/direct-payment', ['item_id' => '123', 'item_name' => 'webinar_id']);

        $response->assertRedirect('/login');
        $this->assertSame(['action' => '/course/direct-payment', 'data' => ['item_id' => '123', 'item_name' => 'webinar_id']], session('pending_purchase'));

        $response = $this->post('/login', [
            'type' => 'email',
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(url('/resume-purchase'));

        // The resume page re-posts the original "Buy now" form to the payment action.
        // (The array session used in tests gets a new id per request, so SessionValidity is skipped here.)
        $this->withoutMiddleware(SessionValidity::class)
            ->actingAs($user)
            ->get('/resume-purchase')
            ->assertOk()
            ->assertSee('action="/course/direct-payment"', false)
            ->assertSee('name="item_id" value="123"', false)
            ->assertSee('name="item_name" value="webinar_id"', false);

        $this->assertNull(session('pending_purchase'), 'The pending purchase is used only once.');
    }

    public function test_resume_purchase_only_replays_whitelisted_actions()
    {
        $user = $this->makeUser();
        $this->withoutMiddleware(SessionValidity::class);

        $this->actingAs($user)
            ->withSession(['pending_purchase' => ['action' => '/panel/setting', 'data' => ['x' => 1]]])
            ->get('/resume-purchase')
            ->assertRedirect('/cart');

        $this->actingAs($user)
            ->get('/resume-purchase')
            ->assertRedirect('/cart');
    }

    public function test_register_and_verify_returns_to_the_cart_with_the_guest_cart()
    {
        $cart = $this->guestCartCookie();
        if (empty($cart)) {
            $this->markTestSkipped('No active product to put in the cart.');
        }

        $this->get('/cart');

        $email = 'regression.new.' . uniqid() . '@example.com';

        $response = $this->withCookie('carts', $cart['value'])->post('/register', [
            'account_type' => 'user',
            'email' => $email,
            'full_name' => 'Regression New Buyer',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'term' => '1',
            'timezone' => config('app.timezone'),
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'Registration must create the user.');

        $target = $response->headers->get('Location');

        if (str_ends_with($target, '/verification')) {
            // Verification by code is enabled: confirm it like the user would.
            $code = Verification::where('email', $email)->latest('id')->value('code');

            $response = $this->withCookie('carts', $cart['value'])->post('/verification', [
                'username' => $email,
                'code' => $code,
            ]);
        }

        $response->assertRedirect(url('/cart'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(UserLoginHistory::where('user_id', $user->id)->exists(), 'A login-history row is required or SessionValidity logs the user out.');
        $this->assertTrue(
            Cart::where('creator_id', $user->id)->whereNotNull('product_order_id')->exists(),
            'The guest cart must be moved into the new account.'
        );
    }
}
