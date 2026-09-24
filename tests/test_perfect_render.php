<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

$template = CertificateTemplate::where('status', 'publish')->where('type', 'quiz')->first();
$body = $template->body;

// Clean background image URL to local asset / file
$bgImagePath = public_path('/store/1/default_images/certificate_background.jpg');
if (file_exists($bgImagePath)) {
    $bgBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($bgImagePath));
    $body = preg_replace('/background-image:\s*url\([^\)]+\)/i', 'background-image: url("' . $bgBase64 . '")', $body);
}

// Generate proper QR code img
$url = url("/certificate_validation");
$qrSvg = QrCode::size(120)->generate($url);
$qrSvgClean = preg_replace('/<\?xml[^\>]*\?>/i', '', (string)$qrSvg);
$qrImg = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvgClean) . '" width="120" height="120" />';

$body = str_replace('[student]', 'Cameron Schofield', $body);
$body = str_replace('[student_name]', 'Cameron Schofield', $body);
$body = str_replace('[platform_name]', 'Meem LMS', $body);
$body = str_replace('[course]', 'Become a Product Manager', $body);
$body = str_replace('[course_name]', 'Become a Product Manager', $body);
$body = str_replace('[grade]', '80', $body);
$body = str_replace('[certificate_id]', '3', $body);
$body = str_replace('[date]', '13 Jul 2021 | 17:51', $body);
$body = str_replace('[instructor_name]', 'Instructor Name', $body);
$body = str_replace('[duration]', '40', $body);
$body = str_replace('[instructor_signature]', '', $body);
$body = str_replace('[user_certificate_additional]', '', $body);
$body = str_replace('[qr_code]', $qrImg, $body);

$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificate</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
            size: 930px 600px;
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
            background-color: #fff;
        }
        .certificate-template-container {
            width: 930px;
            height: 600px;
            position: relative;
            margin: 0 auto;
            padding: 0;
            border: none;
            background-repeat: no-repeat;
            background-size: 100% 100%;
            background-position: center center;
            overflow: hidden;
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
        .draggable-element img {
            display: block;
        }
    </style>
</head>
<body>
' . $body . '
</body>
</html>';

try {
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
    Storage::disk('public')->put('perfect_certificate.pdf', $pdfContent);
    echo "SUCCESS: Rendered perfect single-page certificate, size: " . strlen($pdfContent) . " bytes\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
