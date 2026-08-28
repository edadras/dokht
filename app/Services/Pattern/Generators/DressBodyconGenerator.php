<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ چسبان از پارچهٔ کشی. */
class DressBodyconGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_bodycon';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_bodycon',
            'title' => 'پیراهن چسبان',
            'form' => 'onepiece',
            'shape' => 'fitted',
            'length' => 42,
            'hem_flare' => 0,
            'waist_dart' => true,
            'fit' => 'fitted',
            'ease' => ['bust' => 2.0, 'waist' => 1.0, 'hip' => 2.0],
            'notes' => ['فقط با پارچه کشی؛ آزادی این درفت برای پارچه بافته کم است.'],
        ];
    }
}
