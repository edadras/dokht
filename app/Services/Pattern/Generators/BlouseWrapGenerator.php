<?php

namespace App\Services\Pattern\Generators;

/**
 * شومیز راپ.
 *
 * دو نیمهٔ جلو روی هم می‌افتند و با بند بسته می‌شوند، نه دکمه. همین یعنی خط
 * یقه هفتِ عمیقِ مورب است و لبهٔ جلو باید سجاف بخورد، چون دیده می‌شود.
 */
class BlouseWrapGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_wrap';
    }

    public function label(): string
    {
        return 'شومیز راپ (چپ‌وراست)';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-wrap', 'title' => 'شومیز راپ', 'fit' => 'fitted',
            'neckline' => 'deep_v', 'collar' => 'none', 'sleeve' => 'three_quarter', 'use' => 'daily',
            'opening' => 'wrap', 'belt' => true, 'gathers' => 5];
    }
}
