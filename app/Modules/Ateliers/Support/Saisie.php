<?php

namespace App\Modules\Ateliers\Support;

/**
 * Normalisation des saisies avant validation. Les champs du module sont des champs texte
 * (aucun contrôle de saisie HTML) : on accepte les écritures courantes et on les ramène
 * au format attendu par les règles. Toute autre valeur est rendue telle quelle pour que
 * la validation la signale.
 */
class Saisie
{
    /**
     * « 12,5 » -> « 12.5 ».
     */
    public static function decimal(mixed $valeur): mixed
    {
        if (is_string($valeur) && preg_match('/^\s*[+-]?\d+,\d+\s*$/', $valeur)) {
            return str_replace(',', '.', trim($valeur));
        }

        return $valeur;
    }

    /**
     * « 9:00 », « 9h00 » ou « 09 h 00 » -> « 09:00 ».
     */
    public static function heure(mixed $valeur): mixed
    {
        if (is_string($valeur) && preg_match('/^\s*(\d{1,2})\s*[:hH]\s*(\d{2})\s*$/', $valeur, $morceaux)) {
            return sprintf('%02d:%s', (int) $morceaux[1], $morceaux[2]);
        }

        return $valeur;
    }
}
