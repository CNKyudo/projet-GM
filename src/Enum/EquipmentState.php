<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentState: string
{
    case NEW = 'new';
    case USED = 'used';
    case TO_REPAIR = 'to_repair';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'equipment.state.new',
            self::USED => 'equipment.state.used',
            self::TO_REPAIR => 'equipment.state.to_repair',
        };
    }
}
