<?php

namespace App\Modules\Ateliers\Support;

use App\Modules\Ateliers\Models\Atelier;

/**
 * Pont entre l'éditeur d'horaires du back office et le format de HorairesRule.
 *
 * Formulaire : horaires[lundi][ferme] = 0|1, horaires[lundi][plages][i][debut|fin] = "HH:MM".
 * Stockage    : horaires[lundi] = [["09:00", "12:00"], ...] ; [] = fermé.
 */
class HorairesFormulaire
{
    public const PAR_DEFAUT = [
        'lundi' => [['09:00', '12:00'], ['14:00', '18:00']],
        'mardi' => [['09:00', '12:00'], ['14:00', '18:00']],
        'mercredi' => [['09:00', '12:00'], ['14:00', '18:00']],
        'jeudi' => [['09:00', '12:00'], ['14:00', '18:00']],
        'vendredi' => [['09:00', '12:00'], ['14:00', '18:00']],
        'samedi' => [['09:00', '13:00']],
        'dimanche' => [],
    ];

    /**
     * Saisie du formulaire -> format attendu par HorairesRule. Les jours déjà au format
     * stockage et les clés inconnues sont laissés tels quels pour que la règle les signale.
     */
    public static function depuisFormulaire(mixed $saisie): mixed
    {
        if (! is_array($saisie)) {
            return $saisie;
        }

        foreach ($saisie as $jour => $valeur) {
            if (! is_array($valeur) || ! (array_key_exists('ferme', $valeur) || array_key_exists('plages', $valeur))) {
                continue;
            }

            if (! empty($valeur['ferme'])) {
                $saisie[$jour] = [];

                continue;
            }

            $plages = [];

            foreach (is_array($valeur['plages'] ?? null) ? $valeur['plages'] : [] as $plage) {
                if (! is_array($plage)) {
                    $plages[] = $plage;

                    continue;
                }

                $debut = trim((string) ($plage['debut'] ?? ''));
                $fin = trim((string) ($plage['fin'] ?? ''));

                if ($debut === '' && $fin === '') {
                    continue;
                }

                $plages[] = [$debut, $fin];
            }

            $saisie[$jour] = $plages;
        }

        return $saisie;
    }

    /**
     * N'importe quelle source (old() au format formulaire ou stockage, ou valeur en base)
     * -> données d'affichage de l'éditeur : 7 jours, chacun ['ferme' => bool, 'plages' => [[debut, fin], ...]].
     *
     * @return array<string, array{ferme: bool, plages: list<array{0: string, 1: string}>}>
     */
    public static function pourFormulaire(mixed $source): array
    {
        $source = is_array($source) ? $source : self::PAR_DEFAUT;
        $jours = [];

        foreach (Atelier::JOURS as $jour) {
            $valeur = $source[$jour] ?? null;
            $plages = [];

            if (is_array($valeur) && (array_key_exists('ferme', $valeur) || array_key_exists('plages', $valeur))) {
                foreach (is_array($valeur['plages'] ?? null) ? $valeur['plages'] : [] as $plage) {
                    $debut = is_array($plage) ? trim((string) ($plage['debut'] ?? '')) : '';
                    $fin = is_array($plage) ? trim((string) ($plage['fin'] ?? '')) : '';

                    if ($debut !== '' || $fin !== '') {
                        $plages[] = [$debut, $fin];
                    }
                }

                $jours[$jour] = ['ferme' => ! empty($valeur['ferme']), 'plages' => $plages];

                continue;
            }

            foreach (is_array($valeur) ? $valeur : [] as $plage) {
                if (is_array($plage)) {
                    $plages[] = [(string) ($plage[0] ?? ''), (string) ($plage[1] ?? '')];
                }
            }

            $jours[$jour] = ['ferme' => $plages === [], 'plages' => $plages];
        }

        return $jours;
    }
}
