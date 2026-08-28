<?php

namespace App\Services\Pattern\Generators;

/** جینِ دم‌گشاد: از زانو به پایین باز می‌شود. */
class JeansFlareGenerator extends PantsFlareGenerator
{
    public static function key(): string
    {
        return 'jeans_flare';
    }

    public function label(): string
    {
        return 'شلوار جین دم‌گشاد';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 6,
            'knee_ease' => 2,
            'hem_ease' => 26,
        ]);
    }
}
