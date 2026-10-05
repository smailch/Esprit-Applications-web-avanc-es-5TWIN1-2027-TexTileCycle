<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    App\Modules\Associations\Models\Association::create([
        'user_id' => '123',
        'nom' => 'test',
        'description' => 'test',
        'adresse' => 'test',
        'statut' => 'actif',
        'besoins' => []
    ]);
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
