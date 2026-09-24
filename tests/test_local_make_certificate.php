<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\QuizzesResult;
use App\Mixins\Certificate\MakeCertificate;

$quizResult = QuizzesResult::where('status', 'passed')->first();

if (!$quizResult) {
    echo "Creating a dummy passed quiz result for testing...\n";
    $quiz = \App\Models\Quiz::first();
    $user = \App\User::first();
    if ($quiz && $user) {
        $quizResult = QuizzesResult::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'user_grade' => 90,
            'status' => 'passed',
            'created_at' => time()
        ]);
    }
}

if ($quizResult) {
    echo "Testing MakeCertificate for QuizResult ID: {$quizResult->id}\n";
    $maker = new MakeCertificate();
    $response = $maker->makeQuizCertificate($quizResult);

    if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
        echo "SUCCESS: Certificate generated and returned download response without external API!\n";
        echo "File status: " . ($response->getFile() ? $response->getFile()->getPathname() : 'memory') . "\n";
    } else {
        echo "Response type: " . get_class($response) . "\n";
    }
} else {
    echo "No quiz result available to test.\n";
}
