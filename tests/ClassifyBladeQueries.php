<?php

$data = json_decode(file_get_contents(__DIR__ . '/blade_queries_found.json'), true);

$directDbCalls = [];
$collectionOrRelationCalls = [];

foreach ($data as $item) {
    $code = $item['code'];
    
    // Direct DB calls: DB:: or Model::where/query/find/select/count
    if (preg_match('/\b(DB|[A-Z][a-zA-Z0-9]+)::(table|select|statement|raw|where|find|count|query|get|first|pluck)\b/i', $code, $m)) {
        $directDbCalls[] = $item;
    } elseif (preg_match('/\$[a-zA-Z0-9_]+->(where|first|get|count|exists)\(/', $code)) {
        $collectionOrRelationCalls[] = $item;
    }
}

echo "Direct DB static calls in views: " . count($directDbCalls) . "\n";
echo "Method calls (collection/relation): " . count($collectionOrRelationCalls) . "\n\n";

echo "Top Direct DB Calls:\n";
foreach (array_slice($directDbCalls, 0, 30) as $c) {
    echo "{$c['file']}:{$c['line']} => {$c['code']}\n";
}
