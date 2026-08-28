<?php

namespace App\Services\Pattern\Generators;

/** شومیز محجبه: بلند تا روی باسن، آستینِ بلند و خط یقهٔ بسته و بالا. */
class BlouseModestGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_modest';
    }

    public function label(): string
    {
        return 'شومیز محجبه';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-modest', 'title' => 'شومیز محجبه', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'stand', 'sleeve' => 'long', 'use' => 'modest',
            'body_length' => 36, 'bust_dart' => true];
    }
}
