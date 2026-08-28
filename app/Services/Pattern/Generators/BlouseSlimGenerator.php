<?php

namespace App\Services\Pattern\Generators;

/** شومیز جذب: ساسونِ سینه و کمر هر دو، و آزادیِ کم. */
class BlouseSlimGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_slim';
    }

    public function label(): string
    {
        return 'شومیز جذب';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-slim', 'title' => 'شومیز جذب', 'fit' => 'fitted',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'office',
            'armhole' => 3, 'bust_dart' => true];
    }
}
