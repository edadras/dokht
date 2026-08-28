<?php

namespace App\Services\Pattern\Generators;

/** کیفِ خریدِ چهارگوش با دو بندِ بلندِ شانه. */
class BagToteGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_tote';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_tote',
            'title' => 'کیف خرید (توت)',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'تنه کیف', 'w' => 40.0, 'h' => 42.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'gusset', 'name' => 'کف و پهلو (نوار دور)', 'w' => 12.0, 'h' => 96.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'strap', 'name' => 'بند شانه', 'w' => 8.0, 'h' => 62.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'lining', 'name' => 'آستر تنه', 'w' => 40.0, 'h' => 42.0, 'cut' => 2],
            ],
            'notes' => ['نوارِ دور از یک کفِ کیف تا دو پهلو یک‌تکه بریده می‌شود تا کف درز نخورد.'],
        ];
    }
}
