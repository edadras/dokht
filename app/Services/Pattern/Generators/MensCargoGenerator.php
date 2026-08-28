<?php

namespace App\Services\Pattern\Generators;

/** کارگویِ مردانه: رانِ گشاد، جیبِ بغل، دمِ پای جمع‌شونده. */
class MensCargoGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_cargo';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_cargo',
            'title' => 'شلوار کارگو مردانه',
            'use' => 'work',
            'rise' => 'mid',
            'thigh_ease' => 18,
            'knee_ease' => 15,
            'hem_ease' => 14,
            'hem_vs_knee' => -5.0,
            'front_waist' => 'none',
            'side_share' => 0.24,
            'notes' => ['جیبِ بغل روی رانِ پایین می‌نشیند؛ درِ آن هم‌اندازهٔ کیسه بریده می‌شود.'],
        ];
    }
}
