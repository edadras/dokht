<?php

namespace App\Services\Pattern\Generators;

/**
 * جلابیه.
 *
 * پیراهنِ بلندِ گشادِ عربی با یقهٔ گرد و چاکِ کوتاهِ جلو. تفاوتش با ثوبِ مردانه
 * در گشادیِ بیشتر و نبودِ یقهٔ ایستاده است؛ تفاوتش با عبایا در این است که
 * جلابیه خودِ لباس است، نه روپوش.
 */
class TradJalabiyaGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_jalabiya';
    }

    public function label(): string
    {
        return 'جلابیه';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'jalabiya-',
            'title' => 'جلابیه',
            'length' => 140,
            'sleeve' => 60,
            'cuff_flare' => 8,
            'fullness' => 1.0,
            'flare' => 24,
            'slit' => 16,
            'shape' => 'trapeze',
            'notes' => [
                'پارچهٔ نازک و لبهٔ پایینِ پُر؛ لباس باید در گرما هوا بگیرد.',
            ],
        ];
    }
}
