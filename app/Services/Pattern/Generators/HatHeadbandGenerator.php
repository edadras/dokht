<?php

namespace App\Services\Pattern\Generators;

/** هدبندِ کشیِ پهن. */
class HatHeadbandGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'hat_headband';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'hat_headband',
            'title' => 'هدبند',
            'kind' => 'hat',
            'parts' => [
                ['form' => 'rect', 'code' => 'band', 'name' => 'نوار سر', 'w' => 'head-6', 'h' => 16.0, 'cut' => 1],
            ],
            'notes' => ['از پارچهٔ کشی بریده می‌شود و شش سانتی‌متر کوتاه‌تر از دورِ سر است.'],
        ];
    }
}
