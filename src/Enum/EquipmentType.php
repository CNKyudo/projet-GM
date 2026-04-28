<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentType: string
{
    case YUMI = 'yumi';
    case GLOVE = 'glove';
    case MAKIWARA = 'makiwara';

    public function label(): string
    {
        return match($this) {
            self::YUMI => 'equipment.type.yumi',
            self::GLOVE => 'equipment.type.glove',
            self::MAKIWARA => 'equipment.type.makiwara',
        };
    }
}
