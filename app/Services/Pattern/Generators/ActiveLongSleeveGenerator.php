<?php

namespace App\Services\Pattern\Generators;

/** بالاتنهٔ آستین‌بلندِ ورزشی برای هوای خنک. */
class ActiveLongSleeveGenerator extends ActiveTopBaseGenerator
{
    public static function key(): string
    {
        return 'active_long_sleeve';
    }

    protected function active(): array
    {
        return [
            'prefix' => 'active_long_sleeve',
            'title' => 'بالاتنه آستین‌بلند ورزشی',
            'use' => 'run',
            'stretch' => 0.94,
            'length' => 14,
            'back_drop' => 4,
            'sleeve' => 'set_in',
            'sleeve_length' => 58,
        ];
    }
}
