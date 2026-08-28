<?php

namespace App\Services\Pattern\Generators;

/** کوله‌پشتیِ ساده با درِ برگردان و دو بندِ پشت. */
class BagBackpackGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'bag_backpack';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'bag_backpack',
            'title' => 'کوله‌پشتی',
            'kind' => 'bag',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'تنه کوله', 'w' => 32.0, 'h' => 44.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'gusset', 'name' => 'کف و پهلو', 'w' => 14.0, 'h' => 100.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'flap', 'name' => 'در کوله', 'w' => 32.0, 'h' => 24.0, 'cut' => 2],
                ['form' => 'taper', 'code' => 'strap', 'name' => 'بند پشت', 'top' => 8.0, 'bottom' => 4.0, 'h' => 62.0, 'cut' => 4],
                ['form' => 'rect', 'code' => 'pocket', 'name' => 'جیب جلو', 'w' => 24.0, 'h' => 20.0, 'cut' => 2],
            ],
            'notes' => ['بندها دولا بریده می‌شوند و لایهٔ نرم میانشان می‌آید؛ بندِ یک‌لایه روی شانه رد می‌اندازد.'],
        ];
    }
}
