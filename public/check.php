<?php
require '../vendor/autoload.php';
$app = require '../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Artisan::call('route:list', ['--name' => 'dashboard.index']);
echo \Artisan::output();