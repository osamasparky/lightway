<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settings = App\Models\Setting::where('name', 'certificates')->first();
echo "Certificates Setting:\n";
if ($settings) {
    print_r(json_decode($settings->value, true));
} else {
    echo "No setting found\n";
}
