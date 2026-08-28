<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ ترک‌دار: هشت ترک که از باسن باز می‌شوند. */
class BridalGoredGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_gored';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_gored',
            'title' => 'لباس عروس ترک‌دار',
            'skirt' => 'skirt_gored',
            'length' => 118,
            'skirt_params' => ['panels' => 8, 'flare' => 55, 'flare_from' => 'hip'],
            'neckline' => 'v',
        ];
    }
}
