<?php

namespace App\Services\Pattern\Generators;

/** شالِ گردنِ حلقه‌ای (بی‌انتها). */
class ScarfInfinityGenerator extends AccessoryBaseGenerator
{
    public static function key(): string
    {
        return 'scarf_infinity';
    }

    protected function accessory(): array
    {
        return [
            'prefix' => 'scarf_infinity',
            'title' => 'شال گردن حلقه‌ای',
            'kind' => 'scarf',
            'parts' => [
                ['form' => 'tube', 'code' => 'body', 'name' => 'حلقه شال', 'w' => 60.0, 'h' => 150.0, 'cut' => 1],
            ],
            'notes' => ['دو سر به هم دوخته می‌شود؛ پیچِ نیم‌دور پیش از دوخت، شال را روی گردن می‌خواباند.'],
        ];
    }
}
