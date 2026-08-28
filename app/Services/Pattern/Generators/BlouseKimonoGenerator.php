<?php

namespace App\Services\Pattern\Generators;

/** شومیز کیمونو: آستین از خودِ تنه درمی‌آید و حلقهٔ آستینِ جدا ندارد. */
class BlouseKimonoGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_kimono';
    }

    public function label(): string
    {
        return 'شومیز کیمونو';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-kimono', 'title' => 'شومیز کیمونو', 'fit' => 'loose',
            'neckline' => 'v', 'collar' => 'none', 'sleeve' => 'kimono', 'use' => 'daily',
            'armhole' => 7, 'bust_dart' => false, 'opening' => 'closed'];
    }
}
