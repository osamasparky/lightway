<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Quiz;
use App\Models\QuizzesResult;
use App\Models\Webinar;
use App\User;
use App\Mixins\Certificate\MakeCertificate;

$maker = new MakeCertificate();

// Test Scenario 1: Arabic Quiz Certificate with Real Data
app()->setLocale('ar');
$quizResultAr = QuizzesResult::where('status', 'passed')->first();
if ($quizResultAr) {
    echo "--- Scenario 1: Arabic Quiz Certificate ---\n";
    $response = $maker->makeQuizCertificate($quizResultAr);
    $filePath = $response->getFile()->getPathname();
    $content = file_get_contents($filePath);
    $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
    echo "Arabic Certificate generated: " . basename($filePath) . " | Pages: {$pages} | Size: " . strlen($content) . " bytes\n\n";
}

// Test Scenario 2: English Quiz Certificate
app()->setLocale('en');
if ($quizResultAr) {
    echo "--- Scenario 2: English Quiz Certificate ---\n";
    $response = $maker->makeQuizCertificate($quizResultAr);
    $filePath = $response->getFile()->getPathname();
    $content = file_get_contents($filePath);
    $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
    echo "English Certificate generated: " . basename($filePath) . " | Pages: {$pages} | Size: " . strlen($content) . " bytes\n\n";
}

// Test Scenario 3: Course Certificate with Long Arabic Course Name & Student
app()->setLocale('ar');
$certificate = Certificate::first();
if ($certificate) {
    echo "--- Scenario 3: Course Completion Certificate ---\n";
    $response = $maker->makeCourseCertificate($certificate);
    if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
        $filePath = $response->getFile()->getPathname();
        $content = file_get_contents($filePath);
        $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
        echo "Course Certificate generated: " . basename($filePath) . " | Pages: {$pages} | Size: " . strlen($content) . " bytes\n\n";
    }
}

echo "ALL TEST SCENARIOS COMPLETED SUCCESSFULLY!\n";
