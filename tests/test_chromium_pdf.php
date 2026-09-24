<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CertificateTemplate;
use App\Models\Translation\CertificateTemplateTranslation;
use App\Models\QuizzesResult;
use App\Mixins\Certificate\MakeCertificate;

$maker = new MakeCertificate();
$quizResult = QuizzesResult::where('status', 'passed')->first();

if ($quizResult) {
    app()->setLocale('ar');
    $template = CertificateTemplate::where('status', 'publish')->where('type', 'quiz')->first();
    $user = $quizResult->user;
    $quiz = $quizResult->quiz;
    $userCertificate = $maker->saveQuizCertificate($user, $quiz, $quizResult);

    $locale = 'ar';
    $body = (!empty($template->translate($locale)) and !empty($template->translate($locale)->body)) ? $template->translate($locale)->body : $template->body;

    $reflection = new \ReflectionClass($maker);
    $makeBodyMethod = $reflection->getMethod('makeBody');
    $makeBodyMethod->setAccessible(true);
    list($renderedBody, $bg) = $makeBodyMethod->invoke(
        $maker,
        $template,
        $userCertificate,
        $user,
        $body,
        $quiz->webinar ? $quiz->webinar->title : '-',
        $quizResult->user_grade,
        $quiz->webinar ? $quiz->webinar->teacher->id : null,
        $quiz->webinar ? $quiz->webinar->teacher->full_name : null,
        $quiz->webinar ? $quiz->webinar->duration : null
    );

    // If background was cleaned, make sure the container has background-image in inline style
    if (!str_contains($renderedBody, 'background-image:') && $bg) {
        $renderedBody = preg_replace('/(<div[^>]*class=["\'][^"\']*certificate-template-container[^"\']*["\'][^>]*)style=["\']([^"\']*)["\']/i', '$1style="$2; background-image: url(\'' . $bg . '\');"', $renderedBody);
    }

    $data = [
        'body' => $renderedBody,
        'backgroundImage' => $bg
    ];

    $html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);

    $tempHtmlFile = storage_path('app/temp_cert_test.html');
    $tempPdfFile = public_path('store/test_chromium_cert.pdf');
    $tempPngFile = public_path('store/test_chromium_cert.png');
    file_put_contents($tempHtmlFile, $html);

    $nodeScript = base_path('node/generate_certificate_pdf.js');
    $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($tempHtmlFile) . " " . escapeshellarg($tempPdfFile) . " " . escapeshellarg($tempPngFile);
    
    echo "Running command: {$cmd}\n";
    exec($cmd, $output, $returnCode);
    
    echo "Return code: {$returnCode}\n";
    echo "Output: " . implode("\n", $output) . "\n";
    
    if (file_exists($tempPdfFile)) {
        $size = filesize($tempPdfFile);
        echo "PDF successfully created! Size: {$size} bytes\n";
    }
    if (file_exists($tempPngFile)) {
        $size = filesize($tempPngFile);
        echo "PNG screenshot successfully created! Size: {$size} bytes\n";
    }
}
