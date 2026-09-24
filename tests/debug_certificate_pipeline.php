<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Mixins\Certificate\MakeCertificate;

$debugDir = storage_path('app/debug');
if (!file_exists($debugDir)) {
    mkdir($debugDir, 0777, true);
}

// Find Cert 21 or course certificate for Noura
$certificate = Certificate::with(['webinar.teacher', 'student'])->find(21);
if (!$certificate) {
    $certificate = Certificate::with(['webinar.teacher', 'student'])->where('type', 'course')->first();
}

echo "Generating HTML for Certificate #{$certificate->id} (Student: " . ($certificate->student ? $certificate->student->full_name : 'N/A') . ")\n";

$template = CertificateTemplate::where('status', 'publish')
    ->where('type', 'course')
    ->first();

$course = $certificate->webinar;
$user = $certificate->student;

$locale = 'ar';
$body = (!empty($template->translate($locale)) and !empty($template->translate($locale)->body)) ? $template->translate($locale)->body : $template->body;

$reflection = new \ReflectionClass(MakeCertificate::class);
$makeBodyMethod = $reflection->getMethod('makeBody');
$makeBodyMethod->setAccessible(true);

$makeCert = new MakeCertificate();
list($parsedBody, $backgroundImage) = $makeBodyMethod->invoke(
    $makeCert,
    $template,
    $certificate,
    $user,
    $body,
    $course ? $course->title : 'معاني الأحاديث القدسية',
    95,
    $course && $course->teacher ? $course->teacher->id : null,
    $course && $course->teacher ? $course->teacher->full_name : null,
    $course ? $course->duration : null
);

$data = [
    'body' => $parsedBody,
    'backgroundImage' => $backgroundImage,
];

$html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);

$htmlPath = $debugDir . '/certificate-pdf-debug.html';
file_put_contents($htmlPath, $html);
echo "Wrote debug HTML to: {$htmlPath}\n";

$pngPath = $debugDir . '/certificate-chromium.png';
$pdfPath = $debugDir . '/certificate-output.pdf';

$cmd = "node " . escapeshellarg(base_path('node/debug_certificate.js')) . " " . escapeshellarg($htmlPath) . " " . escapeshellarg($pngPath) . " " . escapeshellarg($pdfPath);
passthru($cmd);
