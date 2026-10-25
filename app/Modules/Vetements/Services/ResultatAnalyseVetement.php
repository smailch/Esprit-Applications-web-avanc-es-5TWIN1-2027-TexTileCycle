<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Vetements\Models\Vetement;

class ResultatAnalyseVetement
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function __construct(private array $donnees)
    {
    }

    /**
     * @param  array<string, mixed>  $brut
     */
    public static function depuisModele(array $brut, string $modele): self
    {
        $decision = (new PolitiqueDeuxiemeChance())->appliquer($brut);
        $decision['disponible'] = true;
        $decision['message'] = null;
        $decision['modele'] = $modele;
        $decision['analyse_le'] = now()->toIso8601String();
        $decision['verdict_libelle'] = self::libelleVerdict($decision['verdict'] ?? null);
        $decision['recommandation_libelle'] = self::libelleRecommandation($decision['recommandation'] ?? null);

        return new self($decision);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public static function depuisTableau(array $donnees): self
    {
        return new self($donnees);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->donnees;
    }

    public function disponible(): bool
    {
        return (bool) ($this->donnees['disponible'] ?? false);
    }

    public static function libelleVerdict(?string $verdict): string
    {
        return match ($verdict) {
            PolitiqueDeuxiemeChance::VERDICT_REPARABLE => 'Deuxième chance',
            PolitiqueDeuxiemeChance::VERDICT_INUTILE => 'Don plutôt que réparation',
            PolitiqueDeuxiemeChance::VERDICT_IRREPARABLE => 'Réparation déconseillée',
            PolitiqueDeuxiemeChance::VERDICT_HORS_SUJET => 'Photo non reconnue',
            default => 'Analyse photo',
        };
    }

    public static function libelleRecommandation(?string $recommandation): ?string
    {
        return match ($recommandation) {
            Vetement::ACTION_REPARATION => 'Réparer en atelier',
            Vetement::ACTION_DON => 'Proposer un don',
            'recyclage' => 'Orienter vers le recyclage',
            default => null,
        };
    }

    public static function tonVerdict(?string $verdict): string
    {
        return match ($verdict) {
            PolitiqueDeuxiemeChance::VERDICT_REPARABLE => 'green',
            PolitiqueDeuxiemeChance::VERDICT_INUTILE => 'blue',
            PolitiqueDeuxiemeChance::VERDICT_IRREPARABLE => 'orange',
            default => 'gray',
        };
    }
}
