<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\User::where('role_name', 'admin')->first();
if ($admin) {
    $admin->password = Hash::make('123456');
    $admin->save();
    echo "Admin password updated to 123456\n";
}

