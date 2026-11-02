<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appel texte → JSON à Google Gemini (même format que les autres modules du projet).
 * Renvoie null si l'IA est désactivée, sans clé, indisponible ou si la réponse n'est pas un JSON :
 * l'appelant applique alors ses règles locales.
 */
class GeminiJson
{
    public function __construct(
        private string $configCle = 'services.admin_ia',
        private int $delai = 6
    ) {
    }

    public function disponible(): bool
    {
        return filter_var(config($this->configCle.'.actif', true), FILTER_VALIDATE_BOOLEAN)
            && trim((string) config($this->configCle.'.gemini_key')) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function demander(string $consigne, float $temperature = 0.1): ?array
    {
        if (! $this->disponible()) {
            return null;
        }

        $modele = (string) config('services.vision.gemini_model') ?: 'gemini-3.5-flash-lite';

        try {
            $reponse = Http::timeout($this->delai)
                // Même bundle de certificats que la connexion MongoDB : utile quand php.ini n'a pas de curl.cainfo.
                ->when(file_exists(storage_path('certs/cacert.pem')), fn ($http) => $http->withOptions(['verify' => storage_path('certs/cacert.pem')]))
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => trim((string) config($this->configCle.'.gemini_key'))])
                ->post(sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', rawurlencode($modele)), [
                    'contents' => [['parts' => [['text' => $consigne]]]],
                    'generationConfig' => ['temperature' => $temperature, 'responseMimeType' => 'application/json'],
                ]);

            if (! $reponse->successful()) {
                Log::warning('IA admin : Gemini indisponible', ['statut' => $reponse->status()]);

                return null;
            }

            $texte = '';
            foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
                if (is_array($part) && isset($part['text']) && empty($part['thought'])) {
                    $texte .= $part['text'];
                }
            }

            $decode = json_decode(trim($texte), true);

            return is_array($decode) ? $decode : null;
        } catch (Throwable $e) {
            Log::warning('IA admin : échec de l\'appel Gemini', ['erreur' => $e->getMessage()]);

            return null;
        }
    }
}
