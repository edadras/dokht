<?php

namespace App\Services\Pattern\Generators;

/** جینِ جذبِ مردانه؛ فقط با پارچهٔ کشی بریده می‌شود. */
class MensJeansSlimGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_jeans_slim';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_jeans_slim',
            'title' => 'شلوار جین جذب مردانه',
            'use' => 'daily',
            'rise' => 'low',
            'rise_extra' => 0.5,
            'thigh_ease' => 3,
            'knee_ease' => 2,
            'hem_ease' => 2,
            'hem_vs_knee' => -2.0,
            'front_waist' => 'none',
            'side_share' => 0.3,
            'shape' => ['stretch' => 0.94],
            'notes' => ['بدونِ پارچهٔ کشی این اندازه‌ها تنگ درمی‌آید؛ کششِ ۹۴ درصد در درفت لحاظ شده است.'],
        ];
    }
}
