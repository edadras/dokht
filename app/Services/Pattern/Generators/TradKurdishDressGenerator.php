<?php

namespace App\Services\Pattern\Generators;

/**
 * کُراس — پیراهن بلند کردی.
 *
 * سایه‌اش را دو چیز می‌سازد و هیچ‌کدام برشِ تنه نیست: آستینِ بلندِ سه‌گوش که از
 * آرنج به پایین باز می‌شود (در بعضی ناحیه‌ها آن‌قدر بلند که پشتِ دست گره می‌خورد)،
 * و دامنِ چین‌دارِ پُر که از خط کمر پایین می‌آید. تنه خودش گشاد و بی‌ساسون است.
 */
class TradKurdishDressGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_kurdish_dress';
    }

    public function label(): string
    {
        return 'لباس کردی (کراس)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'kuras-',
            'title' => 'کُراس',
            'length' => 132,
            'sleeve' => 62,
            'cuff_flare' => 26,
            'fullness' => 2.2,
            'waist_drop' => 38,
            'slit' => 22,
            'neck_depth' => 3,
            'notes' => [
                'دمِ آستین باز و بلند است؛ روی پارچهٔ نازک بریده می‌شود تا سنگین نشود.',
                'چینِ دامن روی خط کمر یکنواخت پخش می‌شود، نه فقط روی پهلوها.',
            ],
        ];
    }
}
