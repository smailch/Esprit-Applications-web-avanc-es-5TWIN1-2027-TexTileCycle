<?php

namespace App\Modules\Ateliers\Exceptions;

use RuntimeException;

/**
 * Le diagnostic par photo n'a pas pu aboutir. Le message est destiné au citoyen (en français, sans détail technique).
 */
class DiagnosticIndisponibleException extends RuntimeException
{
    public const MESSAGE_INDISPONIBLE = 'Le diagnostic par photo est indisponible pour le moment. Vous pouvez chercher un atelier par service ou par ville.';

    public static function indisponible(): self
    {
        return new self(self::MESSAGE_INDISPONIBLE);
    }

    public static function photoRefusee(string $raison): self
    {
        return new self("La photo n'a pas pu être analysée : {$raison}");
    }

    public static function catalogueVide(): self
    {
        return new self("Aucun atelier ne propose encore de service : le diagnostic ne peut rien recommander pour l'instant.");
    }
}
