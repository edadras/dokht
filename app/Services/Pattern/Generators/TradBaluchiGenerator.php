<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس بلوچی.
 *
 * سه ناحیهٔ دوزی دارد که هر سه در الگو قطعهٔ جدا هستند: کادرِ سینه، جیبِ بزرگِ
 * مثلثیِ روی شکم (پَشک) و دمِ آستین. تنه خودش راستِ ساده است — تمامِ شناسنامهٔ
 * لباس روی همان سه قطعه است.
 */
class TradBaluchiGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_baluchi';
    }

    public function label(): string
    {
        return 'لباس بلوچی';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'baluchi-',
            'title' => 'لباس بلوچی',
            'length' => 130,
            'sleeve' => 60,
            'cuff_flare' => 10,
            'fullness' => 1.0,
            'flare' => 18,
            'slit' => 20,
            'vent' => 26,
            'notes' => [
                'پَشک (جیبِ بزرگِ جلو) پیش از بستنِ درزِ پهلو روی تنه دوخته می‌شود.',
                'چاکِ پهلو بلند است تا با شلوار پوشیده شود.',
            ],
        ];
    }
}
