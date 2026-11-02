<?php

namespace App\Modules\Core\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Autorise l'IP publique du poste dans MongoDB Atlas.
 * L'entrée précédente créée par l'application est remplacée : pas d'IP figée.
 */
class AtlasIpSynchronizer
{
    public const COMMENT = 'textilecycle-auto';

    private const COMMANDES = ['serve', 'db:seed', 'migrate', 'migrate:fresh', 'mongodb:ping'];

    public static function shouldRunForCurrentProcess(): bool
    {
        if (! app()->environment('local') || PHP_SAPI !== 'cli') {
            return false;
        }

        if (! filter_var(config('services.atlas.sync'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $command = $_SERVER['argv'][1] ?? '';

        return in_array($command, self::COMMANDES, true);
    }

    public function sync(): string
    {
        $projectId = (string) config('services.atlas.project_id');
        $publicKey = (string) config('services.atlas.public_key');
        $privateKey = (string) config('services.atlas.private_key');

        if ($projectId === '' || $publicKey === '' || $privateKey === '') {
            throw new RuntimeException(
                'Clés Atlas manquantes. Une seule fois dans .env : ATLAS_PROJECT_ID, ATLAS_PUBLIC_KEY, ATLAS_PRIVATE_KEY.'
            );
        }

        $ip = $this->currentPublicIp();
        $entries = $this->accessList($projectId, $publicKey, $privateKey);

        foreach ($entries as $entry) {
            $cidr = $entry['cidrBlock'] ?? '';
            if ($cidr === '0.0.0.0/0' || $cidr === '::/0') {
                return $ip;
            }
        }

        $already = false;
        $stale = [];

        foreach ($entries as $entry) {
            $address = $this->entryIp($entry);
            $isAuto = ($entry['comment'] ?? '') === self::COMMENT;

            if ($address === $ip) {
                $already = true;
                continue;
            }

            if ($isAuto && $address !== null) {
                $stale[] = $address;
            }
        }

        if (! $already) {
            $this->addIp($projectId, $publicKey, $privateKey, $ip);
        }

        foreach ($stale as $old) {
            $this->deleteEntry($projectId, $publicKey, $privateKey, $old);
        }

        return $ip;
    }

    private function currentPublicIp(): string
    {
        foreach (['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com'] as $url) {
            try {
                $response = Http::timeout(8)->get($url);
                $ip = trim($response->body());
                if ($response->successful() && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        throw new RuntimeException('Impossible de lire l\'adresse IP publique de cette machine.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accessList(string $projectId, string $publicKey, string $privateKey): array
    {
        $response = $this->client($publicKey, $privateKey)
            ->get($this->base($projectId).'/accessList?itemsPerPage=500');

        if ($response->failed()) {
            throw new RuntimeException($this->atlasError('lecture de la liste d\'accès', $response->status()));
        }

        return $response->json('results') ?? [];
    }

    private function addIp(string $projectId, string $publicKey, string $privateKey, string $ip): void
    {
        $response = $this->client($publicKey, $privateKey)
            ->post($this->base($projectId).'/accessList', [[
                'ipAddress' => $ip,
                'comment' => self::COMMENT,
            ]]);

        if ($response->status() === 409) {
            return;
        }

        if ($response->failed()) {
            throw new RuntimeException($this->atlasError('ajout de l\'IP '.$ip, $response->status()));
        }
    }

    private function deleteEntry(string $projectId, string $publicKey, string $privateKey, string $address): void
    {
        $response = $this->client($publicKey, $privateKey)
            ->delete($this->base($projectId).'/accessList/'.rawurlencode($address));

        if ($response->status() === 404) {
            return;
        }

        if ($response->failed()) {
            throw new RuntimeException($this->atlasError('retrait de l\'ancienne IP '.$address, $response->status()));
        }
    }

    private function client(string $publicKey, string $privateKey): PendingRequest
    {
        return Http::withOptions([
            'auth' => [$publicKey, $privateKey, 'digest'],
        ])->withHeaders([
            'Accept' => 'application/vnd.atlas.2023-02-01+json',
        ])->timeout(20);
    }

    private function base(string $projectId): string
    {
        return 'https://cloud.mongodb.com/api/atlas/v2/groups/'.rawurlencode($projectId);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function entryIp(array $entry): ?string
    {
        if (! empty($entry['ipAddress']) && filter_var($entry['ipAddress'], FILTER_VALIDATE_IP)) {
            return $entry['ipAddress'];
        }

        $cidr = $entry['cidrBlock'] ?? '';
        if (is_string($cidr) && preg_match('/^([^\/]+)\/32$/', $cidr, $match) && filter_var($match[1], FILTER_VALIDATE_IP)) {
            return $match[1];
        }

        return null;
    }

    private function atlasError(string $action, int $status): string
    {
        return "Atlas a refusé {$action} (HTTP {$status}). Vérifiez ATLAS_PROJECT_ID et la clé API (rôle Project Owner).";
    }
}
