<?php

namespace App\Services\Pattern\Generators;

/** پیراهن یقه‌ایستاده (مائو): بی برگردان، پس گردن آزادتر و لباس رسمی‌تر دیده نمی‌شود. */
class MensShirtMandarinGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_mandarin';
    }

    public function label(): string
    {
        return 'پیراهن مردانه یقه‌ایستاده';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-mandarin', 'title' => 'پیراهن یقه‌ایستاده', 'fit' => 'regular',
            'collar' => 'stand', 'cuff' => 'button', 'use' => 'daily', 'collar_height' => 4];
    }
}
