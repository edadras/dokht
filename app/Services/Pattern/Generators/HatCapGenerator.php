<?php

namespace App\Services\Pattern\Generators;

/** کلاهِ نقاب‌دار از شش ترک. */
class HatCapGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'hat_cap';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'hat_cap',
            'title' => 'کلاه نقاب‌دار',
            'kind' => 'hat',
            'parts' => [
                ['form' => 'gore', 'code' => 'gore', 'name' => 'ترک تاج', 'girth' => 'head', 'h' => 18.0, 'panels' => 6, 'cut' => 6],
                ['form' => 'ring', 'code' => 'peak', 'name' => 'نقاب', 'r' => 'head/6.3', 'width' => 7.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'sweatband', 'name' => 'نوار عرق‌گیر', 'w' => 'head', 'h' => 5.0, 'cut' => 1],
            ],
            'notes' => ['نقاب دو لایه است و میانش مقوای فشرده می‌آید؛ فقط نیمِ جلوِ حلقه بریده می‌شود.'],
        ];
    }
}
