<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ کوتاه تا زیرِ زانو. */
class BridalShortGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_short';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_short',
            'title' => 'لباس عروس کوتاه',
            'skirt' => 'skirt_a_line',
            'length' => 60,
            'skirt_params' => ['flare' => 35],
            'neckline' => 'scoop',
        ];
    }
}
