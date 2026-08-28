<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروس با دامنِ کلوشِ کامل و بالاتنهٔ هالتر. */
class BridalCircleGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_circle';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_circle',
            'title' => 'لباس عروس دامن کلوش',
            'skirt' => 'skirt_circle_full',
            'length' => 114,
            'neckline' => 'halter',
        ];
    }
}
