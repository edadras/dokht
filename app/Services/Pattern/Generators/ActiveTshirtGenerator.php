<?php

namespace App\Services\Pattern\Generators;

/** تی‌شرتِ ورزشی: کشیِ سبک، آستینِ کوتاه، دمِ پشت بلندتر. */
class ActiveTshirtGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_tshirt';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_tshirt',
            'title' => 'تی‌شرت ورزشی',
            'use' => 'gym',
            'stretch' => 0.96,
            'length' => 12,
            'back_drop' => 3,
            'sleeve' => 'set_in',
            'sleeve_length' => 22,
            'binding' => true,
        ];
    }
}
