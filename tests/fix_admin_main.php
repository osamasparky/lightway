<?php

$file = __DIR__ . '/../lang/ar/admin/main.php';
$content = file_get_contents($file);

$replacements = [
    "'course' => 'بالطبع'," => "'course' => 'دورة تدريبية',",
    "'dashboard' => 'لوحة القيادة'," => "'dashboard' => 'لوحة التحكم',",
    "'admin_dashboard_show' => 'إظهار لوحة القيادة'," => "'admin_dashboard_show' => 'عرض لوحة التحكم',",
    "'webinar' => 'بالطبع'," => "'webinar' => 'دورة تدريبية',",
    "'home_sections' => 'أقسام المنزل'," => "'home_sections' => 'أقسام الصفحة الرئيسية',",
    "'settings_home_sections' => 'أقسام المنزل'," => "'settings_home_sections' => 'إعدادات أقسام الصفحة الرئيسية',",
    "'notification_waiting_quiz' => 'مسابقة انتظار جديدة'," => "'notification_waiting_quiz' => 'اختبار جديد بانتظار التصحيح',",
    "'class' => 'بالطبع'," => "'class' => 'دورة تدريبية',",
    "'quiz_title' => 'عنوان المسابقة'," => "'quiz_title' => 'عنوان الاختبار',",
    "'type_webinar' => 'بالطبع'," => "'type_webinar' => 'جلسة مباشرة',",
    "'type_course' => 'بالطبع'," => "'type_course' => 'دورة مسجلة',",
    "'general_dashboard_title' => 'لوحة القيادة'," => "'general_dashboard_title' => 'لوحة التحكم العامة',",
    "'marketing_dashboard_title' => 'لوحة القيادة التسويقية'," => "'marketing_dashboard_title' => 'لوحة تحكم التسويق',",
    "'marketing_dashboard' => 'لوحة القيادة التسويقية'," => "'marketing_dashboard' => 'لوحة تحكم التسويق',",
    "'notification_new_quiz' => 'مسابقة جديدة'," => "'notification_new_quiz' => 'اختبار جديد تم إنشاؤه',",
];

foreach ($replacements as $search => $replace) {
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replace, $content);
        echo "Replaced: $search => $replace\n";
    } else {
        echo "NOT FOUND: $search\n";
    }
}

file_put_contents($file, $content);
echo "Done updating admin/main.php\n";
