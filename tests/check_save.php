<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

$settingModel = Setting::where('name', 'general')->first();
if ($settingModel) {
    Cache::put('settings.general.en', ['test' => 1], 3600);
    Cache::put('settings.general.ar', ['test' => 2], 3600);
    
    // Setting updated_at doesn't exist ($timestamps = false), so save() with updated value:
    $settingModel->updated_at = time(); // or touch dirty
    $settingModel->save();
    
    $clearedEn = !Cache::has('settings.general.en');
    $clearedAr = !Cache::has('settings.general.ar');
    echo "save() triggers booted() saved listener: " . ($clearedEn && $clearedAr ? 'PASS' : 'FAIL') . "\n";
}
