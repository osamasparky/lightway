<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;

$templates = CertificateTemplate::with('translations')->get();
foreach ($templates as $t) {
    echo "=== Template ID: {$t->id} | Type: {$t->type} | Status: {$t->status} | Image: {$t->image} ===\n";
    echo "Main Body (First 500 chars):\n" . substr($t->body, 0, 500) . "\n\n";
    foreach ($t->translations as $tr) {
        echo "--- Translation Locale: {$tr->locale} | Title: {$tr->title} ---\n";
        echo "Elements JSON:\n" . $tr->elements . "\n";
        echo "Body HTML:\n" . $tr->body . "\n";
    }
}
