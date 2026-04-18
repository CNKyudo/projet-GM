<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentLevel: string
{
    /** Équipement appartenant à un club spécifique. */
    case CLUB = 'club';

    /** Équipement appartenant à une région (CTK). */
    case REGIONAL = 'regional';

    /** Équipement appartenant à la fédération nationale. */
    case NATIONAL = 'national';

    public function label(): string
    {
        return match ($this) {
            self::CLUB => 'Club',
            self::REGIONAL => 'Régional',
            self::NATIONAL => 'National',
        };
    }
}
