<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ شیپوری: از ران باز می‌شود. */
class BridalTrumpetGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_trumpet';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_trumpet',
            'title' => 'لباس عروس شیپوری',
            'skirt' => 'skirt_trumpet',
            'length' => 118,
            'neckline' => 'sweetheart',
            'fit' => 'fitted',
        ];
    }
}
