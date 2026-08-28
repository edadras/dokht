<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ پارچه‌ایِ اداری تا بالای زانو با پیلیِ جلو. */
class ShortsTailoredGenerator extends ShortsBermudaGenerator
{
    public static function key(): string
    {
        return 'shorts_tailored';
    }

    public function label(): string
    {
        return 'شلوارک پارچه‌ای';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'leg_length' => 30,
            'thigh_ease' => 13,
            'hem_ease' => 12,
        ]);
    }
}
