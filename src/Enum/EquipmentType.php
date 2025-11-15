<?php

namespace App\Enum;

enum EquipmentType: string
{
    case YUMI = 'yumi';
    case GLOVE = 'glove';

        public function label(): string
    {
        return match($this) {
            self::YUMI => 'equipment.type.yumi',
            self::GLOVE => 'equipment.type.glove',
        };
    }
}
