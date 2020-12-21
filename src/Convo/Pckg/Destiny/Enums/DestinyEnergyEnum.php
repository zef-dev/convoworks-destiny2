<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny\Enums;

abstract class DestinyEnergyEnum
{
    const ENERGY_TYPES = [
        0 => "Any",
        1 => "Arc",
        2 => "Solar", // Originally "Thermal"
        3 => "Void",
        4 => "Ghost",
        5 => "Subclass"
    ];
}