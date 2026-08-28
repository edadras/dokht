<?php

namespace App\Services\Pattern\Generators;

/** یقهٔ قلبی با تنهٔ جذب؛ زیرش نوارِ تقویتی لازم دارد. */
class BlouseSweetheartGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_sweetheart';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_sweetheart', 'title' => 'شومیز یقه‌قلبی',
            'fit' => 'fitted', 'neckline' => 'sweetheart', 'collar' => 'none', 'sleeve' => 'cap',
            'use' => 'party'];
    }
}
