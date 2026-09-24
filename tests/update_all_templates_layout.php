<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use App\Models\Translation\CertificateTemplateTranslation;

// 1. Template 7 (Quiz Certificate)
// Arabic translation
$arBody7 = '<div class="certificate-template-container" style="background-image: url(\'/store/1/default_images/certificate_background.jpg\');">
    <div class="draggable-element" data-name="title" style="position: absolute !important; top: 275px; left: 165px; width: 600px; font-size: 20px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">تُمنح هذه الشهادة إلى</div>
    <div class="draggable-element" data-name="student_name" style="position: absolute !important; top: 310px; left: 165px; width: 600px; font-size: 28px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">[student_name]</div>
    <div class="draggable-element" data-name="subtitle" style="position: absolute !important; top: 360px; left: 165px; width: 600px; font-size: 18px; color: rgb(0, 0, 0); text-align: center;">واجتيازه بنجاح: [course_name]</div>
    <div class="draggable-element" data-name="body" style="position: absolute !important; top: 395px; left: 165px; width: 600px; font-size: 16px; color: rgb(0, 0, 0); text-align: center;">بتقدير [grade] بنجاح وتفوق</div>
    <div class="draggable-element" data-name="date" style="position: absolute !important; top: 80px; left: 80px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[date]</div>
    <div class="draggable-element" data-name="platform_name" style="position: absolute !important; top: 50px; left: 80px; font-size: 16px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[platform_name]</div>
    <div class="draggable-element" data-name="hint" style="position: absolute !important; top: 50px; left: 700px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">رقم الشهادة : [certificate_id]</div>
    <div class="draggable-element" data-name="stamp" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 440px; left: 100px; width: 110px; height: 110px;"><img src="/store/1/default_images/stamp.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="platform_signature" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 440px; left: 230px; width: 110px; height: 110px;"><img src="/store/1/default_images/signature.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="qr_code" style="position: absolute !important; top: 430px; left: 730px; width: 110px; height: 110px;">[qr_code]</div>
</div>';

