<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;
use App\Models\Quiz;
use App\Models\Webinar;
use App\Models\QuizzesResult;
use App\Models\Certificate;
use App\Mixins\Certificate\MakeCertificate;

echo "Current Students: " . User::where('role_name', 'user')->count() . "\n";
echo "Current Quizzes: " . Quiz::count() . "\n";
echo "Current Webinars: " . Webinar::count() . "\n";
echo "Current Certificates: " . Certificate::count() . "\n";

// Let's create or find students
$studentsData = [
    [
        'full_name' => 'سارة أحمد العتيبي',
        'email' => 'sarah.otaibi@example.com',
        'mobile' => '0501112233',
        'grade' => 95
    ],
    [
        'full_name' => 'عبدالرحمن محمد الشهري',
        'email' => 'abdulrahman.shehri@example.com',
        'mobile' => '0502223344',
        'grade' => 92
    ],
    [
        'full_name' => 'نورة خالد القحطاني',
        'email' => 'noura.qahtani@example.com',
        'mobile' => '0503334455',
        'grade' => 88
    ],
    [
        'full_name' => 'فهد عبدالعزيز الدوسري',
        'email' => 'fahad.dosari@example.com',
        'mobile' => '0504445566',
        'grade' => 98
    ],
    [
        'full_name' => 'Sophia Alexander',
        'email' => 'sophia.alexander@example.com',
        'mobile' => '0505556677',
        'grade' => 94
    ],
    [
        'full_name' => 'Alexander Hayes',
        'email' => 'alexander.hayes@example.com',
        'mobile' => '0506667788',
        'grade' => 89
    ]
];

$quizzes = Quiz::where('status', 'active')->with('webinar')->get();
if ($quizzes->isEmpty()) {
    $quizzes = Quiz::with('webinar')->get();
}

$webinars = Webinar::where('status', 'active')->get();

$maker = new MakeCertificate();
$createdCount = 0;

foreach ($studentsData as $index => $sData) {
    $user = User::firstOrCreate(
        ['email' => $sData['email']],
        [
            'full_name' => $sData['full_name'],
            'role_name' => 'user',
            'role_id' => 1,
            'status' => 'active',
            'mobile' => $sData['mobile'],
            'password' => bcrypt('123456'),
            'created_at' => time() - (86400 * (10 - $index))
        ]
    );

    // Make sure user has active status
    $user->update(['status' => 'active', 'full_name' => $sData['full_name']]);

    // Pick a quiz
    $quiz = $quizzes[$index % $quizzes->count()];

    if ($quiz) {
        $timestamp = time() - (86400 * ($index + 1)) + (3600 * $index);

        // Create or update QuizzesResult
        $quizResult = QuizzesResult::updateOrCreate(
            [
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
            ],
            [
                'user_grade' => $sData['grade'],
                'status' => QuizzesResult::$passed,
                'created_at' => $timestamp
            ]
        );

        // Save Certificate
        $cert = $maker->saveQuizCertificate($user, $quiz, $quizResult);
        if ($cert) {
            $createdCount++;
            echo "✔ Issued Quiz Certificate ID #{$cert->id} for '{$user->full_name}' in Quiz: '{$quiz->title}' (Grade: {$sData['grade']}%)\n";
        }
    }

    // Also issue a Course Completion Certificate
    $webinar = $webinars[$index % $webinars->count()];
    if ($webinar) {
        $courseCert = $maker->saveCourseCertificate($user, $webinar);
        if ($courseCert) {
            $createdCount++;
            echo "✔ Issued Course Certificate ID #{$courseCert->id} for '{$user->full_name}' in Course: '{$webinar->title}'\n";
        }
    }
}

echo "\n============================================\n";
echo "Successfully seeded {$createdCount} new student certificates!\n";
echo "Total Certificates now in system: " . Certificate::count() . "\n";
echo "============================================\n";
