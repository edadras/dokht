<?php

namespace App\Services\Pattern\Generators;

/** شومیز تونیک: تا میان ران، با چاک پهلو تا راه رفتن راحت باشد. */
class BlouseTunicGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_tunic';
    }

    public function label(): string
    {
        return 'شومیز تونیک';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-tunic', 'title' => 'شومیز تونیک', 'fit' => 'regular',
            'neckline' => 'v', 'collar' => 'stand', 'sleeve' => 'long', 'use' => 'modest',
            'body_length' => 44, 'bust_dart' => false];
    }
}
