<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ کشبافِ میدیِ چسبان. */
class DressKnitMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_knit_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_knit_midi',
            'title' => 'پیراهن کشباف میدی',
            'form' => 'onepiece',
            'shape' => 'fitted',
            'length' => 66,
            'hem_flare' => 2,
            'bust_dart' => false,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 58,
            'fit' => 'fitted',
            'ease' => ['bust' => 1.0, 'waist' => 0.0, 'hip' => 1.0],
            'notes' => ['فقط با پارچهٔ کشی؛ بدونِ بست از باسن رد می‌شود چون پارچه باز می‌شود.']
        ];
    }
}
