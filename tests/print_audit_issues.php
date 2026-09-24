<?php

$data = json_decode(file_get_contents(__DIR__ . '/audit_arabic_details.json'), true);

echo "=== DETAILED BREAKDOWN BY FILE ===\n\n";

foreach ($data['files'] as $file => $details) {
    $hasIssues = !empty($details['missing_keys']) || !empty($details['empty_values']) || !empty($details['copied_en']) || !empty($details['placeholder_mismatches']);
    if ($hasIssues) {
        echo "FILE: $file\n";
        echo "  EN count: {$details['en_count']} | AR count: {$details['ar_count']}\n";
        
        if (!empty($details['missing_keys'])) {
            echo "  Missing Keys (" . count($details['missing_keys']) . "):\n";
            foreach ($details['missing_keys'] as $mk) {
                echo "    - $mk\n";
            }
        }
        
        if (!empty($details['empty_values'])) {
            echo "  Empty Values (" . count($details['empty_values']) . "):\n";
            foreach ($details['empty_values'] as $ev) {
                echo "    - $ev\n";
            }
        }
        
        if (!empty($details['copied_en'])) {
            echo "  Copied English (" . count($details['copied_en']) . "):\n";
            foreach ($details['copied_en'] as $ce) {
                echo "    - [{$ce['key']}] => {$ce['en']}\n";
            }
        }

        if (!empty($details['placeholder_mismatches'])) {
            echo "  Placeholder Mismatches (" . count($details['placeholder_mismatches']) . "):\n";
            foreach ($details['placeholder_mismatches'] as $pm) {
                echo "    - [{$pm['key']}]\n      EN: {$pm['en']}\n      AR: {$pm['ar']}\n";
            }
        }
        echo "\n";
    }
}
