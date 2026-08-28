<?php

namespace App\Services\Pattern\Generators;

/** دستکشِ یک‌انگشتی (میتن). */
class GloveMittenGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'glove_mitten';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'glove_mitten',
            'title' => 'دستکش یک‌انگشتی',
            'kind' => 'glove',
            'parts' => [
                ['form' => 'rect', 'code' => 'body', 'name' => 'کف و پشت دست', 'w' => 12.0, 'h' => 24.0, 'cut' => 4],
                ['form' => 'taper', 'code' => 'thumb', 'name' => 'شست', 'top' => 8.0, 'bottom' => 5.0, 'h' => 9.0, 'cut' => 4],
                ['form' => 'rect', 'code' => 'cuff', 'name' => 'مچ کشباف', 'w' => 'wrist+2', 'h' => 12.0, 'cut' => 2],
            ],
            'notes' => ['هر دست دو لایه دارد: رو و آستر. شست جدا دوخته و روی کف نشانده می‌شود.'],
        ];
    }
}
