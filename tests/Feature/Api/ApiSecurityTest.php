<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Instructor\WebinarsController;
use App\Models\Accounting;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Session;
use App\Models\Support;
use App\Models\Verification;
use App\Models\Webinar;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Regression tests for the API review (phase 1). Each test names the finding it covers.
 * Runs against the configured database inside a transaction.
 */
class ApiSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/development';

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function apiHeaders(?User $user = null): array
    {
        $headers = ['x-api-key' => env('API_KEY'), 'Accept' => 'application/json', 'Content-Type' => 'application/json'];

        if ($user) {
            $headers['Authorization'] = 'Bearer ' . auth('api')->tokenById($user->id);
        }

        return $headers;
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::create([
            'role_name' => $role,
            'role_id' => $role == 'teacher' ? 4 : 1,
            'full_name' => 'Api Test ' . $role,
            'email' => 'api.test.' . uniqid() . '@example.com',
            'password' => Hash::make('Secret@12345'),
            'status' => User::$active,
            'access_content' => 1,
            'affiliate' => 0,
            'created_at' => time(),
        ]);
    }

    private function paidWebinar(): Webinar
    {
        $webinar = Webinar::where('status', 'active')->where('price', '>', 0)->where('private', false)->first();
        if (empty($webinar)) {
            $this->markTestSkipped('No paid active course.');
        }

        return $webinar;
    }

    private function giveCourse(User $user, Webinar $webinar): void
    {
        Sale::create([
            'buyer_id' => $user->id,
            'seller_id' => $webinar->creator_id,
            'webinar_id' => $webinar->id,
            'type' => 'webinar',
            'payment_method' => 'credit',
            'amount' => $webinar->price,
            'total_amount' => $webinar->price,
            'created_at' => time(),
        ]);
    }

    private function liveSession(Webinar $webinar): Session
    {
        return Session::create([
            'webinar_id' => $webinar->id,
            'creator_id' => $webinar->teacher_id,
            'date' => time() + 3600,
            'duration' => 60,
            'link' => 'https://meet.example.com/room',
            'session_api' => 'big_blue_button',
            'moderator_secret' => 'moderator-secret',
            'api_secret' => 'attendee-secret',
            'zoom_start_link' => 'https://zoom.example.com/start/host',
            'status' => 'active',
            'created_at' => time(),
        ]);
    }

    /* C1 */
    public function test_test_auth_id_no_longer_impersonates_users()
    {
        $this->getJson(self::API . '/panel/profile-setting?test_auth_id=1', $this->apiHeaders())
            ->assertJson(['success' => false, 'status' => 'unauthorized']);
    }

    /* C2 */
    public function test_browser_join_link_needs_a_valid_signature()
    {
        $webinar = $this->paidWebinar();
        $session = $this->liveSession($webinar);

        $this->get('/api_sessions/' . $session->id . '/big_blue_button?test_auth_id=1')->assertForbidden();
        $this->assertGuest();

        $forged = '/api_sessions/' . $session->id . '/big_blue_button?user=1&expires=' . (time() + 60) . '&signature=abc';
        $this->get($forged)->assertForbidden();
        $this->assertGuest();

        $buyer = $this->makeUser();
        $signed = URL::temporarySignedRoute('big_blue_button', now()->addHours(2), ['session_id' => $session->id, 'user' => $buyer->id]);
        $this->get($signed)->assertRedirect(url('panel/sessions/' . $session->id . '/joinToBigBlueButton'));
        $this->assertAuthenticatedAs($buyer);
    }

    /* C4 */
    public function test_paid_session_needs_login_and_purchase()
    {
        $webinar = $this->paidWebinar();
        $session = $this->liveSession($webinar);

        $this->getJson(self::API . '/panel/sessions/' . $session->id, $this->apiHeaders())
            ->assertStatus(403)->assertJson(['success' => false]);

        $stranger = $this->makeUser();
        $this->getJson(self::API . '/panel/sessions/' . $session->id, $this->apiHeaders($stranger))
            ->assertStatus(403);
    }

    /* C4 + C2: buyers still get the session, without host-only values, and a signed join link */
    public function test_buyer_gets_session_without_host_secrets()
    {
        $webinar = $this->paidWebinar();
        $session = $this->liveSession($webinar);
        $buyer = $this->makeUser();
        $this->giveCourse($buyer, $webinar);

        $data = $this->getJson(self::API . '/panel/sessions/' . $session->id, $this->apiHeaders($buyer))
            ->assertOk()
            ->assertJsonStructure(['data' => ['moderator_secret', 'zoom_start_link', 'api_secret', 'join_link']])
            ->json('data');

        $this->assertNull($data['moderator_secret']);
        $this->assertNull($data['zoom_start_link']);
        $this->assertSame('attendee-secret', $data['api_secret']);
        $this->assertStringContainsString('signature=', $data['join_link']);
        $this->assertStringContainsString('expires=', $data['join_link']);
    }

    /* C4: quizzes of a paid course */
    public function test_paid_quiz_details_need_purchase()
    {
        $quiz = \App\Models\Quiz::whereHas('webinar', function ($query) {
            $query->where('price', '>', 0);
        })->where('status', 'active')->first();

        if (empty($quiz)) {
            $this->markTestSkipped('No quiz on a paid course.');
        }

        $this->getJson(self::API . '/panel/quizzes/' . $quiz->id, $this->apiHeaders())->assertStatus(403);
    }

    /* H1 */
    public function test_auto_login_links_expire()
    {
        $user = $this->makeUser();

        $old = URL::signedRoute('my_api.web.charge', [$user->id]);
        $this->get($old)->assertForbidden();
        $this->assertGuest();

        $expired = URL::temporarySignedRoute('my_api.web.charge', now()->subMinute(), [$user->id]);
        $this->get($expired)->assertForbidden();

        $fresh = URL::temporarySignedRoute('my_api.web.charge', now()->addMinutes(30), [$user->id]);
        $this->get($fresh)->assertRedirect('/panel/financial/account');
        $this->assertAuthenticatedAs($user);
    }

    /* H1: the API hands out expiring links */
    public function test_web_charge_link_from_the_api_expires()
    {
        $user = $this->makeUser();

        $link = $this->postJson(self::API . '/panel/financial/web_charge', [], $this->apiHeaders($user))
            ->assertJson(['success' => true])
            ->json('data.link');

        $this->assertStringContainsString('expires=', $link);
    }

    /* H2 */
    public function test_support_ticket_is_only_visible_to_its_owner()
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $ticket = Support::create([
            'user_id' => $owner->id,
            'department_id' => \App\Models\SupportDepartment::value('id'),
            'title' => 'Private ticket',
            'status' => 'open',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $this->getJson(self::API . '/panel/support/' . $ticket->id, $this->apiHeaders($other))->assertNotFound();

        // The JWT guard caches the first token inside one test, so act as the owner directly.
        $this->app['auth']->forgetGuards();

        $this->actingAs($owner, 'api')->getJson(self::API . '/panel/support/' . $ticket->id, $this->apiHeaders())->assertJson(['success' => true]);
    }

    /* H4 */
    public function test_verification_code_locks_after_five_wrong_tries()
    {
        $email = 'api.verify.' . uniqid() . '@example.com';

        Verification::create([
            'email' => $email,
            'code' => '12345',
            'created_at' => time(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::API . '/verification', ['username' => $email, 'code' => '0000' . $i], $this->apiHeaders());
        }

        // Even the right code is refused while locked.
        $this->postJson(self::API . '/verification', ['username' => $email, 'code' => '12345'], $this->apiHeaders())
            ->assertJson(['success' => false, 'status' => 'too_many_attempts']);
    }

    /* H4 */
    public function test_sign_in_endpoints_have_their_own_rate_limit()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::API . '/login', ['username' => 'nobody@example.com', 'password' => 'wrong-pass'], $this->apiHeaders());
        }

        $this->postJson(self::API . '/login', ['username' => 'nobody@example.com', 'password' => 'wrong-pass'], $this->apiHeaders())
            ->assertStatus(429);

        // Normal browsing is not affected by the sign-in limit.
        $this->getJson(self::API . '/categories', $this->apiHeaders())->assertOk();
    }

    /* H6 */
    public function test_instructor_updates_cannot_touch_protected_columns()
    {
        $teacher = $this->makeUser('teacher');
        $method = new \ReflectionMethod(WebinarsController::class, 'withoutProtectedColumns');
        $method->setAccessible(true);

        $clean = $method->invoke(new WebinarsController(), [
            'title' => 'Allowed',
            'price' => 100,
            'creator_id' => 1,
            'teacher_id' => 1,
            'sales_count_number' => 9999,
            'id' => 5,
        ], $teacher);

        $this->assertSame(['title' => 'Allowed', 'price' => 100], $clean);

        $own = $method->invoke(new WebinarsController(), ['teacher_id' => $teacher->id], $teacher);
        $this->assertSame(['teacher_id' => $teacher->id], $own);
    }

    /* H7 */
    public function test_credit_payment_needs_balance_for_the_total_with_tax()
    {
        $user = $this->makeUser();

        Accounting::create([
            'user_id' => $user->id, 'amount' => 12, 'type' => Accounting::$addiction,
            'type_account' => Accounting::$asset, 'description' => 'test', 'system' => false, 'tax' => false, 'created_at' => time(),
        ]);

        $order = Order::create([
            'user_id' => $user->id, 'status' => Order::$pending, 'amount' => 10, 'tax' => 5,
            'total_discount' => 0, 'total_amount' => 15, 'created_at' => time(),
        ]);

        $this->postJson(self::API . '/panel/payments/credit', ['order_id' => $order->id, 'sale_type' => [1 => 'self']], $this->apiHeaders($user))
            ->assertJson(['success' => false, 'status' => 'not_enough_credit']);

        $this->assertEquals(12, $user->getAccountingCharge());
    }

    /* H7 */
    public function test_credit_payment_rejects_someone_elses_order()
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $order = Order::create([
            'user_id' => $owner->id, 'status' => Order::$pending, 'amount' => 10, 'tax' => 0,
            'total_discount' => 0, 'total_amount' => 10, 'created_at' => time(),
        ]);

        $this->postJson(self::API . '/panel/payments/credit', ['order_id' => $order->id, 'sale_type' => [1 => 'self']], $this->apiHeaders($other))
            ->assertJson(['success' => false]);
    }
}
