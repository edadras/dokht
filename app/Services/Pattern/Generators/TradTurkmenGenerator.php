<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس ترکمنی.
 *
 * تنهٔ راستِ بلند با چاکِ عمودیِ بلندِ جلو که تمامش گلدوزی می‌شود. برشِ لباس
 * عمداً ساده است: هرچه هست روی همان نوارِ چاک و دمِ آستین است، پس الگو باید آن
 * نوار را جدا و قابلِ کار بدهد.
 */
class TradTurkmenGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_turkmen';
    }

    public function label(): string
    {
        return 'لباس ترکمنی';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'turkmen-',
            'title' => 'لباس ترکمنی',
            'length' => 134,
            'sleeve' => 60,
            'fullness' => 1.0,
            'flare' => 14,
            'slit' => 34,
            'vent' => 22,
            'notes' => [
                'نوارِ گلدوزیِ چاک جدا بریده و بعد روی دو لبهٔ چاک دوخته می‌شود.',
            ],
        ];
    }
}
