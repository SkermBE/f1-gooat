<?php

namespace modules\f1gooat;

class PointsCalculator
{
    /**
     * Points map: difference from P10 → points earned.
     * Used by controllers, jobs, and templates.
     */
    public const POINTS_MAP = [
        0 => 25,  // Perfect P10
        1 => 18,  // P9 or P11
        2 => 15,  // P8 or P12
        3 => 12,  // P7 or P13
        4 => 10,  // P6 or P14
        5 => 8,   // P5 or P15
        6 => 6,   // P4 or P16
        7 => 4,   // P3 or P17
        8 => 2,   // P2 or P18
        9 => 1,   // P1 or P19
    ];

    public static function calculate(int $actualPosition): int
    {
        $difference = abs($actualPosition - 10);
        return self::POINTS_MAP[$difference] ?? 0;
    }
}
