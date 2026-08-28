<?php

namespace App\Services\Pattern\Generators;

/** شومیز نامتقارن: خط یقه یک‌طرفه و لبهٔ پایین مورب؛ دو نیمهٔ جلو یکی نیستند. */
class BlouseAsymmetricGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_asymmetric';
    }

    public function label(): string
    {
        return 'شومیز نامتقارن';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-asym', 'title' => 'شومیز نامتقارن', 'fit' => 'regular',
            'neckline' => 'one_shoulder', 'collar' => 'none', 'sleeve' => 'none', 'use' => 'party',
            'body_length' => 18];
    }
}
