<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شب با دامنِ پیلیِ ریز. */
class EveningPleatedGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_pleated';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_pleated',
            'title' => 'لباس شب پیلی‌دار',
            'skirt' => 'skirt_pleat_knife',
            'length' => 110,
            'neckline' => 'straight',
        ];
    }
}
