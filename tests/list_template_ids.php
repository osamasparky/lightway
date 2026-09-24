<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$templates = App\Models\CertificateTemplate::all();
foreach ($templates as $t) {
    echo "ID: {$t->id} | Type: {$t->type} | Title: {$t->title}\n";
}
