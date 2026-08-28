<?php

namespace App\Services\Pattern\Generators;

/** پیراهن بی‌یقه: خط یقه فقط با نوارِ اریب تمیز می‌شود؛ ساده‌ترین گونهٔ پیراهن. */
class MensShirtGrandadGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_grandad';
    }

    public function label(): string
    {
        return 'پیراهن مردانه بی‌یقه';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-grandad', 'title' => 'پیراهن بی‌یقه', 'fit' => 'loose',
            'collar' => 'none', 'cuff' => 'none', 'use' => 'daily', 'sleeve' => 58,
            'pocket' => false];
    }
}
