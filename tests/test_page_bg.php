<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

$bgImagePath = public_path('/store/1/default_images/certificate_background.jpg');
$bgBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($bgImagePath));

$stampPath = public_path('/store/1/default_images/stamp.png');
$stampBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($stampPath));

$sigPath = public_path('/store/1/default_images/signature.png');
$sigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($sigPath));

$url = url("/certificate_validation");
$qrSvg = QrCode::size(120)->generate($url);
$qrSvgClean = preg_replace('/<\?xml[^\>]*\?>/i', '', (string)$qrSvg);
$qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvgClean);

$html = '<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificate</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
            size: 930px 600px;
            background-image: url("' . $bgBase64 . '");
            background-image-resize: 6;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 930px;
            height: 600px;
            overflow: hidden;
            font-family: "DejaVu Sans", sans-serif;
            direction: ltr !important;
        }
        .certificate-template-container {
            width: 930px;
            height: 600px;
            position: relative;
            margin: 0;
            padding: 0;
            border: none;
            overflow: hidden;
            direction: ltr !important;
            text-align: left !important;
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        .draggable-element {
            position: absolute !important;
            display: inline-block;
            white-space: pre-wrap;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }
        .draggable-element.rtl-text {
            direction: rtl;
            unicode-bidi: embed;
        }
        .draggable-element img {
            display: block;
        }
    </style>
</head>
<body>
    <div class="certificate-template-container">
        <div class="draggable-element rtl-text" style="top: 220px; left: 165px; width: 600px; font-size: 24px; color: #000; text-align: center; font-weight: bold;">شهادة إتمام دورة تدريبية</div>
        <div class="draggable-element rtl-text" style="top: 260px; left: 165px; width: 600px; font-size: 22px; color: #000; text-align: center; font-weight: bold;">تُمنح إلى: Morgan Sullivan</div>
        <div class="draggable-element rtl-text" style="top: 295px; left: 165px; width: 600px; font-size: 18px; color: #000; text-align: center;">لاجتيازه بنجاح: التلاوة الموجهة وتطبيق التجويد</div>
        <div class="draggable-element rtl-text" style="top: 330px; left: 165px; width: 600px; font-size: 16px; color: #000; text-align: center;">بتقدير 90 بنجاح وتفوق</div>
        
        <div class="draggable-element rtl-text" style="top: 80px; left: 57px; font-size: 14px; color: #000; font-weight: bold;">14 يوليو 2021 | 00:46</div>
        <div class="draggable-element rtl-text" style="top: 48px; left: 56px; font-size: 16px; color: #000; font-weight: bold;">Meem LMS</div>
        <div class="draggable-element rtl-text" style="top: 49px; left: 740px; font-size: 14px; color: #000; font-weight: bold;">رقم الشهادة : 5</div>
        
        <div class="draggable-element" style="top: 400px; left: 100px; width: 128px; height: 128px;">
            <img src="' . $stampBase64 . '" style="max-height: 100%; max-width: 100%;" />
        </div>
        
        <div class="draggable-element" style="top: 400px; left: 241px; width: 128px; height: 128px;">
            <img src="' . $sigBase64 . '" style="max-height: 100%; max-width: 100%;" />
        </div>
        
        <div class="draggable-element" style="top: 392px; left: 728px; width: 128px; height: 128px;">
            <img src="' . $qrBase64 . '" width="128" height="128" />
        </div>
    </div>
</body>
</html>';

$pdf = PDF::loadHTML($html, [
    'format' => [930 * 0.264583, 600 * 0.264583],
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
]);

$pdfContent = $pdf->output();
Storage::disk('public')->put('test_page_bg.pdf', $pdfContent);
echo "SUCCESS: Rendered with page background! File size: " . strlen($pdfContent) . " bytes\n";
