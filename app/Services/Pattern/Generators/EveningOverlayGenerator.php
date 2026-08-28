<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شب با رودامنِ باز از جلو. */
class EveningOverlayGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_overlay';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_overlay',
            'title' => 'لباس شب رودامن‌دار',
            'skirt' => 'skirt_overlay',
            'length' => 110,
            'skirt_params' => ['overlay_style' => 'open_front', 'overlay_length' => 30, 'overlay_flare' => 40],
            'neckline' => 'v',
        ];
    }
}
