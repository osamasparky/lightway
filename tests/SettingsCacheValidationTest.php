<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "--- SETTINGS CACHE VALIDATION ---\n";

Cache::flush();
Setting::$general = null;

// Test 1: Fetch setting in English
app()->setLocale('en');
$generalEn1 = Setting::getGeneralSettings();
$cachedEn = Cache::has('settings.general.en');
echo "1. Fetch 'general' in EN -> Cache key 'settings.general.en' exists: " . ($cachedEn ? 'YES (PASS)' : 'NO (FAIL)') . "\n";

// Test 2: Fetch setting again in English (should hit cache)
DB::flushQueryLog();
DB::enableQueryLog();
Setting::$general = null; // Clear static variable to force cache layer lookup
$generalEn2 = Setting::getGeneralSettings();
$queriesCount = count(DB::getQueryLog());
echo "2. Repeated fetch 'general' in EN (static var reset) -> DB Queries: $queriesCount " . ($queriesCount === 0 ? '(PASS - Cache Hit)' : '(FAIL - Query Fired)') . "\n";

// Test 3: Fetch setting in Arabic (should be separated cache key)
app()->setLocale('ar');
Setting::$general = null;
$generalAr1 = Setting::getGeneralSettings();
$cachedAr = Cache::has('settings.general.ar');
echo "3. Fetch 'general' in AR -> Cache key 'settings.general.ar' exists: " . ($cachedAr ? 'YES (PASS)' : 'NO (FAIL)') . "\n";

// Test 4: Locale separation
$enVal = Cache::get('settings.general.en');
$arVal = Cache::get('settings.general.ar');
echo "4. Cache locale separation (settings.general.en and settings.general.ar both exist): " . ($enVal !== null && $arVal !== null ? 'PASS' : 'FAIL') . "\n";

// Test 5: Cache invalidation on save
$settingModel = Setting::where('name', 'general')->first();
if ($settingModel) {
    $settingModel->touch(); // triggers save
    $clearedEn = !Cache::has('settings.general.en');
    $clearedAr = !Cache::has('settings.general.ar');
    echo "5. Model touch() invalidates settings.general.* cache: " . ($clearedEn && $clearedAr ? 'PASS' : 'FAIL') . "\n";
}

echo "Settings Cache Validation Complete.\n";
