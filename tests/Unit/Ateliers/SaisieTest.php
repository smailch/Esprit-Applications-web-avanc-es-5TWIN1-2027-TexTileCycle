<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Support\Saisie;
use PHPUnit\Framework\TestCase;

class SaisieTest extends TestCase
{
    public function test_decimal_remplace_la_virgule_par_un_point(): void
    {
        $this->assertSame('12.5', Saisie::decimal('12,5'));
        $this->assertSame('-36.8065', Saisie::decimal(' -36,8065 '));
    }

    public function test_decimal_laisse_les_autres_valeurs_pour_la_validation(): void
    {
        $this->assertSame('12.5', Saisie::decimal('12.5'));
        $this->assertSame('1,2,3', Saisie::decimal('1,2,3'));
        $this->assertSame('abc', Saisie::decimal('abc'));
        $this->assertNull(Saisie::decimal(null));
        $this->assertSame(['12,5'], Saisie::decimal(['12,5']));
    }

    public function test_heure_complete_les_ecritures_courantes(): void
    {
        $this->assertSame('09:00', Saisie::heure('9:00'));
        $this->assertSame('09:30', Saisie::heure('9h30'));
        $this->assertSame('14:00', Saisie::heure('14 H 00'));
        $this->assertSame('08:15', Saisie::heure('08:15'));
    }

    public function test_heure_laisse_les_saisies_invalides_pour_la_regle(): void
    {
        $this->assertSame('9h', Saisie::heure('9h'));
        $this->assertSame('25:00', Saisie::heure('25:00'));
        $this->assertSame('123:00', Saisie::heure('123:00'));
        $this->assertSame('', Saisie::heure(''));
        $this->assertSame('midi', Saisie::heure('midi'));
    }
}
