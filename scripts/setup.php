<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$root = dirname(__DIR__);
chdir($root);

if (! file_exists('.env') && ! copy('.env.example', '.env')) {
    throw new RuntimeException('Nao foi possivel criar o arquivo .env.');
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! config('app.key')) {
    Artisan::call('key:generate', ['--force' => true]);
    echo Artisan::output();
}

if (config('database.default') === 'sqlite') {
    $database = config('database.connections.sqlite.database');
    if ($database !== ':memory:' && ! file_exists($database) && ! touch($database)) {
        throw new RuntimeException('Nao foi possivel criar o banco SQLite.');
    }
}

$exit = Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
exit($exit);
