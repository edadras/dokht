<?php

namespace App\Services\Pattern\Generators;

/**
 * بیندالی.
 *
 * پیراهنِ مخملیِ گلدوزی‌شدهٔ ترکی. برش نزدیک به تن است — برخلاف بیشترِ لباس‌های
 * محلی — چون گلدوزیِ سنگین روی پارچهٔ ایستاده باید صاف بخوابد و چینِ اضافه آن را
 * می‌شکند. پس تنه کمرگیر و دامن نیم‌کلوش است.
 */
class TradBindalliGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_bindalli';
    }

    public function label(): string
    {
        return 'بیندالی (Bindallı)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'bindalli-',
            'title' => 'بیندالی',
            'length' => 136,
            'sleeve' => 60,
            'cuff_flare' => 10,
            'fullness' => 1.0,
            'flare' => 16,
            'slit' => 0,
            'fit' => 'fitted',
            'shape' => 'fitted',
            'notes' => [
                'گلدوزیِ سنگین پیش از بستنِ درزها روی قطعهٔ تخت کار می‌شود.',
                'پارچهٔ مخمل جهت‌دار است؛ همهٔ قطعه‌ها باید یک‌جهت بریده شوند.',
            ],
        ];
    }
}
