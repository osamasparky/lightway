<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Illuminate\Support\Facades\Storage;

$template = CertificateTemplate::where('status', 'publish')->where('type', 'quiz')->first();
$body = $template->body;

$body = str_replace('[student]', 'محمد عبد الله', $body);
$body = str_replace('[student_name]', 'محمد عبد الله', $body);
$body = str_replace('[platform_name]', 'Meem LMS', $body);
$body = str_replace('[course]', 'دورة تطوير الويب الاحترافية', $body);
$body = str_replace('[course_name]', 'دورة تطوير الويب الاحترافية', $body);
$body = str_replace('[grade]', '95', $body);
$body = str_replace('[certificate_id]', '1001', $body);
$body = str_replace('[date]', date('Y-m-d'), $body);
$body = str_replace('[instructor_name]', 'أحمد القحطاني', $body);
$body = str_replace('[duration]', '40', $body);
$body = str_replace('[instructor_signature]', '', $body);
$body = str_replace('[user_certificate_additional]', '', $body);
$body = str_replace('[qr_code]', '', $body);

$data = ['body' => $body];
$html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);

try {
    $pdf = PDF::loadHTML($html, [
        'format' => [CertificateTemplate::$templateWidth * 0.264583, CertificateTemplate::$templateHeight * 0.264583], // convert px to mm
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    
    $pdfContent = $pdf->output();
    $storage = Storage::disk('public');
    $storage->put('test_certificate.pdf', $pdfContent);
    echo "SUCCESS: Certificate generated locally and saved to storage. Size: " . strlen($pdfContent) . " bytes\n";
} catch (\Throwable $e) {
    echo "Error rendering certificate: " . $e->getMessage() . "\n";
}
