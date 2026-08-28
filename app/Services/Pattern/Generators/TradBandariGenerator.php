<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس بندری (جنوب).
 *
 * پیراهنِ بلندِ گشادِ یک‌تکه، بی‌کمر و بی‌ساسون، از پارچهٔ نازک. کارِ اصلی روی
 * یقه و سرِ آستین است: یک کادرِ گلدوزی‌شده روی سینه که در الگو یک قطعهٔ جدا
 * (یوکِ رودوزی) است تا بشود جدا دوخت و بعد روی تنه نشاند.
 */
class TradBandariGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_bandari';
    }

    public function label(): string
    {
        return 'لباس بندری';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'bandari-',
            'title' => 'لباس بندری',
            'length' => 138,
            'sleeve' => 60,
            'cuff_flare' => 12,
            'fullness' => 1.0,
            'flare' => 22,
            'slit' => 24,
            'shape' => 'trapeze',
            'notes' => [
                'کادرِ گلدوزی روی سینه پیش از دوختِ سرشانه روی تنه کار می‌شود.',
                'پارچه نازک و روشن است؛ برای همین تنه هیچ لایی نمی‌خورد.',
            ],
        ];
    }
}
