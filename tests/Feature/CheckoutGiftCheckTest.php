<?php

namespace Tests\Feature;

use App\Http\Middleware\SessionValidity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TempUser;
use App\Models\Webinar;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The one-stop checkout validates the recipient choices on /payments/checkout-check
 * before the real payment request. It must never keep anything in the database.
 */
class CheckoutGiftCheckTest extends TestCase
{
    use DatabaseTransactions;

    private User $buyer;
    private Order $order;
    private OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $webinar = Webinar::where('status', 'active')->where('price', '>', 0)->first();
        if (empty($webinar)) {
            $this->markTestSkipped('No paid active course.');
        }

        $this->buyer = User::create([
            'role_name' => 'user',
            'role_id' => 1,
            'full_name' => 'Checkout Buyer',
            'email' => 'checkout.buyer.' . uniqid() . '@example.com',
            'password' => Hash::make('Buyer@12345'),
            'status' => User::$active,
            'access_content' => 1,
            'affiliate' => 0,
            'created_at' => time(),
        ]);

        $this->order = Order::create([
            'user_id' => $this->buyer->id,
            'status' => Order::$pending,
            'amount' => $webinar->price,
            'tax' => 0,
            'total_discount' => 0,
            'total_amount' => $webinar->price,
            'created_at' => time(),
        ]);

        $this->item = OrderItem::create([
            'user_id' => $this->buyer->id,
            'order_id' => $this->order->id,
            'webinar_id' => $webinar->id,
            'amount' => $webinar->price,
            'total_amount' => $webinar->price,
            'created_at' => time(),
        ]);

        $this->withoutMiddleware(SessionValidity::class);
        $this->actingAs($this->buyer);
    }

    private function check(array $saleType, array $giftUser = [])
    {
        return $this->postJson('/payments/checkout-check', [
            'order_id' => $this->order->id,
            'gateway' => 'credit',
            'sale_type' => $saleType,
            'gift_user' => $giftUser,
        ]);
    }

    public function test_buying_for_myself_passes()
    {
        $this->check([$this->item->id => 'self'])->assertOk();
    }

    public function test_gift_needs_recipient_details()
    {
        $this->check([$this->item->id => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(["gift_user.{$this->item->id}.email", "gift_user.{$this->item->id}.full_name"]);
    }

    public function test_gift_to_my_own_email_is_rejected()
    {
        $this->check([$this->item->id => 'other'], [
            $this->item->id => ['full_name' => 'Me', 'email' => $this->buyer->email, 'password' => 'secret12'],
        ])->assertStatus(422)->assertJsonPath('errors', ["gift_user.{$this->item->id}.email" => trans('home.lw_co_err_self_email')]);
    }

    public function test_valid_gift_passes_and_keeps_nothing()
    {
        $before = TempUser::count();

        $this->check([$this->item->id => 'other'], [
            $this->item->id => ['full_name' => 'Friend', 'email' => 'friend.' . uniqid() . '@example.com', 'password' => 'secret12'],
        ])->assertOk();

        $this->assertSame($before, TempUser::count(), 'The dry run must roll back the recipient row.');
    }

    public function test_someone_elses_order_is_rejected()
    {
        $other = User::where('id', '!=', $this->buyer->id)->first();
        $this->actingAs($other);

        $this->check([$this->item->id => 'self'])->assertStatus(422);
    }
}
