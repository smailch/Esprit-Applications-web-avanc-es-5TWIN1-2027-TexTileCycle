<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Models\Avis;
use App\Modules\Ateliers\Services\AvisService;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class AvisTest extends TestCase
{
    public function test_resume_calcule_moyenne_total_et_repartition(): void
    {
        $resume = AvisService::resumeDe(collect([5, 4, 4, '3', 5]));

        $this->assertSame(4.2, $resume['moyenne']);
        $this->assertSame(5, $resume['total']);
        $this->assertSame([5 => 2, 4 => 2, 3 => 1, 2 => 0, 1 => 0], $resume['repartition']);
    }

    public function test_resume_ignore_les_notes_hors_echelle_et_gere_l_absence_d_avis(): void
    {
        $this->assertSame(['moyenne' => 0.0, 'total' => 0, 'repartition' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]], AvisService::resumeDe(collect()));
        $this->assertSame(2, AvisService::resumeDe(collect([0, 6, 2, 2, null]))['total']);
    }

    public function test_le_nom_affiche_protege_l_identite_du_client(): void
    {
        $avis = fn (?string $nom) => (new Avis())->setRelation('auteur', $nom === null ? null : new User(['name' => $nom]));

        $this->assertSame('Amira B.', $avis('amira ben salah')->auteurAffiche());
        $this->assertSame('Élodie M.', $avis('ÉLODIE  martin')->auteurAffiche());
        $this->assertSame('Karim', $avis('karim')->auteurAffiche());
        $this->assertSame('Client', $avis('   ')->auteurAffiche());
        $this->assertSame('Client', $avis(null)->auteurAffiche());
        $this->assertSame('A', $avis('amira ben salah')->initiale());
    }

    public function test_libelle_et_modification(): void
    {
        $avis = new Avis(['note' => 4]);
        $avis->created_at = Carbon::parse('2026-10-01 10:00');
        $avis->updated_at = Carbon::parse('2026-10-01 10:00:30');

        $this->assertSame('Très bien', $avis->libelle());
        $this->assertFalse($avis->estModifie());

        $avis->updated_at = Carbon::parse('2026-10-03 09:00');
        $this->assertTrue($avis->estModifie());
    }
}
