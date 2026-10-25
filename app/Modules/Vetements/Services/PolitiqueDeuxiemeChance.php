<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Vetements\Models\Vetement;

/**
 * Transforme la lecture brute du modèle de vision en verdict de réparation.
 * La gravité l'emporte sur un avis trop optimiste du modèle.
 */
class PolitiqueDeuxiemeChance
{
    public const VERDICT_REPARABLE = 'reparable';

    public const VERDICT_INUTILE = 'inutile';

    public const VERDICT_IRREPARABLE = 'irreparable';

    public const VERDICT_HORS_SUJET = 'hors_sujet';

    /**
     * @param  array<string, mixed>  $brut
     * @return array<string, mixed>
     */
    public function appliquer(array $brut): array
    {
        $type = $this->type($brut['type'] ?? null);
        $etat = $this->etat($brut['etat'] ?? null);
        $gravite = $this->gravite(isset($brut['gravite']) ? (string) $brut['gravite'] : null, $etat);
        $defauts = $this->defauts($brut['defauts'] ?? []);
        $reparable = $this->reparable($brut, $etat);
        $estVetement = $this->estVetement($brut, $type);
        $confiance = max(0, min(100, (int) ($brut['confiance'] ?? 60)));

        if (! $estVetement) {
            $verdict = self::VERDICT_HORS_SUJET;
        } elseif ($gravite === 'severe') {
            $verdict = self::VERDICT_IRREPARABLE;
        } elseif ($etat === 'Abîmé' && ! $reparable) {
            $verdict = self::VERDICT_IRREPARABLE;
        } elseif (in_array($etat, ['Excellent', 'Bon état'], true) && in_array($gravite, ['aucune', 'legere'], true) && ! $reparable) {
            $verdict = self::VERDICT_INUTILE;
        } elseif (in_array($etat, ['Excellent', 'Bon état'], true) && in_array($gravite, ['aucune', 'legere'], true) && $defauts === []) {
            $verdict = self::VERDICT_INUTILE;
        } elseif ($reparable || $etat === 'À réparer' || ($etat === 'Abîmé' && $gravite === 'moyenne')) {
            $verdict = self::VERDICT_REPARABLE;
        } elseif ($etat === 'Abîmé') {
            $verdict = self::VERDICT_IRREPARABLE;
        } else {
            $verdict = self::VERDICT_INUTILE;
        }

        $deuxiemeChance = match ($verdict) {
            self::VERDICT_REPARABLE => true,
            self::VERDICT_HORS_SUJET => null,
            default => false,
        };

        $recommandation = match ($verdict) {
            self::VERDICT_REPARABLE => Vetement::ACTION_REPARATION,
            self::VERDICT_INUTILE => Vetement::ACTION_DON,
            self::VERDICT_IRREPARABLE => 'recyclage',
            default => null,
        };

        $resume = $this->resume($verdict, $defauts, $confiance);

        return [
            'est_vetement' => $estVetement,
            'type' => $estVetement ? $type : null,
            'matiere' => $estVetement ? $this->matiere($brut['matiere'] ?? null) : null,
            'etat' => $estVetement ? $etat : null,
            'defauts' => $estVetement ? $defauts : [],
            'gravite' => $estVetement ? $gravite : null,
            'verdict' => $verdict,
            'deuxieme_chance' => $deuxiemeChance,
            'recommandation' => $recommandation,
            'confiance' => $confiance,
            'resume' => $resume,
            'raisons' => $this->raisons($verdict, $defauts),
        ];
    }

    /**
     * @param  array<string, mixed>  $brut
     */
    private function estVetement(array $brut, string $type): bool
    {
        if (array_key_exists('est_vetement', $brut)) {
            return (bool) filter_var($brut['est_vetement'], FILTER_VALIDATE_BOOLEAN);
        }

        return $type !== 'Autre';
    }

    /**
     * @param  array<string, mixed>  $brut
     */
    private function reparable(array $brut, string $etat): bool
    {
        if (array_key_exists('reparable', $brut)) {
            return (bool) filter_var($brut['reparable'], FILTER_VALIDATE_BOOLEAN);
        }

        return $etat === 'À réparer';
    }

    private function type(mixed $valeur): string
    {
        $texte = trim((string) $valeur);
        foreach (Vetement::TYPES as $type) {
            if (mb_strtolower($texte) === mb_strtolower($type)) {
                return $type;
            }
        }

        $cle = $this->sansAccent($texte);
        $synonymes = [
            't-shirt' => 'T-shirt',
            'tshirt' => 'T-shirt',
            'tee shirt' => 'T-shirt',
            'manteau' => 'Manteau',
            'doudoune' => 'Manteau',
            'chemise' => 'Chemise',
            'pantalon' => 'Pantalon',
            'veste' => 'Veste',
            'blouson' => 'Veste',
            'robe' => 'Robe',
            'jupe' => 'Jupe',
            'pull' => 'Pull',
            'sweat' => 'Pull',
            'gilet' => 'Pull',
            'jean' => 'Jean',
            'short' => 'Short',
        ];

        foreach ($synonymes as $mot => $type) {
            if (str_contains($cle, $mot) && in_array($type, Vetement::TYPES, true)) {
                return $type;
            }
        }

        return 'Autre';
    }

