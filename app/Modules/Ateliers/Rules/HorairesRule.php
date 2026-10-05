<?php

namespace App\Modules\Ateliers\Rules;

use App\Modules\Ateliers\Models\Atelier;
use Illuminate\Contracts\Validation\InvokableRule;

/**
 * Format attendu : ['lundi' => [['09:00', '12:00'], ['14:00', '18:00']], ..., 'dimanche' => []].
 * Les 7 jours sont obligatoires ; un jour fermé vaut [] ou null (un formulaire HTML
 * ne peut pas envoyer de tableau vide : un champ caché horaires[dimanche]="" suffit).
 */
class HorairesRule implements InvokableRule
{
    private const FORMAT_HEURE = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __invoke($attribute, $value, $fail): void
    {
        if (! is_array($value)) {
            $fail('Les horaires doivent être un tableau indexé par jour.');

            return;
        }

        $inconnus = array_diff(array_map('strval', array_keys($value)), Atelier::JOURS);

        if ($inconnus !== []) {
            $fail('Jour(s) inconnu(s) dans les horaires : '.implode(', ', $inconnus).'. Utilisez les jours en minuscules (lundi … dimanche).');

            return;
        }

        $manquants = array_diff(Atelier::JOURS, array_keys($value));

        if ($manquants !== []) {
            $fail('Les horaires doivent contenir les 7 jours. Manquant(s) : '.implode(', ', $manquants).'.');

            return;
        }

        foreach (Atelier::JOURS as $jour) {
            $plages = $value[$jour];

            if ($plages === null || $plages === '') {
                continue;
            }

            if (! is_array($plages) || ! array_is_list($plages)) {
                $fail("Les horaires du {$jour} doivent être une liste de plages [début, fin].");

                continue;
            }

            foreach ($plages as $index => $plage) {
                $numero = $index + 1;

                if (! is_array($plage) || ! array_is_list($plage) || count($plage) !== 2) {
                    $fail("La plage n°{$numero} du {$jour} doit contenir exactement une heure de début et une heure de fin.");

                    continue;
                }

                [$debut, $fin] = $plage;

                if (! is_string($debut) || ! is_string($fin)
                    || ! preg_match(self::FORMAT_HEURE, $debut) || ! preg_match(self::FORMAT_HEURE, $fin)) {
                    $fail("La plage n°{$numero} du {$jour} doit utiliser le format HH:MM (ex. 09:00).");

                    continue;
                }

                if ($debut >= $fin) {
                    $fail("La plage n°{$numero} du {$jour} : l'heure de début ({$debut}) doit précéder l'heure de fin ({$fin}).");
                }
            }
        }
    }
}
