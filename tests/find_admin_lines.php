<?php

$file = __DIR__ . '/../lang/ar/admin/main.php';
$lines = file($file);

$targetKeys = [
    'course', 'dashboard', 'admin_dashboard_show', 'webinar', 'home_sections',
    'settings_home_sections', 'class', 'type_webinar', 'type_course',
    'general_dashboard_title', 'marketing_dashboard_title', 'marketing_dashboard',
    'quiz_title', 'notification_waiting_quiz', 'notification_new_quiz', 'quiz_certificate',
    'add_quiz', 'add_new_quizzes'
];

foreach ($lines as $num => $line) {
    foreach ($targetKeys as $k) {
        if (preg_match("/['\"]" . preg_quote($k, '/') . "['\"]\s*=>/", $line)) {
            echo "Line " . ($num + 1) . ": " . trim($line) . "\n";
        }
    }
}
