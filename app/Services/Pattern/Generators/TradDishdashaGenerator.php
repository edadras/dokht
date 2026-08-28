<?php

namespace App\Services\Pattern\Generators;

/**
 * دشداشه.
 *
 * پیراهنِ بلندِ مردانهٔ خلیج: راستِ راست، با یقهٔ ایستادهٔ کوتاه و چاکِ دکمه‌دارِ
 * جلو. برخلاف جلابیه، لبهٔ پایین باز نمی‌شود — همان پهنای سینه تا پایین می‌آید و
 * همین «راست بودن» شناسنامه‌اش است.
 */
class TradDishdashaGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_dishdasha';
    }

    public function label(): string
    {
        return 'دشداشه';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'dishdasha-',
            'title' => 'دشداشه',
            'length' => 142,
            'sleeve' => 62,
            'cuff_flare' => 0,
            'fullness' => 1.0,
            'flare' => 6,
            'slit' => 26,
            'collar' => 'stand',
            'shape' => 'straight',
            'notes' => [
                'تنه از زیر بغل تا پایین راست است؛ لبه باز نمی‌شود.',
                'جیبِ سینه و دو جیبِ پهلو روی درز، بخشی از لباسِ روزمره است.',
            ],
        ];
    }
}
