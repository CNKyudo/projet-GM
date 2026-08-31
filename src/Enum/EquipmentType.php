<?php

declare(strict_types=1);

namespace App\Enum;

enum EquipmentType: string
{
    case AZUCHI = 'azuchi';
    case MAKIWARA = 'makiwara';
    case MAKU = 'maku';
    case MUNEATE = 'muneate';
    case SHITAGAKE = 'shitagake';
    case SUPPORT_MAKIWARA = 'support_makiwara';
    case TSURU = 'tsuru';
    case YATATE = 'yatate';
    case YUGAKE = 'yugake';
    case YUMI = 'yumi';
    case YUMITATE = 'yumitate';

    public function label(): string
    {
        return match($this) {
            self::AZUCHI => 'equipment.type.azuchi',
            self::MAKIWARA => 'equipment.type.makiwara',
            self::MAKU => 'equipment.type.maku',
            self::MUNEATE => 'equipment.type.muneate',
            self::SHITAGAKE => 'equipment.type.shitagake',
            self::SUPPORT_MAKIWARA => 'equipment.type.support_makiwara',
            self::TSURU => 'equipment.type.tsuru',
            self::YATATE => 'equipment.type.yatate',
            self::YUGAKE => 'equipment.type.yugake',
            self::YUMI => 'equipment.type.yumi',
            self::YUMITATE => 'equipment.type.yumitate',
        };
    }
}
