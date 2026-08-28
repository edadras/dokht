<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ بندیِ میدی از پارچهٔ ساتن، اریب‌بر. */
class DressSlipMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_slip_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_slip_midi',
            'title' => 'پیراهن بندی میدی',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 68,
            'hem_flare' => 5,
            'sleeve' => 'none',
            'fit' => 'fitted',
            'block' => ['neck_width_extra' => 4, 'front_neck_depth_extra' => 8, 'back_neck_depth' => 8],
            'notes' => ['روی اریب بریده می‌شود؛ پیش از دوختِ دم، بیست و چهار ساعت آویزان بماند تا کشش بنشیند.']
        ];
    }
}
