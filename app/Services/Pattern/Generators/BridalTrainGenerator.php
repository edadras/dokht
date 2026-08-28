<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ دنباله‌دارِ بلند (کلیسایی). */
class BridalTrainGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_train';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_train',
            'title' => 'لباس عروس دنباله‌دار',
            'skirt' => 'skirt_train',
            'length' => 118,
            'skirt_params' => ['train' => 120, 'hem_flare' => 60, 'bustle_loop' => true],
            'neckline' => 'sweetheart',
            'notes' => ['حلقهٔ جمع‌کنِ دنباله (باسل) روی کمرِ پشت دوخته می‌شود تا برای جشن بالا زده شود.'],
        ];
    }
}
