<?php

namespace App\Services\Pattern\Generators;

/**
 * شومیز بارداری.
 *
 * جا برای شکم از *زیر سینه* باز می‌شود، نه از خط کمر: برشِ امپایر و چینِ زیرِ
 * آن. اگر فقط لباس را گشاد کنیم، روی شانه هم گشاد می‌شود و می‌افتد.
 */
class BlouseMaternityGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_maternity';
    }

    public function label(): string
    {
        return 'شومیز بارداری';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-maternity', 'title' => 'شومیز بارداری', 'fit' => 'loose',
            'neckline' => 'v', 'collar' => 'none', 'sleeve' => 'three_quarter', 'use' => 'maternity',
            'body_length' => 30, 'gathers' => 12, 'bust_dart' => true, 'belt' => true];
    }
}
