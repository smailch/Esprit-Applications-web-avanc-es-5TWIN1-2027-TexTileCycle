<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Rules\HorairesRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HorairesRuleTest extends TestCase
{
    private function semaine(array $surcharges = []): array
    {
        $journee = [['09:00', '12:00'], ['14:00', '18:00']];

        return array_merge([
            'lundi' => $journee,
            'mardi' => $journee,
            'mercredi' => $journee,
            'jeudi' => $journee,
            'vendredi' => $journee,
            'samedi' => [['09:00', '13:00']],
            'dimanche' => [],
        ], $surcharges);
    }

    private function erreurs(mixed $horaires): array
    {
        return Validator::make(['horaires' => $horaires], ['horaires' => [new HorairesRule()]])
            ->errors()
            ->get('horaires');
    }

    public function test_semaine_complete_valide(): void
    {
        $this->assertSame([], $this->erreurs($this->semaine()));
    }

    public function test_jour_ferme_null_ou_chaine_vide_accepte(): void
    {
        $this->assertSame([], $this->erreurs($this->semaine(['dimanche' => null, 'samedi' => ''])));
    }

    public function test_bornes_00_00_et_23_59_acceptees(): void
    {
        $this->assertSame([], $this->erreurs($this->semaine(['lundi' => [['00:00', '23:59']]])));
    }

    public function test_refuse_une_valeur_qui_n_est_pas_un_tableau(): void
    {
        $this->assertSame(['Les horaires doivent être un tableau indexé par jour.'], $this->erreurs('lundi 9h-18h'));
    }

    public function test_refuse_un_jour_manquant(): void
    {
        $horaires = $this->semaine();
        unset($horaires['dimanche']);

        $this->assertStringContainsString('Manquant(s) : dimanche', $this->erreurs($horaires)[0]);
    }

    public function test_refuse_un_jour_en_majuscules_ou_inconnu(): void
    {
        $horaires = $this->semaine();
        unset($horaires['lundi']);
        $horaires['Lundi'] = [['09:00', '12:00']];

        $this->assertStringContainsString('Jour(s) inconnu(s)', $this->erreurs($horaires)[0]);
    }

    /**
     * @dataProvider plagesInvalides
     */
    public function test_refuse_les_plages_invalides(mixed $lundi, string $attendu): void
    {
        $erreurs = $this->erreurs($this->semaine(['lundi' => $lundi]));

        $this->assertCount(1, $erreurs);
        $this->assertStringContainsString($attendu, $erreurs[0]);
    }

    public static function plagesInvalides(): array
    {
        return [
            'jour non liste' => [['matin' => ['09:00', '12:00']], 'doivent être une liste de plages'],
            'jour scalaire' => ['09:00-12:00', 'doivent être une liste de plages'],
            'plage à 1 valeur' => [[['09:00']], 'exactement une heure de début et une heure de fin'],
            'plage à 3 valeurs' => [[['09:00', '12:00', '14:00']], 'exactement une heure de début'],
            'heure sans zéro' => [[['9:00', '12:00']], 'format HH:MM'],
            'heure 24:00' => [[['09:00', '24:00']], 'format HH:MM'],
            'minutes 60' => [[['09:60', '12:00']], 'format HH:MM'],
            'heure non texte' => [[[900, 1200]], 'format HH:MM'],
            'début après fin' => [[['18:00', '09:00']], 'doit précéder'],
            'début égal fin' => [[['09:00', '09:00']], 'doit précéder'],
        ];
    }

    public function test_signale_le_numero_de_la_plage_fautive(): void
    {
        $erreurs = $this->erreurs($this->semaine(['mardi' => [['09:00', '12:00'], ['15:00', '14:00']]]));

        $this->assertStringContainsString('plage n°2 du mardi', $erreurs[0]);
    }
}
