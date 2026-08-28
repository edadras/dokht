<?php

namespace App\Services\Pattern\Generators;

/** یقهٔ چهارگوشِ باز با آستینِ کوتاهِ پفی. */
class BlouseSquareNeckGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_square_neck';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_square_neck', 'title' => 'شومیز یقه‌چهارگوش',
            'fit' => 'fitted', 'neckline' => 'square', 'collar' => 'none', 'sleeve' => 'puff',
            'use' => 'daily'];
    }
}
