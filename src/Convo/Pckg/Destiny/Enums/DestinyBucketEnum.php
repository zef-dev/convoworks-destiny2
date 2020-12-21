<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Enums;

abstract class DestinyBucketEnum
{
    const EQUIPPABLE_GEAR = [
        self::BUCKET_KINETIC_WEAPONS,
        self::BUCKET_ENERGY_WEAPONS,
        self::BUCKET_POWER_WEAPONS,

        self::BUCKET_HELMETS,
        self::BUCKET_GAUNTLETS,
        self::BUCKET_CHEST_ARMOR,
        self::BUCKET_GREAVES,
        self::BUCKET_CLASS_ITEMS
    ];

    const BUCKET_KINETIC_WEAPONS = 1498876634;
    const BUCKET_ENERGY_WEAPONS = 2465295065;
    const BUCKET_POWER_WEAPONS = 953998645;

    const BUCKET_HELMETS = 3448274439;
    const BUCKET_GAUNTLETS = 3551918588;
    const BUCKET_CHEST_ARMOR = 14239492;
    const BUCKET_GREAVES = 20886954;
    const BUCKET_CLASS_ITEMS = 1585787867;

    const BUCKET_VAULT = 138197802;
}