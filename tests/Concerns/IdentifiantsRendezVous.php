<?php

namespace Tests\Concerns;

/**
 * Identifiants fixes partagés par les tests du module RendezVous.
 *
 * Portés par une interface (et non par le trait FabriqueRendezVous) car les
 * constantes de trait ne sont disponibles qu'à partir de PHP 8.2, alors que le
 * projet déclare "php": "^8.0" dans composer.json.
 */
interface IdentifiantsRendezVous
{
    public const ID_CITOYEN = '652f000000000000000000c1';

    public const ID_COMPTE_ATELIER = '652f000000000000000000c2';

    public const ID_ATELIER = '652f000000000000000000a1';

    public const ID_AUTRE_ATELIER = '652f000000000000000000a2';

    public const ID_SERVICE = '652f000000000000000000b1';

    public const ID_SERVICE_2 = '652f000000000000000000b2';

    public const ID_SERVICE_ETRANGER = '652f000000000000000000b9';

    public const ID_VETEMENT = '652f000000000000000000e1';

    public const ID_RDV = '652f000000000000000000f1';

    public const NOM_PIEGE = 'Couture <script>alert(1)</script> Atelier';
}
