<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شب با دامنِ کلوشِ کامل. */
class EveningCircleGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_circle';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_circle',
            'title' => 'لباس شب دامن کلوش',
            'skirt' => 'skirt_circle_full',
            'length' => 108,
            'neckline' => 'halter',
        ];
    }
}
