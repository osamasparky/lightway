<?php

$dir = dirname(__DIR__) . '/resources/views';
$patterns = [
    '/\b(Webinar|Bundle|User|Category|Product|Setting|Sale|SpecialOffer|Ticket|Comment|Reward|Tag|Faq|Section)::[a-zA-Z0-9_]+\(/',
    '/\bDB::[a-zA-Z0-9_]+\(/',
    '/->where\(/',
    '/->first\(/',
    '/->get\(/',
    '/->count\(/',
    '/->exists\(/',
    '/->with\(/',
];

$results = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($it as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }
    
    $path = $file->getPathname();
    $relative = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    $lines = file($path);

    foreach ($lines as $lineNum => $content) {
        foreach ($patterns as $pat) {
            if (preg_match($pat, $content, $matches)) {
                $results[] = [
                    'file' => $relative,
                    'line' => $lineNum + 1,
                    'matched' => trim($matches[0]),
                    'code' => trim($content),
                ];
            }
        }
    }
}

file_put_contents(__DIR__ . '/blade_queries_found.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Found " . count($results) . " potential queries/patterns in views.\n";