$arElements7 = [
    'title' => ['content' => 'تُمنح هذه الشهادة إلى', 'font_size' => '20', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'student_name' => ['content' => '[student_name]', 'font_size' => '28', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'subtitle' => ['content' => 'واجتيازه بنجاح: [course_name]', 'font_size' => '18', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'body' => ['content' => 'بتقدير [grade] بنجاح وتفوق', 'font_size' => '16', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'date' => ['display_date' => 'textual', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[date]'],
    'qr_code' => ['image_size' => '110', 'enable' => 'on', 'content' => '[qr_code]'],
    'hint' => ['content' => 'رقم الشهادة : [certificate_id]', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on'],
    'platform_name' => ['font_size' => '16', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[platform_name]'],
    'stamp' => ['image' => '/store/1/default_images/stamp.png', 'image_size' => '110', 'enable' => 'on'],
    'platform_signature' => ['image' => '/store/1/default_images/signature.png', 'image_size' => '110', 'enable' => 'on'],
];

// English translation
$enBody7 = '<div class="certificate-template-container" style="background-image: url(\'/store/1/default_images/certificate_background.jpg\');">
    <div class="draggable-element" data-name="title" style="position: absolute !important; top: 275px; left: 165px; width: 600px; font-size: 20px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">This certificate is proudly presented to</div>
    <div class="draggable-element" data-name="student_name" style="position: absolute !important; top: 310px; left: 165px; width: 600px; font-size: 28px; color: rgb(0, 0, 0); text-align: center; font-weight: bold;">[student_name]</div>
    <div class="draggable-element" data-name="subtitle" style="position: absolute !important; top: 360px; left: 165px; width: 600px; font-size: 18px; color: rgb(0, 0, 0); text-align: center;">for successfully completing [course_name]</div>
    <div class="draggable-element" data-name="body" style="position: absolute !important; top: 395px; left: 165px; width: 600px; font-size: 16px; color: rgb(0, 0, 0); text-align: center;">with a final grade of [grade]%</div>
    <div class="draggable-element" data-name="date" style="position: absolute !important; top: 80px; left: 80px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[date]</div>
    <div class="draggable-element" data-name="platform_name" style="position: absolute !important; top: 50px; left: 80px; font-size: 16px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">[platform_name]</div>
    <div class="draggable-element" data-name="hint" style="position: absolute !important; top: 50px; left: 700px; font-size: 14px; color: rgb(0, 0, 0); text-align: inherit; font-weight: bold;">ID : [certificate_id]</div>
    <div class="draggable-element" data-name="stamp" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 440px; left: 100px; width: 110px; height: 110px;"><img src="/store/1/default_images/stamp.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="platform_signature" style="position: absolute !important; display: flex; align-items: center; justify-content: center; top: 440px; left: 230px; width: 110px; height: 110px;"><img src="/store/1/default_images/signature.png" style="max-height: 100%; max-width: 100%;"/></div>
    <div class="draggable-element" data-name="qr_code" style="position: absolute !important; top: 430px; left: 730px; width: 110px; height: 110px;">[qr_code]</div>
</div>';

$enElements7 = [
    'title' => ['content' => 'This certificate is proudly presented to', 'font_size' => '20', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'student_name' => ['content' => '[student_name]', 'font_size' => '28', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'text_center' => 'on', 'enable' => 'on'],
    'subtitle' => ['content' => 'for successfully completing [course_name]', 'font_size' => '18', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'body' => ['content' => 'with a final grade of [grade]%', 'font_size' => '16', 'font_color' => '#000000', 'text_center' => 'on', 'enable' => 'on'],
    'date' => ['display_date' => 'textual', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[date]'],
    'qr_code' => ['image_size' => '110', 'enable' => 'on', 'content' => '[qr_code]'],
    'hint' => ['content' => 'ID : [certificate_id]', 'font_size' => '14', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on'],
    'platform_name' => ['font_size' => '16', 'font_color' => '#000000', 'font_weight_bold' => 'on', 'enable' => 'on', 'content' => '[platform_name]'],
    'stamp' => ['image' => '/store/1/default_images/stamp.png', 'image_size' => '110', 'enable' => 'on'],
    'platform_signature' => ['image' => '/store/1/default_images/signature.png', 'image_size' => '110', 'enable' => 'on'],
];

// Update Template 7
$t7 = CertificateTemplate::find(7);
if ($t7) {
    $t7->update([
        'image' => '/store/1/default_images/certificate_background.jpg',
        'body' => $enBody7
    ]);
}

CertificateTemplateTranslation::updateOrCreate([
    'certificate_template_id' => 7,
    'locale' => 'ar'
], [
    'title' => 'قالب شهادة الاختبار (عربي)',
    'body' => $arBody7,
    'elements' => json_encode($arElements7, JSON_UNESCAPED_UNICODE)
]);

CertificateTemplateTranslation::updateOrCreate([
    'certificate_template_id' => 7,
    'locale' => 'en'
], [
    'title' => 'Quiz Certificate Template',
    'body' => $enBody7,
    'elements' => json_encode($enElements7, JSON_UNESCAPED_UNICODE)
]);

// 2. Template 8 (Course Completion Certificate)
$t8 = CertificateTemplate::find(8);
if ($t8) {
    $t8->update([
        'image' => '/store/1/default_images/certificate_background.jpg',
        'body' => $enBody7
    ]);
    
    CertificateTemplateTranslation::updateOrCreate([
        'certificate_template_id' => 8,
        'locale' => 'ar'
    ], [
        'title' => 'قالب شهادة إتمام الدورة (عربي)',
        'body' => $arBody7,
        'elements' => json_encode($arElements7, JSON_UNESCAPED_UNICODE)
    ]);

    CertificateTemplateTranslation::updateOrCreate([
        'certificate_template_id' => 8,
        'locale' => 'en'
    ], [
        'title' => 'Course Completion Certificate Template',
        'body' => $enBody7,
        'elements' => json_encode($enElements7, JSON_UNESCAPED_UNICODE)
    ]);
}

echo "SUCCESS: Updated all templates and translations to harmonious parchment layout!\n";
