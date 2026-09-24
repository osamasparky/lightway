<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Mixins\Certificate\MakeCertificate;
use App\Models\Certificate;
use App\Models\QuizzesResult;

$makeCertificate = new MakeCertificate();
$records = Certificate::all();

echo "Testing Chromium PDF Generation across all " . $records->count() . " certificates:\n\n";
$successCount = 0;
$failCount = 0;

foreach ($records as $certificate) {
    $studentName = $certificate->student ? $certificate->student->full_name : 'N/A';
    $type = $certificate->type ?? 'quiz';
    
    echo sprintf("[%02d] Cert #%d (%s) | Student: %-25s\n", $certificate->id, $certificate->id, $type, $studentName);
    
    try {
        $response = null;
        if ($certificate->type == 'quiz') {
            $quizResult = QuizzesResult::where('id', $certificate->quiz_result_id)
                ->with([
                    'quiz' => function ($query) {
                        $query->with(['webinar.teacher']);
                    },
                    'user'
                ])
                ->first();

            if ($quizResult) {
                $response = $makeCertificate->makeQuizCertificate($quizResult);
            } else {
                echo "     ✗ QuizzesResult not found\n";
                $failCount++;
                continue;
            }
        } else if ($certificate->type == 'course') {
            $certificate->load(['webinar.teacher', 'student']);
            $response = $makeCertificate->makeCourseCertificate($certificate);
        } else if ($certificate->type == 'bundle') {
            $certificate->load(['bundle.teacher', 'student']);
            $response = $makeCertificate->makeBundleCertificate($certificate);
        }
        
        $studentId = !empty($certificate->student_id) ? $certificate->student_id : 'certificates';
        $pdfPath = public_path("store/{$studentId}/certificates/certificate_{$certificate->id}.pdf");
        
        if (file_exists($pdfPath) && filesize($pdfPath) > 1000) {
            $sizeKb = round(filesize($pdfPath) / 1024, 1);
            echo "     ✓ SUCCESS (Chromium): {$pdfPath} ({$sizeKb} KB)\n";
            $successCount++;
        } else {
            echo "     ✗ FAILED: File missing or empty ({$pdfPath})\n";
            $failCount++;
        }
    } catch (\Throwable $e) {
        echo "     ✗ EXCEPTION: " . $e->getMessage() . "\n";
        $failCount++;
    }
}

echo "\n============================================\n";
echo "SUMMARY: {$successCount} Succeeded, {$failCount} Failed out of {$records->count()} certificates.\n";
echo "============================================\n";
