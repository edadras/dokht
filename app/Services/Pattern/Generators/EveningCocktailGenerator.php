<?php

namespace App\Services\Pattern\Generators;

/** لباسِ کوتاهِ مجلسی تا بالای زانو. */
class EveningCocktailGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_cocktail';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_cocktail',
            'title' => 'لباس کوتاه مجلسی',
            'skirt' => 'skirt_a_line',
            'length' => 52,
            'skirt_params' => ['flare' => 'skirt_flare'],
            'neckline' => 'scoop',
            'fit' => 'fitted',
            'extra' => [
                'skirt_flare' => [
                    'label' => 'گشادی دم دامن', 'min' => 8, 'max' => 60, 'step' => 2,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
            ],
        ];
    }
}
