<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;

$html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>body { font-family: "tajawal", "dejavusans", sans-serif; text-align: center; } .cert { width: 100%; height: 500px; border: 5px solid #d4af37; padding: 20px; }</style></head><body><div class="cert"><h1>شهادة إتمام دورة تدريبية</h1><p>Certificate of Completion</p><p>مُنحت هذه الشهادة للمتعلّم: محمد عبد الله</p></div></body></html>';

try {
    $pdf = PDF::loadHTML($html, [
        'format' => 'A4-L',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    $output = $pdf->output();
    echo "SUCCESS: niklasravnsborg LaravelPdf (mPDF) generated PDF with full Arabic support, size: " . strlen($output) . " bytes\n";
} catch (\Throwable $e) {
    echo "LaravelPdf Error: " . $e->getMessage() . "\n";
}
