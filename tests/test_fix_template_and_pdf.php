<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use App\Models\Translation\CertificateTemplateTranslation;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

// Let's create clean Arabic body for Template 7
$arBody = '<div class="certificate-template-container" style="background-image: url(\'/store/1/default_images/certificate_background.jpg\');">
    <div class="draggable-element" data-name="title" style="position: absolute !important; top: 220px; left: 165px; width: 600px; font-size: 24px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">شهادة إتمام دورة تدريبية</div>
    <div class="draggable-element" data-name="student_name" style="position: absolute !important; top: 260px; left: 165px; width: 600px; font-size: 22px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">تُمنح إلى: [student_name]</div>
    <div class="draggable-element" data-name="subtitle" style="position: absolute !important; top: 295px; left: 165px; width: 600px; font-size: 18px; color: rgb(0, 0, 0); text-align: center;">لاجتيازه بنجاح: [course_name]</div>
    <div class="draggable-element" data-name="body" style="position: absolute !important; top: 330px; left: 165px; width: 600px; font-size: 16px; color: rgb(0, 0, 0); text-align: center;">بتقدير [grade] بنجاح وتفوق</div>
    <div class="draggable-element" data-name="date" style="position: absolute !important; top: 80px; left: 57px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[date]</div>
    <div class="draggable-element" data-name="platform_name" style="position: absolute !important; top: 48px; left: 56px; font-size: 16px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[platform_name]</div>
    <div class="draggable-element" data-name="hint" style="position: absolute !important; top: 49px; left: 740px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">رقم الشهادة : [certificate_id]</div>
    <div class="draggable-element" data-name="stamp" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 400px; left: 100px; width: 128px; height: 128px;"><img src="/store/1/default_images/stamp.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="platform_signature" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 400px; left: 241px; width: 128px; height: 128px;"><img src="/store/1/default_images/signature.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="qr_code" style="position: absolute !important; top: 392px; left: 728px; width: 128px; height: 128px;">[qr_code]</div>
</div>';

$arElements = [
    'title' => ['content' => 'شهادة إتمام دورة تدريبية', 'font_size' => '24', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'student_name' => ['content' => 'تُمنح إلى: [student_name]', 'font_size' => '22', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'subtitle' => ['content' => 'لاجتيازه بنجاح: [course_name]', 'font_size' => '18', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'body' => ['content' => 'بتقدير [grade] بنجاح وتفوق', 'font_size' => '16', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'date' => ['display_date' => 'textual', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[date]'],
    'qr_code' => ['image_size' => '128', 'enable' => 'on', 'content' => '[qr_code]'],
    'hint' => ['content' => 'رقم الشهادة : [certificate_id]', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on'],
    'platform_name' => ['font_size' => '16', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[platform_name]'],
    'stamp' => ['image' => '/store/1/default_images/stamp.png', 'image_size' => '128', 'enable' => 'on'],
    'platform_signature' => ['image' => '/store/1/default_images/signature.png', 'image_size' => '128', 'enable' => 'on'],
];

// Update DB
$tr = CertificateTemplateTranslation::where('certificate_template_id', 7)->where('locale', 'ar')->first();
if ($tr) {
    $tr->update([
        'title' => 'قالب شهادة الاختبار (عربي)',
        'body' => $arBody,
        'elements' => json_encode($arElements, JSON_UNESCAPED_UNICODE)
    ]);
    echo "Updated Arabic translation for Template 7 in database.\n";
}

// Also update base template 7 image and body if needed
$t7 = CertificateTemplate::find(7);
if ($t7) {
    $t7->update([
        'image' => '/store/1/default_images/certificate_background.jpg'
    ]);
}
