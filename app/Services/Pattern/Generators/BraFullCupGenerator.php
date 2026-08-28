<?php

namespace App\Services\Pattern\Generators;

/** سوتینِ فنجانِ کامل: کاپِ پهن‌تر و سینه‌بندِ بلندتر؛ نگه‌داریِ بیشتر بدون فنر. */
class BraFullCupGenerator extends BraSoftGenerator
{
    public static function key(): string
    {
        return 'bra_full_cup';
    }

    public function label(): string
    {
        return 'سوتین فنجان کامل';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'cup_ratio' => 0.27,
            'band_height' => 5.5,
            'hook_rows' => 3,
            'strap_width' => 2.0,
            'band_ratio' => 0.8,
        ]);
    }
}
