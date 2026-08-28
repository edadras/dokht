<?php

namespace App\Services\Pattern\Generators;

/** کراواتِ کلاسیکِ اریب‌بر. */
class TieNeckGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'tie_neck';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'tie_neck',
            'title' => 'کراوات',
            'kind' => 'tie',
            'parts' => [
                ['form' => 'taper', 'code' => 'blade', 'name' => 'تیغه پهن', 'top' => 9.0, 'bottom' => 4.0, 'h' => 90.0, 'cut' => 1],
                ['form' => 'taper', 'code' => 'tail', 'name' => 'تیغه باریک', 'top' => 5.5, 'bottom' => 3.0, 'h' => 60.0, 'cut' => 1],
                ['form' => 'rect', 'code' => 'keeper', 'name' => 'حلقه نگه‌دارنده', 'w' => 4.0, 'h' => 7.0, 'cut' => 1],
            ],
            'notes' => ['هر سه قطعه باید روی اریبِ ۴۵ درجه بریده شوند؛ کراواتِ راست‌بر گره نمی‌خورد و می‌پیچد.'],
        ];
    }
}
