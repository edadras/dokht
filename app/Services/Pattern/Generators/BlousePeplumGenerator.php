<?php

namespace App\Services\Pattern\Generators;

/** شومیز پپلوم: تنهٔ جذب تا خط کمر و یک نوارِ کلوشِ کوتاه از آن‌جا به پایین. */
class BlousePeplumGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_peplum';
    }

    public function label(): string
    {
        return 'شومیز پپلوم';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-peplum', 'title' => 'شومیز پپلوم', 'fit' => 'fitted',
            'neckline' => 'round', 'collar' => 'none', 'sleeve' => 'three_quarter', 'use' => 'party',
            'body_length' => 2, 'ruffle' => 0];
    }
}
