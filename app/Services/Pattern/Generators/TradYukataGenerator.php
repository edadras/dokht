<?php

namespace App\Services\Pattern\Generators;

/**
 * یوکاتا.
 *
 * کیمونوی سبکِ تابستانی. مثل هر کیمونو از مستطیل‌های راست بریده می‌شود و هیچ
 * منحنی‌ای جز گودیِ کمِ یقه ندارد — عرضِ تختهٔ پارچهٔ سنتی همان پهنای تنه را
 * می‌سازد. تفاوتش با کیمونوی رسمی: یک لایه، بی آستر، و آستینِ کوتاه‌ترِ چسبیده
 * به تنه.
 */
class TradYukataGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_yukata';
    }

    public function label(): string
    {
        return 'یوکاتا';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'yukata-',
            'title' => 'یوکاتا',
            'length' => 140,
            'sleeve' => 48,
            'cuff_flare' => 30,
            'fullness' => 1.0,
            'flare' => 0,
            'slit' => 0,
            'shape' => 'straight',
            'neck_depth' => 10,
            'neck_width' => 2,
            'notes' => [
                'همهٔ قطعه‌ها مستطیلِ راست‌اند؛ تنها منحنی، گودیِ کمِ یقه است.',
                'جلو روی هم می‌افتد — همیشه چپ روی راست — و با کمربندِ اوبی بسته می‌شود.',
                'یک لایه و بی‌آستر است؛ همین آن را از کیمونوی رسمی جدا می‌کند.',
            ],
        ];
    }
}
