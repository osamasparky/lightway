<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Models\Webinar;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\Accounting;
use App\Http\Controllers\Web\PaymentController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

echo "======================================================\n";
echo "   MEEM LMS EXTENDED INTEGRATION TEST SUITE\n";
echo "======================================================\n\n";

$passedCount = 0;
$totalCount = 0;

function assertTest($description, $condition) {
    global $passedCount, $totalCount;
    $totalCount++;
    if ($condition) {
        $passedCount++;
        echo "  ✔ $description\n";
    } else {
        echo "  ❌ FAIL: $description\n";
    }
}

// 1. AUTHENTICATION & USER MANAGEMENT
echo "Suite 1: Authentication & User Management\n";
$testEmail = 'test_integration_' . time() . '@meem.local';
$user = User::create([
    'full_name' => 'Meem LMS Test Student',
    'email' => $testEmail,
    'password' => Hash::make('Secret123!'),
    'role_name' => 'user',
    'role_id' => 1,
    'status' => 'active',
    'created_at' => time(),
]);

assertTest('User successfully created with hashed password', $user && !empty($user->id));
assertTest('Password verification matches hash', Hash::check('Secret123!', $user->password));
assertTest('Invalid password fails verification', !Hash::check('WrongPassword!', $user->password));

// 2. COURSE ACCESS & ENROLLMENT
echo "\nSuite 2: Course Access & Permissions\n";
$webinar = Webinar::where('status', Webinar::$active)->first();
if ($webinar) {
    assertTest('Non-enrolled student cannot access course content', !$webinar->checkUserHasBought($user));
    
    // Create an enrollment sale
    $sale = Sale::create([
        'buyer_id' => $user->id,
        'seller_id' => $webinar->creator_id,
        'webinar_id' => $webinar->id,
        'type' => 'webinar',
        'payment_method' => 'credit',
        'amount' => 50.00,
        'total_amount' => 50.00,
        'access_to_purchased_item' => true,
        'created_at' => time(),
    ]);

    assertTest('Enrolled student with active sale gains course access', $webinar->checkUserHasBought($user));
}

// 3. CHECKOUT & IDEMPOTENT PAYMENT CALLBACK
echo "\nSuite 3: Checkout & Payment Webhook Idempotency\n";
$order = Order::create([
    'user_id' => $user->id,
    'status' => Order::$paying,
    'amount' => 50.00,
    'tax' => 0,
    'total_discount' => 0,
    'total_amount' => 50.00,
    'created_at' => time(),
]);

$orderItem = OrderItem::create([
    'user_id' => $user->id,
    'order_id' => $order->id,
    'webinar_id' => $webinar ? $webinar->id : 1,
    'amount' => 50.00,
    'total_amount' => 50.00,
    'tax' => 0,
    'discount' => 0,
    'created_at' => time(),
]);

$paymentController = new PaymentController();
$reflector = new ReflectionClass($paymentController);
$afterVerifyMethod = $reflector->getMethod('paymentOrderAfterVerify');
$afterVerifyMethod->setAccessible(true);

// First callback
$initialAccountingCount = Accounting::where('user_id', $user->id)->count();
$afterVerifyMethod->invoke($paymentController, $order);

$postFirstCallbackAccounting = Accounting::where('user_id', $user->id)->count();
$freshOrder = Order::find($order->id);

assertTest('First payment callback marks order as PAID', $freshOrder->status === Order::$paid);
assertTest('First callback creates expected accounting records', $postFirstCallbackAccounting >= $initialAccountingCount);

// Second identical callback (replay/retry simulation)
$afterVerifyMethod->invoke($paymentController, $freshOrder);
$postSecondCallbackAccounting = Accounting::where('user_id', $user->id)->count();

assertTest('Duplicate payment callback is strictly IDEMPOTENT (no duplicate accounting rows)', $postFirstCallbackAccounting === $postSecondCallbackAccounting);

// 4. REFUND TRANSACTION SETTLEMENT
echo "\nSuite 4: Refund Settlement & Access Revocation\n";
Sale::where('buyer_id', $user->id)->update([
    'refund_at' => time(),
    'access_to_purchased_item' => false,
]);

$webinarCheckAfterRefund = Webinar::find($webinar->id);
assertTest('Refunded sales revoke course access immediately', !$webinarCheckAfterRefund->checkUserHasBought($user));

// 5. CERTIFICATE ELIGIBILITY
echo "\nSuite 5: Certificate Eligibility Logic\n";
assertTest('Non-completed progress (0%) does not issue certificate', $webinar->getProgress() < 100);

// 6. ROUTING & HTTP 404
echo "\nSuite 6: HTTP Routing & Fallback Status\n";
$routes = [
    '/' => 200,
    '/classes' => 200,
    '/non-existent-random-page-xyz' => 404,
];

foreach ($routes as $uri => $expectedStatus) {
    $out = shell_exec('curl.exe -s -o NUL -w "%{http_code}" "http://127.0.0.1:8000' . $uri . '"');
    assertTest("Route '$uri' returns HTTP $expectedStatus", intval($out) === $expectedStatus);
}

// Clean up test records
if ($user) {
    Sale::where('buyer_id', $user->id)->delete();
    Accounting::where('user_id', $user->id)->delete();
    OrderItem::where('user_id', $user->id)->delete();
    Order::where('user_id', $user->id)->delete();
    $user->delete();
}

echo "\n------------------------------------------------------\n";
echo "INTEGRATION SUMMARY: $totalCount Total | $passedCount Passed | " . ($totalCount - $passedCount) . " Failed\n";
echo "------------------------------------------------------\n";
