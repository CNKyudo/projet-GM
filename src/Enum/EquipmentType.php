<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentType: string
{
    case YUMI = 'yumi';
    case GLOVE = 'glove';
    case MAKIWARA = 'makiwara';
    case SUPPORT_MAKIWARA = 'support_makiwara';
    case YUMITATE = 'yumitate';

    public function label(): string
    {
        return match($this) {
            self::YUMI => 'equipment.type.yumi',
            self::GLOVE => 'equipment.type.glove',
            self::MAKIWARA => 'equipment.type.makiwara',
            self::SUPPORT_MAKIWARA => 'equipment.type.support_makiwara',
            self::YUMITATE => 'equipment.type.yumitate',
        };
    }
}
