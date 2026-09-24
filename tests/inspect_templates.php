<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use App\Models\Translation\CertificateTemplateTranslation;

$templates = CertificateTemplate::all();
foreach ($templates as $t) {
    echo "========================================\n";
    echo "ID: {$t->id} | Type: {$t->type} | Title: {$t->title} | Image: {$t->image}\n";
    echo "Base Body:\n" . $t->body . "\n\n";
    
    $translations = CertificateTemplateTranslation::where('certificate_template_id', $t->id)->get();
    foreach ($translations as $tr) {
        echo "  [Locale: {$tr->locale}] Title: {$tr->title}\n";
        echo "  Body:\n" . $tr->body . "\n\n";
    }
}
