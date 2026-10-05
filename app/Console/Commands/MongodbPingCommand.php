<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MongoDB\Client;
use Throwable;

class MongodbPingCommand extends Command
{
    protected $signature = 'mongodb:ping';

    protected $description = 'Teste la connexion MongoDB Atlas (TLS / réseau)';

    public function handle(): int
    {
        $uri = config('database.connections.mongodb.dsn');
        $driverOptions = config('database.connections.mongodb.driver_options') ?? [];

        if (! $uri) {
            $this->error('MONGODB_URI manquant dans .env');

            return self::FAILURE;
        }

        $this->line('OpenSSL : '.OPENSSL_VERSION_TEXT);
        $this->line('URI (masquée) : '.$this->maskUri($uri));

        if (! empty($driverOptions['tlsCAFile'])) {
            $this->line('tlsCAFile : '.$driverOptions['tlsCAFile']);
        }

        try {
            $client = new Client($uri, ['serverSelectionTimeoutMS' => 15000], $driverOptions);
            $client->selectDatabase('admin')->command(['ping' => 1]);
            $this->info('Connexion MongoDB OK (ping).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Vérifications Atlas (dans l’ordre) :');
            $this->line('1. Network Access → Add Current IP Address (ou 0.0.0.0/0 en dev)');
            $this->line('2. Database Access → utilisateur avec mot de passe à jour');
            $this->line('3. .env → MONGODB_TLS_CA_FILE=storage/certs/cacert.pem');
            $this->line('4. php.ini → extension=mongodb + OpenSSL récent (PHP 8.2+ recommandé)');

            return self::FAILURE;
        }
    }

    private function maskUri(string $uri): string
    {
        return preg_replace('#://([^:]+):([^@]+)@#', '://$1:***@', $uri) ?? $uri;
    }
}
