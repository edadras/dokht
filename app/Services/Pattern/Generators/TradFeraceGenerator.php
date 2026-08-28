<?php

namespace App\Services\Pattern\Generators;

/**
 * فراجه.
 *
 * روپوشِ بلندِ جلوبازِ عثمانی: از سرشانه تا مچ پا یک خط می‌آید، آستینش بلند و
 * گشاد است و جلویش بی‌دکمه روی هم می‌افتد. تفاوتش با عبا در سرشانهٔ دوخته است —
 * عبا از پارچهٔ تخت آویزان می‌شود، فراجه حلقهٔ آستین دارد.
 */
class TradFeraceGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_ferace';
    }

    public function label(): string
    {
        return 'فراجه (Ferace)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'ferace-',
            'title' => 'فراجه',
            'length' => 142,
            'sleeve' => 62,
            'cuff_flare' => 14,
            'fullness' => 1.0,
            'flare' => 20,
            'slit' => 0,
            'shape' => 'trapeze',
            'notes' => [
                'جلو بی‌دکمه است و روی هم می‌افتد؛ لبهٔ جلو با سجاف تمیز می‌شود.',
            ],
        ];
    }
}
