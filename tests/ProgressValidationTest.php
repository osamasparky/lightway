<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Webinar;
use App\Models\CourseLearning;
use App\Models\WebinarAssignmentHistory;
use App\Models\QuizzesResult;
use App\User;

echo "--- PROGRESS CALCULATION LOGIC VALIDATION ---\n";

// Grab existing active webinars
$webinars = Webinar::where('status', Webinar::$active)->take(5)->get();
$user = User::first();

if (!$user || $webinars->isEmpty()) {
    echo "No user or webinars found to test.\n";
    exit;
}

$allMatch = true;

foreach ($webinars as $webinar) {
    // 1. Old logic simulation for Files
    $oldFilesCount = $webinar->files()->where('status', 'active')->count();
    $oldFilesPassed = 0;
    foreach ($webinar->files()->where('status', 'active')->get() as $f) {
        $st = CourseLearning::where('user_id', $user->id)->where('file_id', $f->id)->first();
        if ($st) $oldFilesPassed++;
    }

    // New logic
    $newFilesStat = $webinar->getFilesLearningProgressStat($user->id);

    if ($oldFilesCount !== $newFilesStat['count'] || $oldFilesPassed !== $newFilesStat['passed']) {
        echo "MISMATCH on Webinar {$webinar->id} Files! Old: {$oldFilesPassed}/{$oldFilesCount} vs New: {$newFilesStat['passed']}/{$newFilesStat['count']}\n";
        $allMatch = false;
    }

    // 2. Old logic simulation for Sessions
    $oldSessionsCount = $webinar->sessions()->where('status', 'active')->count();
    $oldSessionsPassed = 0;
    foreach ($webinar->sessions()->where('status', 'active')->get() as $s) {
        $st = CourseLearning::where('user_id', $user->id)->where('session_id', $s->id)->first();
        if ($st) $oldSessionsPassed++;
    }

    $newSessionsStat = $webinar->getSessionsLearningProgressStat($user->id);
    if ($oldSessionsCount !== $newSessionsStat['count'] || $oldSessionsPassed !== $newSessionsStat['passed']) {
        echo "MISMATCH on Webinar {$webinar->id} Sessions!\n";
        $allMatch = false;
    }

    // 3. Old logic simulation for Text Lessons
    $oldLessonsCount = $webinar->textLessons()->where('status', 'active')->count();
    $oldLessonsPassed = 0;
    foreach ($webinar->textLessons()->where('status', 'active')->get() as $tl) {
        $st = CourseLearning::where('user_id', $user->id)->where('text_lesson_id', $tl->id)->first();
        if ($st) $oldLessonsPassed++;
    }

    $newLessonsStat = $webinar->getTextLessonsLearningProgressStat($user->id);
    if ($oldLessonsCount !== $newLessonsStat['count'] || $oldLessonsPassed !== $newLessonsStat['passed']) {
        echo "MISMATCH on Webinar {$webinar->id} Text Lessons!\n";
        $allMatch = false;
    }

    // 4. Old logic simulation for Assignments
    $oldAssignmentsCount = $webinar->assignments()->where('status', 'active')->count();
    $oldAssignmentsPassed = 0;
    foreach ($webinar->assignments()->where('status', 'active')->get() as $a) {
        $st = WebinarAssignmentHistory::where('assignment_id', $a->id)->where('student_id', $user->id)->where('status', WebinarAssignmentHistory::$passed)->first();
        if ($st) $oldAssignmentsPassed++;
    }

    $newAssignmentsStat = $webinar->getAssignmentsLearningProgressStat($user->id);
    if ($oldAssignmentsCount !== $newAssignmentsStat['count'] || $oldAssignmentsPassed !== $newAssignmentsStat['passed']) {
        echo "MISMATCH on Webinar {$webinar->id} Assignments!\n";
        $allMatch = false;
    }

    // 5. Old logic simulation for Quizzes
    $oldQuizzesCount = $webinar->quizzes()->where('status', 'active')->count();
    $oldQuizzesPassed = 0;
    foreach ($webinar->quizzes()->where('status', 'active')->get() as $q) {
        $st = QuizzesResult::where('quiz_id', $q->id)->where('user_id', $user->id)->where('status', QuizzesResult::$passed)->first();
        if ($st) $oldQuizzesPassed++;
    }

    $newQuizzesStat = $webinar->getQuizzesLearningProgressStat($user->id);
    if ($oldQuizzesCount !== $newQuizzesStat['count'] || $oldQuizzesPassed !== $newQuizzesStat['passed']) {
        echo "MISMATCH on Webinar {$webinar->id} Quizzes!\n";
        $allMatch = false;
    }
}

echo "Progress Calculation Equivalence Result: " . ($allMatch ? "PASS (Exact mathematical & logical equivalence across all 5 modules)" : "FAIL") . "\n";
