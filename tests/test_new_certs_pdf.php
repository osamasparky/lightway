<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Certificate;
use App\Models\QuizzesResult;
use App\Mixins\Certificate\MakeCertificate;

$maker = new MakeCertificate();
$certs = Certificate::with(['student', 'quiz', 'webinar'])->orderBy('id', 'desc')->take(6)->get();

foreach ($certs as $cert) {
    echo "Processing Certificate ID #{$cert->id} | Type: {$cert->type} | Student: {$cert->student->full_name}\n";
    if ($cert->type == 'quiz') {
        $result = QuizzesResult::where('quiz_id', $cert->quiz_id)->where('user_id', $cert->student_id)->first();
        if ($result) {
            $resp = $maker->makeQuizCertificate($result);
            if ($resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $path = $resp->getFile()->getPathname();
                $content = file_get_contents($path);
                $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
                echo "  -> Rendered PDF (Pages: {$pages}, Size: " . strlen($content) . " bytes)\n";
            }
        }
    } else if ($cert->type == 'course') {
        $resp = $maker->makeCourseCertificate($cert);
        if ($resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $path = $resp->getFile()->getPathname();
            $content = file_get_contents($path);
            $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
            echo "  -> Rendered PDF (Pages: {$pages}, Size: " . strlen($content) . " bytes)\n";
        }
    }
}
