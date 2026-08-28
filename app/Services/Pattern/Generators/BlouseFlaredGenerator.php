<?php

namespace App\Services\Pattern\Generators;

/** شومیز کلوش: از زیر سینه باز می‌شود و لبهٔ پایینش پُر است. */
class BlouseFlaredGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_flared';
    }

    public function label(): string
    {
        return 'شومیز کلوش';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-flared', 'title' => 'شومیز کلوش', 'fit' => 'loose',
            'neckline' => 'scoop', 'collar' => 'none', 'sleeve' => 'bell', 'use' => 'daily',
            'body_length' => 26, 'bust_dart' => false];
    }
}
