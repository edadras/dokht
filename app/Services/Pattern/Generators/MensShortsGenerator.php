<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ مردانه تا بالای زانو، پای راسته. */
class MensShortsGenerator extends MensPantsBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shorts';
    }

    protected function mens(): array
    {
        return [
            'prefix' => 'mens_shorts',
            'title' => 'شلوارک مردانه',
            'use' => 'daily',
            'rise' => 'mid',
            'thigh_ease' => 13,
            'knee_ease' => 12,
            'hem_ease' => 13,
            'leg_length' => 26,
            'front_waist' => 'none',
            'extra' => [
                'leg_length' => [
                    'label' => 'قد پا از خط فاق', 'min' => 10, 'max' => 45, 'step' => 1,
                    'default' => 26, 'unit' => 'سانتی‌متر',
                ],
            ],
        ];
    }
}
