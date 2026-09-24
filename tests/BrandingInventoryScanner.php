<?php

ini_set('memory_limit', '512M');

$dir = dirname(__DIR__);
$excludeDirs = [
    'vendor',
    'node_modules',
    '.git',
    'storage',
    'public/store',
    'public/vendor',
];

$searchTerms = [
    'rocket lms',
    'rocketlms',
    'rocket-lms',
    'lightway',
];

$inventory = [];

$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        function ($file, $key, $iterator) use ($excludeDirs, $dir) {
            $path = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $path = str_replace('\\', '/', $path);
            foreach ($excludeDirs as $exc) {
                if ($path === $exc || str_starts_with($path, $exc . '/')) {
                    return false;
                }
            }
            return true;
        }
    )
);

foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $rel = str_replace($dir . DIRECTORY_SEPARATOR, '', $path);
    $rel = str_replace('\\', '/', $rel);

    // Skip binary files and large bundles
    $ext = strtolower($file->getExtension());
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'zip', 'gz', 'tar', 'sql', 'map', 'min.js', 'min.css'])) {
        continue;
    }

    if ($file->getSize() > 2 * 1024 * 1024) {
        continue; // Skip files > 2MB
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) continue;

    foreach ($lines as $lineNum => $line) {
        $lower = strtolower($line);
        foreach ($searchTerms as $term) {
            if (str_contains($lower, $term)) {
                $inventory[] = [
                    'file' => $rel,
                    'line' => $lineNum + 1,
                    'term' => $term,
                    'content' => trim($line),
                ];
                break;
            }
        }
    }
}

file_put_contents(__DIR__ . '/branding_inventory.json', json_encode($inventory, JSON_PRETTY_PRINT));
echo "Found " . count($inventory) . " branding occurrences across non-vendor files.\n";

$byFile = [];
foreach ($inventory as $item) {
    $byFile[$item['file']] = ($byFile[$item['file']] ?? 0) + 1;
}

echo "Files containing branding references: " . count($byFile) . "\n\n";
foreach ($byFile as $f => $cnt) {
    echo " - $f ($cnt occurrences)\n";
}
