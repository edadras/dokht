<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ بندیِ میدیِ روزمره. */
class DressCamiMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_cami_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_cami_midi',
            'title' => 'پیراهن بندی میدی روزانه',
            'form' => 'waisted',
            'skirt' => 'skirt_a_line',
            'skirt_length' => 70,
            'skirt_params' => ['flare' => 24],
            'sleeve' => 'none',
            'block' => ['neck_width_extra' => 5, 'front_neck_depth_extra' => 7, 'back_neck_depth' => 6]
        ];
    }
}
