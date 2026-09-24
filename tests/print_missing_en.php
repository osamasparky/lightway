<?php

$baseLangPath = __DIR__ . '/../lang';
$enPath = $baseLangPath . '/en';
$arPath = $baseLangPath . '/ar';

$data = json_decode(file_get_contents(__DIR__ . '/audit_arabic_details.json'), true);

foreach ($data['files'] as $file => $details) {
    if (!empty($details['missing_keys'])) {
        echo "=== $file ===\n";
        $enData = include $enPath . '/' . $file;
        foreach ($details['missing_keys'] as $mk) {
            echo "KEY: $mk\n";
            echo "EN : " . ($enData[$mk] ?? '(NOT FOUND)') . "\n\n";
        }
    }
}
