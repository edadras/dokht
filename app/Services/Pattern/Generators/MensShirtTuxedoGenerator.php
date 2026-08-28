<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن مجلسیِ تاکسیدو.
 *
 * سه چیز آن را از پیراهن رسمی جدا می‌کند و هر سه در الگو هست: مچِ دوبلِ
 * فرانسوی، پاتلتِ پهنِ پیلی‌دارِ جلو (پلاسترون) و یقهٔ ایستادهٔ کوتاه با نوکِ
 * برگشته.
 */
class MensShirtTuxedoGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_tuxedo';
    }

    public function label(): string
    {
        return 'پیراهن مردانه مجلسی (تاکسیدو)';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-tuxedo', 'title' => 'پیراهن تاکسیدو', 'fit' => 'fitted',
            'collar' => 'shirt', 'cuff' => 'french', 'use' => 'party', 'collar_height' => 7,
            'pocket' => false, 'back_pleat' => 'none'];
    }
}
