<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شبِ ترک‌دار: دامنِ چندترکه که از ران باز می‌شود. */
class EveningGoredGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_gored';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_gored',
            'title' => 'لباس شب ترک‌دار',
            'skirt' => 'skirt_gored',
            'length' => 112,
            'skirt_params' => ['panels' => 8, 'flare' => 40, 'flare_from' => 'hip'],
            'neckline' => 'v',
        ];
    }
}
