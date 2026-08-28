<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس گیلکی.
 *
 * ترکیبِ تنهٔ کوتاهِ کمرگیر و دامنِ چین‌دارِ کوتاه‌تر از بقیهٔ لباس‌های محلی — تا
 * زیر زانو، نه تا مچ پا. زیرِ آن شلوار پوشیده می‌شود، پس دامن کوتاه بودن اشکالی
 * ندارد.
 */
class TradGilakiGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_gilaki';
    }

    public function label(): string
    {
        return 'لباس گیلکی';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'gilaki-',
            'title' => 'لباس گیلکی',
            'length' => 96,
            'sleeve' => 56,
            'fullness' => 2.0,
            'waist_drop' => 36,
            'slit' => 14,
            'fit' => 'regular',
            'notes' => [
                'خط کمر با بند یا کش جمع می‌شود، نه با ساسون.',
                'زیرِ لباس شلوار پوشیده می‌شود؛ بلندی دامن تا زیر زانو کافی است.',
            ],
        ];
    }
}
