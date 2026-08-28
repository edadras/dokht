<?php

namespace App\Services\Pattern\Generators;

/** کلاهِ کشباف از شش ترک با نوارِ دورِ سر. */
class HatBeanieGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'hat_beanie';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'hat_beanie',
            'title' => 'کلاه بافتنی (بینی)',
            'kind' => 'hat',
            'parts' => [
                ['form' => 'gore', 'code' => 'gore', 'name' => 'ترک تاج', 'girth' => 'head-2', 'h' => 20.0, 'panels' => 6, 'cut' => 6],
                ['form' => 'rect', 'code' => 'band', 'name' => 'نوار دور سر', 'w' => 'head-4', 'h' => 12.0, 'cut' => 1],
            ],
            'notes' => ['نوارِ دورِ سر کوتاه‌تر از دورِ سر بریده می‌شود؛ کششِ همان است که کلاه را نگه می‌دارد.'],
        ];
    }
}
