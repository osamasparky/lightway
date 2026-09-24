<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

echo "=== MEEM LMS PERFORMANCE PROFILER ===\n\n";

// 1. Test Database Query Latency
$t0 = microtime(true);
DB::select('SELECT 1');
$t1 = microtime(true);
echo sprintf("DB Ping Latency: %.2f ms\n", ($t1 - $t0) * 1000);

// 2. Profile Key Pages
$urls = [
    '/' => 'Home Page',
    '/classes' => 'Courses Listing',
    '/admin/login' => 'Admin Login Page',
    '/admin' => 'Admin Dashboard (Redirect/Load)',
    '/admin/certificates/templates' => 'Certificate Templates List',
    '/admin/certificates/templates/8/edit?locale=ar' => 'Certificate Template 8 Edit'
];

foreach ($urls as $url => $label) {
    DB::enableQueryLog();
    $t0 = microtime(true);
    
    $req = Request::create($url, 'GET');
    // Set admin user if admin route
    if (str_starts_with($url, '/admin') && !str_contains($url, 'login')) {
        $admin = App\User::where('role_name', 'admin')->first();
        if ($admin) {
            auth()->login($admin);
        }
    }
    
    $res = $kernel->handle($req);
    $t1 = microtime(true);
    
    $queries = DB::getQueryLog();
    $queryCount = count($queries);
    $totalQueryTime = array_sum(array_column($queries, 'time'));
    $totalTimeMs = ($t1 - $t0) * 1000;
    
    echo sprintf("[%-35s] Total: %7.2f ms | DB Queries: %3d (%6.2f ms) | HTTP: %d\n", 
        $label, $totalTimeMs, $queryCount, $totalQueryTime, $res->getStatusCode());
    
    DB::flushQueryLog();
}

echo "\n--- System Configurations ---\n";
echo "App Debug: " . (config('app.debug') ? 'TRUE (Active)' : 'FALSE') . "\n";
echo "App Environment: " . config('app.env') . "\n";
echo "Cache Driver: " . config('cache.default') . "\n";
echo "Session Driver: " . config('session.driver') . "\n";
echo "Database Host: " . config('database.connections.mysql.host') . "\n";
