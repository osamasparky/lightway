<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- DATABASE INDEX EXPLAIN VERIFICATION ---\n\n";

$queries = [
    'Webinars Active & Public' => "EXPLAIN SELECT * FROM webinars WHERE status = 'active' AND private = 0 ORDER BY updated_at DESC LIMIT 6",
    'Webinars Category Filter' => "EXPLAIN SELECT * FROM webinars WHERE status = 'active' AND category_id = 1 ORDER BY updated_at DESC LIMIT 6",
    'Sales Buyer Access' => "EXPLAIN SELECT * FROM sales WHERE buyer_id = 1 AND access_to_purchased_item = 1 AND refund_at IS NULL",
    'Product Badge Contents' => "EXPLAIN SELECT * FROM product_badge_contents WHERE targetable_type = 'App\\\\Models\\\\Webinar' AND targetable_id = 1",
    'Special Offers Active' => "EXPLAIN SELECT * FROM special_offers WHERE webinar_id = 1 AND status = 'active' AND from_date < " . time() . " AND to_date > " . time(),
];

foreach ($queries as $label => $sql) {
    echo "Query: $label\n";
    $result = DB::select($sql);
    foreach ($result as $row) {
        $rowArr = (array) $row;
        echo "  Table: {$rowArr['table']} | Key Selected: " . ($rowArr['key'] ?? 'NONE') . " | Type: {$rowArr['type']} | Rows Examined: {$rowArr['rows']} | Extra: {$rowArr['Extra']}\n";
    }
    echo "\n";
}