    private function etat(mixed $valeur): string
    {
        $texte = trim((string) $valeur);
        if (in_array($texte, Vetement::ETATS, true)) {
            return $texte;
        }

        $cle = $this->sansAccent($texte);

        return match (true) {
            str_contains($cle, 'excellent') => 'Excellent',
            str_contains($cle, 'repar') => 'À réparer',
            str_contains($cle, 'abim') => 'Abîmé',
            str_contains($cle, 'bon') => 'Bon état',
            default => 'Bon état',
        };
    }

    private function gravite(?string $valeur, string $etat): string
    {
        $cle = $this->sansAccent((string) $valeur);
        $map = [
            'aucune' => 'aucune',
            'aucun' => 'aucune',
            'legere' => 'legere',
            'leger' => 'legere',
            'faible' => 'legere',
            'moyenne' => 'moyenne',
            'moyen' => 'moyenne',
            'severe' => 'severe',
            'grave' => 'severe',
        ];

        if (isset($map[$cle])) {
            return $map[$cle];
        }

        return match ($etat) {
            'À réparer' => 'moyenne',
            'Abîmé' => 'severe',
            default => 'aucune',
        };
    }

    private function matiere(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }

        $matieres = Vetement::MATERIALS;
        usort($matieres, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $cle = $this->sansAccent($texte);
        foreach ($matieres as $matiere) {
            if (mb_strtolower($texte) === mb_strtolower($matiere) || str_contains($cle, $this->sansAccent($matiere))) {
                return $matiere;
            }
        }

        if (str_contains($cle, 'jean') || str_contains($cle, 'denim')) {
            return 'Denim';
        }

        return mb_substr($texte, 0, 120);
    }

    /**
     * @return list<string>
     */
    private function defauts(mixed $valeur): array
    {
        if (! is_array($valeur)) {
            return [];
        }

        $liste = [];
        foreach ($valeur as $defaut) {
            if (! is_string($defaut)) {
                continue;
            }

            $defaut = trim(preg_replace('/\s+/', ' ', $defaut) ?? '');
            if ($defaut === '') {
                continue;
            }

            $liste[] = mb_substr($defaut, 0, 160);
            if (count($liste) >= 5) {
                break;
            }
        }

        return $liste;
    }

    /**
     * @param  list<string>  $defauts
     */
    private function resume(string $verdict, array $defauts, int $confiance): string
    {
        $details = $defauts === [] ? '' : ' '.implode(', ', $defauts).'.';

        $texte = match ($verdict) {
            self::VERDICT_REPARABLE => 'Deuxième chance : oui. Un atelier peut reprendre cette pièce.'.$details,
            self::VERDICT_INUTILE => 'Réparation non nécessaire : la pièce est encore en bon état. Un don lui offre une seconde vie.',
            self::VERDICT_IRREPARABLE => 'Deuxième chance : non. Une réparation ne rendrait pas la pièce portable de façon fiable.'.$details.' Le recyclage est plus adapté.',
            default => 'La photo ne permet pas de reconnaître un vêtement. Photographiez la pièce entière, à plat, avec une lumière uniforme.',
        };

        if ($verdict !== self::VERDICT_HORS_SUJET && $confiance < 45) {
            $texte .= ' Confiance limitée : vérifiez la photo avant de suivre cette recommandation.';
        }

        return trim(preg_replace('/\s+/', ' ', $texte) ?? $texte);
    }

    /**
     * @param  list<string>  $defauts
     * @return list<string>
     */
    private function raisons(string $verdict, array $defauts): array
    {
        $raisons = $defauts;

        $raisons[] = match ($verdict) {
            self::VERDICT_REPARABLE => 'Les défauts visibles restent localisés et corrigeables en atelier.',
            self::VERDICT_INUTILE => 'La pièce est encore portable sans retouche.',
            self::VERDICT_IRREPARABLE => 'Les dégâts sont trop étendus pour une réparation durable.',
            default => 'Aucun vêtement n\'est assez lisible sur la photo.',
        };

        return array_values(array_slice(array_unique($raisons), 0, 6));
    }

    private function sansAccent(string $valeur): string
    {
        $valeur = mb_strtolower(trim($valeur));

        return strtr($valeur, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }
}
