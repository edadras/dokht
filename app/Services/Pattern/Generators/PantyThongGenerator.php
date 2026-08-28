<?php

namespace App\Services\Pattern\Generators;

/** تانگ: درزِ پهلو تقریباً صفر و پوششِ پشت کمینه؛ نوارِ فاق همچنان پنبه‌ای و جداست. */
class PantyThongGenerator extends PantyBriefGenerator
{
    public static function key(): string
    {
        return 'panty_thong';
    }

    public function label(): string
    {
        return 'شورت نخی (تانگ)';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise_drop' => 8,
            'side_seam' => 1.5,
            'coverage' => 'cheeky',
            'seat' => 0,
            'gusset' => 6.5,
        ]);
    }
}
