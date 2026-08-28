<?php

namespace App\Services\Pattern\Generators;

/** پیراهن اسپرت: گشادتر، یقهٔ کوتاه‌تر و لبهٔ پایینِ صاف تا بیرونِ شلوار پوشیده شود. */
class MensShirtCasualGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_shirt_casual';
    }

    public function label(): string
    {
        return 'پیراهن مردانه اسپرت';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-casual', 'title' => 'پیراهن اسپرت', 'fit' => 'loose',
            'collar' => 'shirt', 'cuff' => 'button', 'use' => 'daily', 'collar_height' => 6.5,
            'body_length' => 18, 'back_pleat' => 'side'];
    }
}
