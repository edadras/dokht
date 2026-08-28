<?php

namespace App\Services\Pattern\Generators;

/** پیراهن هاوایی: یقهٔ بازِ خوابیده که دکمهٔ بالایی ندارد و لبه‌اش خودش برمی‌گردد. */
class MensShirtCampGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_camp';
    }

    public function label(): string
    {
        return 'پیراهن مردانه هاوایی';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-camp', 'title' => 'پیراهن هاوایی', 'fit' => 'loose',
            'collar' => 'camp', 'cuff' => 'none', 'use' => 'daily', 'sleeve' => 22,
            'body_length' => 16];
    }
}
