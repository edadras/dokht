<?php

namespace App\Services\Pattern\Generators;

/** پیراهن رسمی مردانه: یقهٔ برگردانِ بلند، مچِ دکمه‌دار، یوکِ پشت و پیلیِ وسط. */
class MensShirtDressGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_dress';
    }

    public function label(): string
    {
        return 'پیراهن مردانه رسمی';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-dress', 'title' => 'پیراهن رسمی', 'fit' => 'regular',
            'collar' => 'shirt', 'cuff' => 'button', 'use' => 'office', 'collar_height' => 8];
    }
}
