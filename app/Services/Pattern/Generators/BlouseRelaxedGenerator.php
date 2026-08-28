<?php

namespace App\Services\Pattern\Generators;

/** شومیز آزاد: میانِ معمولی و اورسایز؛ بی‌ساسون و با حلقهٔ کمی پایین‌تر. */
class BlouseRelaxedGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_relaxed';
    }

    public function label(): string
    {
        return 'شومیز آزاد';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-relaxed', 'title' => 'شومیز آزاد', 'fit' => 'loose',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'daily',
            'armhole' => 4.5, 'bust_dart' => false];
    }
}
