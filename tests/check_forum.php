<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$features = getFeaturesSettings();
echo "getFeaturesSettings():\n";
print_r($features);

$setting = \App\Models\Setting::where('name', 'features')->first();
if ($setting) {
    echo "\nSetting row found, id: " . $setting->id . "\n";
    $translations = \Illuminate\Support\Facades\DB::table('setting_translations')->where('setting_id', $setting->id)->get();
    foreach ($translations as $t) {
        echo "Locale: {$t->locale} | Value: {$t->value}\n";
    }
} else {
    echo "\nNo Setting row for 'features'\n";
}
