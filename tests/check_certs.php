<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cert4 = App\Models\Certificate::find(4);
if ($cert4) {
    echo "Cert 4: type={$cert4->type}, quiz_id={$cert4->quiz_id}, quiz_result_id={$cert4->quiz_result_id}\n";
    $result = App\Models\QuizzesResult::find($cert4->quiz_result_id);
    if ($result) {
        echo "QuizResult: user_id={$result->user_id}, quiz_id={$result->quiz_id}, grade={$result->user_grade}\n";
    }
}
$cert10 = App\Models\Certificate::find(10);
if ($cert10) {
    echo "Cert 10: type={$cert10->type}, quiz_id={$cert10->quiz_id}\n";
}
