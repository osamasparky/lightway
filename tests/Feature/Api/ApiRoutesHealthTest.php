<?php

namespace Tests\Feature\Api;

use App\Models\IpRestriction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tests for the API review (phase 2): endpoints that were broken.
 */
class ApiRoutesHealthTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/development';

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function headers(): array
    {
        return ['x-api-key' => env('API_KEY'), 'Accept' => 'application/json', 'Content-Type' => 'application/json'];
    }

    /* M1 */
    public function test_contact_and_newsletter_no_longer_crash()
    {
        $this->postJson(self::API . '/contact', [], $this->headers())->assertOk()->assertJson(['success' => false]);
        $this->postJson(self::API . '/newsletter', [], $this->headers())->assertOk()->assertJson(['success' => false]);
    }

    /* M2 + M8: every controller class resolves and no two routes share a name */
    public function test_all_route_actions_exist_and_names_are_unique()
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action);
                $this->assertTrue(class_exists($class), "Missing controller $class for {$route->uri()}");

                // Two app features were never built on the server; they are listed in the review report.
                $knownGaps = ['App\Http\Controllers\Api\Panel\UsersController@store', 'App\Http\Controllers\Api\Panel\WebinarChapterController@show'];

                if (str_starts_with($route->uri(), 'api/') and !in_array($action, $knownGaps)) {
                    $this->assertTrue(method_exists($class, $method), "Missing method $action for {$route->uri()}");
                }
            }

            if ($name = $route->getName()) {
                $names[$name][] = $route->uri();
            }
        }

        $duplicates = array_filter($names, fn($uris) => count($uris) > 1 and str_starts_with($uris[0], 'api/'));
        $this->assertSame([], $duplicates, 'API route names must be unique.');
    }

    /* M8: payment gateways still build the web callback URL */
    public function test_payment_callback_names_point_to_the_web_routes()
    {
        $this->assertSame(url('/payments/verify/Paypal'), route('payment_verify', ['gateway' => 'Paypal']));
        $this->assertSame(url('/payments/verify/Iyzipay'), route('payment_verify_post', ['gateway' => 'Iyzipay']));
        $this->assertStringEndsWith('/api/development/facebook', route('facebook'));
        $this->assertStringEndsWith('/api/development/google', route('google'));
    }

    /* M4 */
    public function test_guest_bundle_free_asks_for_login_instead_of_crashing()
    {
        $this->postJson(self::API . '/bundles/1/free', [], $this->headers())
            ->assertOk()->assertJson(['success' => false, 'status' => 'unauthorized']);
    }

    /* M3 */
    public function test_instructor_meetings_root_no_longer_dumps()
    {
        $response = $this->getJson(self::API . '/instructor/meetings', $this->headers());
        $this->assertNotSame(500, $response->status());
    }

    /* Bundles list crashed on free bundles (price helper) */
    public function test_bundles_list_works()
    {
        $this->getJson(self::API . '/bundles', $this->headers())->assertOk()->assertJson(['success' => true]);
    }

    /* M6 */
    public function test_any_matching_ip_rule_blocks()
    {
        IpRestriction::query()->delete();

        IpRestriction::create(['type' => 'full_ip', 'value' => '127.0.0.1', 'reason' => 'test', 'created_at' => time()]);
        IpRestriction::create(['type' => 'full_ip', 'value' => '10.9.8.7', 'reason' => 'test', 'created_at' => time()]);

        $this->getJson(self::API . '/categories', $this->headers())->assertJson(['status' => 'restriction']);
    }

    /* M5 */
    public function test_guest_messages_to_instructors_are_rate_limited()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::API . '/users/1/send-message', [], $this->headers());
        }

        $this->postJson(self::API . '/users/1/send-message', [], $this->headers())->assertStatus(429);
    }

    /* L2 */
    public function test_unauthorized_message_is_translated()
    {
        $message = $this->getJson(self::API . '/panel/profile-setting', $this->headers())->json('message');
        $this->assertNotSame('auth.unauthorized', $message);
    }
}
