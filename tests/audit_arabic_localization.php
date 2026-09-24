<?php

/**
 * Meem LMS - Arabic Localization Audit Tool
 */

$baseLangPath = __DIR__ . '/../lang';
$enPath = $baseLangPath . '/en';
$arPath = $baseLangPath . '/ar';

function getPhpFilesRecursively($dir, $baseDir = '') {
    $results = [];
    if (!is_dir($dir)) return $results;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $dir . '/' . $item;
        $relPath = ltrim($baseDir . '/' . $item, '/');
        if (is_dir($fullPath)) {
            $results = array_merge($results, getPhpFilesRecursively($fullPath, $relPath));
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            $results[] = $relPath;
        }
    }
    return $results;
}

$enFiles = getPhpFilesRecursively($enPath);
$arFiles = getPhpFilesRecursively($arPath);

echo "Found " . count($enFiles) . " EN language files.\n";
echo "Found " . count($arFiles) . " AR language files.\n";

$missingArFiles = array_diff($enFiles, $arFiles);
$arOnlyFiles = array_diff($arFiles, $enFiles);

if (!empty($missingArFiles)) {
    echo "Missing AR Files:\n";
    foreach ($missingArFiles as $f) echo " - $f\n";
} else {
    echo "No missing AR files.\n";
}

if (!empty($arOnlyFiles)) {
    echo "AR-Only Files:\n";
    foreach ($arOnlyFiles as $f) echo " - $f\n";
}

function flattenArray($array, $prefix = '') {
    $result = [];
    foreach ($array as $key => $value) {
        $fullKey = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $fullKey));
        } else {
            $result[$fullKey] = $value;
        }
    }
    return $result;
}

$totalEnKeys = 0;
$totalArKeys = 0;
$totalMissingArKeys = 0;
$totalEmptyArValues = 0;
$totalCopiedEnValues = 0;
$totalPlaceholderMismatches = 0;

$fileReports = [];

foreach ($enFiles as $relFile) {
    $enFileFull = $enPath . '/' . $relFile;
    $arFileFull = $arPath . '/' . $relFile;

    $enData = is_file($enFileFull) ? (include $enFileFull) : [];
    $arData = is_file($arFileFull) ? (include $arFileFull) : [];

    if (!is_array($enData)) $enData = [];
    if (!is_array($arData)) $arData = [];

    $flatEn = flattenArray($enData);
    $flatAr = flattenArray($arData);

    $totalEnKeys += count($flatEn);
    $totalArKeys += count($flatAr);

    $missingKeys = [];
    $emptyValues = [];
    $copiedEnValues = [];
    $placeholderMismatches = [];

    foreach ($flatEn as $key => $enVal) {
        if (!array_key_exists($key, $flatAr)) {
            $missingKeys[] = $key;
            continue;
        }

        $arVal = $flatAr[$key];

        if ($arVal === '' || $arVal === null) {
            if ($enVal !== '' && $enVal !== null) {
                $emptyValues[] = $key;
            }
        }

        // Check if value is identical to English (excluding numbers, symbols, short codes, URLs, pure placeholders)
        if (is_string($enVal) && is_string($arVal) && trim($enVal) !== '') {
            $trimmedEn = trim($enVal);
            $trimmedAr = trim($arVal);
            if ($trimmedEn === $trimmedAr && strlen($trimmedEn) > 3 && !preg_match('/^https?:\/\//i', $trimmedEn) && !preg_match('/^:[\w_]+$/', $trimmedEn) && !in_array($trimmedEn, ['LTR', 'RTL', 'SAR', 'USD', 'EUR', 'v1', 'v2', 'API', 'SMS', 'URL', 'SEO', 'ID', 'PDF', 'XLS', 'SVG', 'PNG', 'JPG', 'MP4', 'ZIP', 'CSV', 'Zoom', 'Agora', 'Vimeo', 'YouTube', 'AWS', 'Jitsi', 'Bunny', 'Google', 'Facebook', 'Meem LMS'])) {
                // If contains english letters
                if (preg_match('/[a-zA-Z]{3,}/', $trimmedAr)) {
                    $copiedEnValues[] = [
                        'key' => $key,
                        'en' => $trimmedEn,
                        'ar' => $trimmedAr
                    ];
                }
            }

            // Check placeholders
            preg_match_all('/:\b([a-zA-Z0-9_]+)\b/', $trimmedEn, $enMatches);
            preg_match_all('/:\b([a-zA-Z0-9_]+)\b/', $trimmedAr, $arMatches);
            $enPlaceholders = $enMatches[1] ?? [];
            $arPlaceholders = $arMatches[1] ?? [];
            sort($enPlaceholders);
            sort($arPlaceholders);
            if ($enPlaceholders !== $arPlaceholders && !empty($enPlaceholders)) {
                $placeholderMismatches[] = [
                    'key' => $key,
                    'en' => $trimmedEn,
                    'ar' => $trimmedAr,
                    'en_placeholders' => $enPlaceholders,
                    'ar_placeholders' => $arPlaceholders
                ];
            }
        }
    }

    $arOnlyKeys = array_diff(array_keys($flatAr), array_keys($flatEn));

    $totalMissingArKeys += count($missingKeys);
    $totalEmptyArValues += count($emptyValues);
    $totalCopiedEnValues += count($copiedEnValues);
    $totalPlaceholderMismatches += count($placeholderMismatches);

    $fileReports[$relFile] = [
        'en_count' => count($flatEn),
        'ar_count' => count($flatAr),
        'missing_keys' => $missingKeys,
        'empty_values' => $emptyValues,
        'copied_en' => $copiedEnValues,
        'placeholder_mismatches' => $placeholderMismatches,
        'ar_only_keys' => $arOnlyKeys
    ];
}

echo "\n--- SUMMARY AUDIT RESULTS ---\n";
echo "Total English Keys: $totalEnKeys\n";
echo "Total Arabic Keys:  $totalArKeys\n";
echo "Missing Arabic Keys: $totalMissingArKeys\n";
echo "Empty Arabic Values: $totalEmptyArValues\n";
echo "Copied English Values in AR: $totalCopiedEnValues\n";
echo "Placeholder Mismatches: $totalPlaceholderMismatches\n";

file_put_contents(__DIR__ . '/audit_arabic_details.json', json_encode([
    'summary' => [
        'total_en_keys' => $totalEnKeys,
        'total_ar_keys' => $totalArKeys,
        'missing_ar_keys' => $totalMissingArKeys,
        'empty_ar_values' => $totalEmptyArValues,
        'copied_en_values' => $totalCopiedEnValues,
        'placeholder_mismatches' => $totalPlaceholderMismatches,
    ],
    'files' => $fileReports
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Detailed audit report written to tests/audit_arabic_details.json\n";
