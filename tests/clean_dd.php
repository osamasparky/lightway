<?php

$dir = dirname(__DIR__) . '/app/PaymentChannels/Drivers';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$modifiedFiles = [];

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);

    // Look for active dd( occurrences (not already commented)
    if (preg_match('/(?<!\/\/)\bdd\s*\(/', $content)) {
        // Ensure Log facade is imported if not already
        if (!str_contains($content, 'use Illuminate\Support\Facades\Log;')) {
            $content = preg_replace('/namespace\s+([^;]+);/', "namespace $1;\n\nuse Illuminate\Support\Facades\Log;", $content, 1);
        }

        // Replace dd($e->getMessage()) or dd($exception) or dd($response) or dd(...)
        $content = preg_replace_callback('/(?<!\/\/)\bdd\s*\(\s*([^;]+)\s*\);/', function ($matches) use ($path) {
            $arg = trim($matches[1]);
            $gateway = basename(dirname($path));
            return "Log::error('Payment gateway error [{$gateway}]', ['error' => is_object({$arg}) && method_exists({$arg}, 'getMessage') ? {$arg}->getMessage() : {$arg}]); throw new \Exception('Payment processing failed');";
        }, $content);

        file_put_contents($path, $content);
        $modifiedFiles[] = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
    }
}

// Also check app/Sessions/Zoom.php
$zoomPath = dirname(__DIR__) . '/app/Sessions/Zoom.php';
if (file_exists($zoomPath)) {
    $zoomContent = file_get_contents($zoomPath);
    if (preg_match('/(?<!\/\/)\bdd\s*\(/', $zoomContent)) {
        if (!str_contains($zoomContent, 'use Illuminate\Support\Facades\Log;')) {
            $zoomContent = preg_replace('/namespace\s+([^;]+);/', "namespace $1;\n\nuse Illuminate\Support\Facades\Log;", $zoomContent, 1);
        }
        $zoomContent = preg_replace('/(?<!\/\/)\bdd\s*\(\s*\$e\s*\);/', "Log::error('Zoom API error', ['error' => \$e->getMessage()]); return false;", $zoomContent);
        file_put_contents($zoomPath, $zoomContent);
        $modifiedFiles[] = 'app/Sessions/Zoom.php';
    }
}

echo "Cleaned dd() from " . count($modifiedFiles) . " files:\n";
foreach ($modifiedFiles as $f) {
    echo " - $f\n";
}
