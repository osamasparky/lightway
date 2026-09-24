<?php
require __DIR__ . '/../vendor/autoload.php';

function runBenchmark($uri) {
    $app = require __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

    $request = \Illuminate\Http\Request::create($uri, 'GET');

    $start = microtime(true);
    $response = $kernel->handle($request);
    $duration = (microtime(true) - $start) * 1000;

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();
    $mem = memory_get_peak_usage(true) / 1024 / 1024;
    $totalSqlTime = array_sum(array_column($queries, 'time'));

    printf("%-15s | Status: %d | Time: %6.1f ms | SQL: %6.1f ms | Queries: %3d | RAM: %4.1f MB\n",
        $uri, $response->getStatusCode(), $duration, $totalSqlTime, count($queries), $mem
    );
    $kernel->terminate($request, $response);
}

echo "========================================================================\n";
echo "                      LIVE RUNTIME BENCHMARK\n";
echo "========================================================================\n";
runBenchmark('/');
runBenchmark('/classes');
runBenchmark('/blog');
runBenchmark('/login');
runBenchmark('/register');
runBenchmark('/contact');
echo "========================================================================\n";
