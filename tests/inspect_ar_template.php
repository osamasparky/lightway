<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Translation\CertificateTemplateTranslation;

$tr = CertificateTemplateTranslation::where('certificate_template_id', 7)->where('locale', 'ar')->first();
if ($tr) {
    echo "TITLE: " . $tr->title . "\n\n";
    echo "BODY:\n" . $tr->body . "\n\n";
    echo "ELEMENTS:\n" . $tr->elements . "\n";
} else {
    echo "No Arabic translation found for template 7.\n";
}
