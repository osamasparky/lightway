<?php

$base = __DIR__ . '/../lang';
$files = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

$errors = [];
foreach ($files as $file) {
    try {
        $res = include $file;
        if (!is_array($res)) {
            $errors[] = "$file does not return an array";
        }
    } catch (\Throwable $e) {
        $errors[] = "$file error: " . $e->getMessage();
    }
}

if (empty($errors)) {
    echo "SUCCESS: All " . count($files) . " language files across all locales loaded successfully without errors.\n";
} else {
    echo "ERRORS FOUND:\n" . implode("\n", $errors) . "\n";
}
