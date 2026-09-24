<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

DB::enableQueryLog();
$t0 = microtime(true);

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Request::create('/', 'GET');
$response = $httpKernel->handle($request);

$t1 = microtime(true);
$queries = DB::getQueryLog();

echo "Page: /\n";
echo "Total Execution Time: " . round(($t1 - $t0) * 1000, 2) . " ms\n";
echo "Total Queries Count: " . count($queries) . "\n";
echo "Total Query Time: " . round(array_sum(array_column($queries, 'time')), 2) . " ms\n\n";

echo "Top 10 Slowest Queries:\n";
usort($queries, fn($a, $b) => $b['time'] <=> $a['time']);
foreach (array_slice($queries, 0, 10) as $i => $q) {
    echo sprintf("#%d [%.2f ms] %s\n", $i + 1, $q['time'], substr($q['query'], 0, 120));
}

$queryStrings = array_column($queries, 'query');
$counts = array_count_values($queryStrings);
arsort($counts);
echo "\nTop Repeated Queries:\n";
foreach (array_slice($counts, 0, 10, true) as $sql => $c) {
    if ($c > 1) {
        echo "Repeated {$c} times: " . substr($sql, 0, 100) . "...\n";
    }
}
