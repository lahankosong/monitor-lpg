<?php
require '../vendor/autoload.php';
$app = require '../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Artisan::call('migrate', ['--force' => true]);
echo '<pre>' . \Artisan::output() . '</pre>';