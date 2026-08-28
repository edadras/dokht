<?php

namespace App\Services\Pattern\Generators;

/**
 * داشیکی.
 *
 * پیراهنِ گشادِ آفریقای غربی: تنه یک مستطیلِ ساده است و آستین از خودِ تنه
 * درمی‌آید (کیمونویی). یقهٔ V با نوارِ گلدوزی‌شدهٔ پهن قاب می‌شود و همان نوار
 * تنها قطعهٔ شکل‌دارِ الگوست.
 */
class TradDashikiGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_dashiki';
    }

    public function label(): string
    {
        return 'داشیکی (Dashiki)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'dashiki-',
            'title' => 'داشیکی',
            'length' => 88,
            'sleeve' => 34,
            'cuff_flare' => 6,
            'fullness' => 1.0,
            'flare' => 12,
            'slit' => 22,
            'neck_depth' => 8,
            'notes' => [
                'نوارِ گلدوزیِ دور یقه پهن است و پیش از دوختِ سرشانه کار می‌شود.',
                'آستینِ کوتاهِ گشاد؛ حلقهٔ آستین پایین‌تر از حلقهٔ معمول است.',
            ],
        ];
    }
}
