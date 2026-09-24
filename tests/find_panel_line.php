<?php

$lines = file(__DIR__ . '/../lang/ar/panel.php');
foreach ($lines as $num => $line) {
    if (strpos($line, 'dashboard') !== false || strpos($line, 'لوحة القيادة') !== false) {
        echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
    }
}
