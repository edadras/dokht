<?php

namespace App\Services\Pattern\Generators;

/**
 * آئو دای.
 *
 * تنهٔ کاملاً قالبِ تن با دو چاکِ پهلوی بسیار بلند که از خط کمر شروع می‌شود، و
 * دو دامنهٔ بلند که آزاد می‌افتند. زیرش شلوارِ راستِ گشاد پوشیده می‌شود، پس چاکِ
 * بلند مشکلی ندارد — و همان چاک است که سایهٔ لباس را می‌سازد.
 */
class TradAoDaiGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_ao_dai';
    }

    public function label(): string
    {
        return 'آئو دای (Áo dài)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'aodai-',
            'title' => 'آئو دای',
            'length' => 128,
            'sleeve' => 60,
            'cuff_flare' => 0,
            'fullness' => 1.0,
            'flare' => 4,
            'slit' => 0,
            'vent' => 62,
            'collar' => 'stand',
            'fit' => 'fitted',
            'shape' => 'fitted',
            'neck_width' => 0.5,
            'notes' => [
                'چاکِ پهلو از خط کمر پایین‌تر شروع می‌شود و تا لبه باز است.',
                'زیرِ آن شلوارِ راستِ گشاد پوشیده می‌شود.',
            ],
        ];
    }
}
