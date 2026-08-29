<?php

namespace App\Services\Pattern\Generators;

/**
 * تاپِ کرستِ تیغه‌دار با بستِ پشت.
 *
 * تفاوتش با بوستیه فقط نام نیست: کرست تنگ‌تر بریده می‌شود و تیغه‌های بیشتری
 * دارد، چون کارش شکل‌دادن است نه فقط پوشاندن. پیش‌تر تنها «آزادی بیشتر = صفر»
 * می‌فرستاد که همان پیش‌فرضِ بوستیه بود، یعنی این دو مدل مو به مو یکی بودند.
 */
class TopCorsetGenerator extends TopBustierGenerator
{
    public static function key(): string
    {
        return 'top_corset';
    }

    public function label(): string
    {
        return 'تاپ کرست';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => -1.5,
            'front_panels' => 4,
            'back_panels' => 3,
        ]);
    }
}
