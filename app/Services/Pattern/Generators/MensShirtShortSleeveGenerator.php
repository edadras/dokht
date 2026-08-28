<?php

namespace App\Services\Pattern\Generators;

/** پیراهن آستین‌کوتاه: آستین بی‌مچ‌بند، با لبهٔ تودوزی‌شده. */
class MensShirtShortSleeveGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_short_sleeve';
    }

    public function label(): string
    {
        return 'پیراهن مردانه آستین‌کوتاه';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-short', 'title' => 'پیراهن آستین‌کوتاه', 'fit' => 'regular',
            'collar' => 'shirt', 'cuff' => 'none', 'use' => 'daily', 'sleeve' => 24];
    }
}
