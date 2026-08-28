<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ تابستانیِ بچگانه: سرخود، بی‌آستین، دامنِ باز. */
class ChildSummerDressGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_summer_dress';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_summer_dress',
            'title' => 'پیراهن تابستانی بچگانه',
            'form' => 'dress',
            'use' => 'daily',
            'length' => 34,
            'length_max' => 90,
            'shape' => 'straight',
            'opening' => 'closed',
            'sleeve' => 'none',
            'sleeve_length' => 0,
            'hem_flare' => 5,
        ];
    }
}
