<?php

$arPath = __DIR__ . '/../lang/ar';

$patterns = [
    'Egyptian / Colloquial' => ['/(\bعلشان\b|\bعشان\b|\bبتاع\b|\bازاي\b|\bدلوقتي\b|\bكده\b|\bعايز\b|\bبرجاء\b)/u'],
    'Awkward Literal Translation' => [
        '/بالطبع/' => 'Check if "course" was translated as "بالطبع"',
        '/لوحة القيادة/' => 'Check if "dashboard" was translated as "لوحة القيادة"',
        '/بطل المنزل/' => 'Check if "home hero" was translated literally',
        '/تصبح مدرس/' => 'Check if "become instructor" was translated literally',
        '/تصبح مدرب/' => 'Check if "become instructor" was translated literally',
        '/حصة/' => 'Check if webinar/lesson was called حصة instead of دورة تدريبية / جلسة',
    ],
];

function scanFiles($dir) {
    $files = [];
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . '/' . $item;
        if (is_dir($full)) {
            $files = array_merge($files, scanFiles($full));
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            $files[] = $full;
        }
    }
    return $files;
}

$arFiles = scanFiles($arPath);
$findings = [];

foreach ($arFiles as $file) {
    $rel = str_replace($arPath . '/', '', $file);
    $data = include $file;
    if (!is_array($data)) continue;

    foreach ($data as $key => $val) {
        if (!is_string($val)) continue;
        
        if (preg_match('/(\bعلشان\b|\bعشان\b|\bبتاع\b|\bازاي\b|\bدلوقتي\b|\bكده\b|\bعايز\b|\bبرجاء\b)/u', $val, $m)) {
            $findings[] = [
                'file' => $rel,
                'key' => $key,
                'match' => $m[0],
                'val' => $val,
                'type' => 'Colloquialism'
            ];
        }

        if (preg_match('/(لوحة القيادة|بطل المنزل|بالطبع|أقسام المنزل)/u', $val, $m)) {
            $findings[] = [
                'file' => $rel,
                'key' => $key,
                'match' => $m[0],
                'val' => $val,
                'type' => 'Literal translation'
            ];
        }
    }
}

echo "Total quality flags found: " . count($findings) . "\n\n";
foreach ($findings as $f) {
    echo "[$f[type]] in $f[file] ($f[key]): Found '{$f['match']}'\n  Text: {$f['val']}\n\n";
}
