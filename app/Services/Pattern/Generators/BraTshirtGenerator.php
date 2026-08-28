<?php

namespace App\Services\Pattern\Generators;

/** سوتینِ تی‌شرتی: کاپِ صاف و بی‌درز که زیرِ لباسِ نازک دیده نشود. */
class BraTshirtGenerator extends BraSoftGenerator
{
    public static function key(): string
    {
        return 'bra_tshirt';
    }

    public function label(): string
    {
        return 'سوتین بی‌درز (تی‌شرتی)';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'cup_ratio' => 0.22,
            'band_height' => 3.5,
            'strap_width' => 1.4,
            'cup_lining' => true,
        ]);
    }
}
