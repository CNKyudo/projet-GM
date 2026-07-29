<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentType: string
{
    case AZUCHI = 'azuchi';
    case GAKE = 'gake';
    case MAKIWARA = 'makiwara';
    case MAKU = 'maku';
    case MUNEATE = 'muneate';
    case SHITAGAKE = 'shitagake';
    case SUPPORT_MAKIWARA = 'support_makiwara';
    case YATATE = 'yatate';
    case YUMI = 'yumi';
    case YUMITATE = 'yumitate';

    public function label(): string
    {
        return match($this) {
            self::AZUCHI => 'equipment.type.azuchi',
            self::GAKE => 'equipment.type.gake',
            self::MAKIWARA => 'equipment.type.makiwara',
            self::MAKU => 'equipment.type.maku',
            self::MUNEATE => 'equipment.type.muneate',
            self::SHITAGAKE => 'equipment.type.shitagake',
            self::SUPPORT_MAKIWARA => 'equipment.type.support_makiwara',
            self::YATATE => 'equipment.type.yatate',
            self::YUMI => 'equipment.type.yumi',
            self::YUMITATE => 'equipment.type.yumitate',
        };
    }
}
