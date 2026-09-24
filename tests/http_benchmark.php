<?php

$urls = [
    '/' => 'Home Page',
    '/classes' => 'Courses Listing',
    '/admin/login' => 'Admin Login Page',
    '/login' => 'User Login Page',
];

echo "=== HTTP BENCHMARK ON http://127.0.0.1:8000 ===\n\n";

foreach ($urls as $uri => $label) {
    $url = "http://127.0.0.1:8000" . $uri;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $t0 = microtime(true);
    $content = curl_exec($ch);
    $t1 = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000;
    $totalTime = ($t1 - $t0) * 1000;
    $sizeKb = round(strlen($content) / 1024, 1);
    
    echo sprintf("[%-25s] %s | Status: %d | Time: %7.2f ms (Connect: %.1f ms) | Size: %s KB\n",
        $label, $uri, $httpCode, $totalTime, $connectTime, $sizeKb);
    
    curl_close($ch);
}
