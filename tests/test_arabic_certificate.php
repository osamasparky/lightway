<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\QuizzesResult;
use App\Mixins\Certificate\MakeCertificate;

app()->setLocale('ar');

$quizResult = QuizzesResult::where('status', 'passed')->first();

if ($quizResult) {
    echo "Testing Arabic MakeCertificate for QuizResult ID: {$quizResult->id}\n";
    $maker = new MakeCertificate();
    $response = $maker->makeQuizCertificate($quizResult);

    if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
        $path = $response->getFile() ? $response->getFile()->getPathname() : 'memory';
        echo "SUCCESS: Arabic Certificate generated!\n";
        echo "File: " . $path . "\n";
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $pages = preg_match_all("/\/Type\s*\/Page\b/", $content, $m);
            echo "Page Count: " . $pages . " | File Size: " . strlen($content) . " bytes\n";
        }
    }
}
