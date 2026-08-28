<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ بشکه‌ای: از ران گشاد، از زانو قوس می‌گیرد و در دمِ پا جمع می‌شود. */
class PantsBarrelGenerator extends PantsTaperedGenerator
{
    public static function key(): string
    {
        return 'pants_barrel';
    }

    public function label(): string
    {
        return 'شلوار بشکه‌ای';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 22,
            'knee_ease' => 20,
            'hem_ease' => 6,
        ]);
    }
}
