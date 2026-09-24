<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fonts = [
    'vazir_reg_woff2' => public_path('assets/default/fonts/vazir/Vazir-Regular.woff2'),
    'vazir_reg_ttf' => public_path('assets/default/fonts/vazir/Vazir-Regular.ttf'),
    'vazir_bold_woff2' => public_path('assets/default/fonts/vazir/Vazir-Bold.woff2'),
    'vazir_bold_ttf' => public_path('assets/default/fonts/vazir/Vazir-Bold.ttf'),
    'montserrat_med' => public_path('assets/default/fonts/Montserrat-Medium.ttf'),
    'montserrat_bold' => public_path('assets/default/fonts/Montserrat-Bold.ttf'),
];

foreach ($fonts as $name => $path) {
    echo sprintf("%-20s: %s (exists: %s, size: %s bytes)\n", $name, $path, file_exists($path) ? 'YES' : 'NO', file_exists($path) ? filesize($path) : 0);
}
