<?php

namespace App\Services\Pattern\Generators;

/** پاپیونِ خودبند. */
class TieBowGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'tie_bow';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'tie_bow',
            'title' => 'پاپیون',
            'kind' => 'tie',
            'parts' => [
                ['form' => 'taper', 'code' => 'wing', 'name' => 'بال پاپیون', 'top' => 12.0, 'bottom' => 5.0, 'h' => 22.0, 'cut' => 4],
                ['form' => 'rect', 'code' => 'band', 'name' => 'نوار گردن', 'w' => 'neck+8', 'h' => 6.0, 'cut' => 2],
                ['form' => 'rect', 'code' => 'knot', 'name' => 'گره میانی', 'w' => 6.0, 'h' => 5.0, 'cut' => 2],
            ],
        ];
    }
}
