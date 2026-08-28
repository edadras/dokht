<?php

namespace App\Services\Pattern\Generators;

/**
 * کبایا.
 *
 * بالاتنهٔ کوتاهِ جلوبازِ قالب با لبهٔ جلوی منحنی. کوتاه است — تا روی باسن — و
 * زیرش با ساروَنگ یا باتیک پوشیده می‌شود. لبهٔ جلو تور یا گلدوزی می‌خورد، پس در
 * الگو سجافِ پهن دارد تا آن کار جایی برای نشستن داشته باشد.
 */
class TradKebayaGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_kebaya';
    }

    public function label(): string
    {
        return 'کبایا (Kebaya)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'kebaya-',
            'title' => 'کبایا',
            'length' => 62,
            'sleeve' => 58,
            'cuff_flare' => 0,
            'fullness' => 1.0,
            'flare' => 6,
            'slit' => 0,
            'fit' => 'fitted',
            'shape' => 'fitted',
            'notes' => [
                'لبهٔ جلو باز می‌ماند و با سنجاق بسته می‌شود؛ دکمه ندارد.',
                'سجافِ پهنِ جلو جای گلدوزی یا تور است.',
            ],
        ];
    }
}
