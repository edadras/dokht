<?php

namespace App\Services\Pattern\Generators;

/** بارانیِ مردانه: دوطرفه‌دکمه، کمربنددار و با سردوشی؛ همان ترنچ با بلوکِ مردانه. */
class MensTrenchGenerator extends CoatTrenchGenerator
{
    public static function key(): string
    {
        return 'mens_trench';
    }

    public function label(): string
    {
        return 'بارانی مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.5,
            'armhole_depth_extra' => 6.0,
        ]);
    }
}
