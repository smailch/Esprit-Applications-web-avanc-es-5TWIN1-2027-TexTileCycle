<?php

/*
 * Exporte en LECTURE SEULE le catalogue des ateliers actifs (nom, description, prix des services) vers
 * ia-diagnostic/photos-test/catalogue-reel.json, pour que evaluer.py mesure sur les vraies données.
 * Aucune écriture dans la base. Le fichier produit n'est pas versionné (dossier photos-test/).
 *
 * Usage, depuis la racine du projet Laravel :  php ia-diagnostic/exporter_catalogue.php
 */

$racine = dirname(__DIR__);
require $racine.'/vendor/autoload.php';
$app = require $racine.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$lignes = [];
foreach (app(App\Modules\Ateliers\Services\AtelierService::class)->actifsAvecServices() as $atelier) {
    foreach ($atelier->services as $service) {
        $lignes[] = [
            'nom' => (string) $service->nom,
            'description' => (string) $service->description,
            'prix' => $service->prix_estime,
        ];
    }
}

$cible = __DIR__.'/photos-test/catalogue-reel.json';
file_put_contents($cible, json_encode($lignes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($lignes)." services exportés vers {$cible}\n";
