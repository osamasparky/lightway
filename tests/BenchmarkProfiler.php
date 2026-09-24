<?php

$routes = [
    '/' => 'GET',
    '/classes' => 'GET',
    '/blog' => 'GET',
    '/contact' => 'GET',
    '/login' => 'GET',
    '/register' => 'GET',
    '/sitemap.xml' => 'GET',
];

$results = [];

foreach ($routes as $uri => $method) {
    $code = '<?php '
        . '$queries = []; '
        . '$totalQueryTime = 0; '
        . '$startTime = microtime(true); '
        . 'require dirname(__DIR__) . "/vendor/autoload.php"; '
        . '$app = require_once dirname(__DIR__) . "/bootstrap/app.php"; '
        . '$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class); '
        . '$request = Illuminate\\Http\\Request::create("' . $uri . '", "' . $method . '"); '
        . '$app->booted(function() use (&$queries, &$totalQueryTime) {'
        . '    Illuminate\\Support\\Facades\\DB::listen(function($query) use (&$queries, &$totalQueryTime) {'
        . '        $queries[] = $query->sql;'
        . '        $totalQueryTime += $query->time;'
        . '    });'
        . '});'
        . '$response = $kernel->handle($request); '
        . '$endTime = microtime(true); '
        . '$endMem = memory_get_peak_usage(); '
        . '$size = strlen($response->getContent()); '
        . 'echo json_encode(['
        . '  "status" => $response->getStatusCode(),'
        . '  "query_count" => count($queries),'
        . '  "query_time_ms" => round($totalQueryTime, 2),'
        . '  "http_time_ms" => round(($endTime - $startTime) * 1000, 2),'
        . '  "peak_memory_mb" => round($endMem / 1024 / 1024, 2),'
        . '  "response_size_kb" => round($size / 1024, 2),'
        . ']);';

    file_put_contents(__DIR__ . '/runner.php', $code);
    $output = shell_exec('php ' . escapeshellarg(__DIR__ . '/runner.php'));
    $data = json_decode($output, true);
    $results[$uri] = $data ?? ['raw' => $output];
}

if (file_exists(__DIR__ . '/runner.php')) {
    unlink(__DIR__ . '/runner.php');
}

echo json_encode($results, JSON_PRETTY_PRINT);
