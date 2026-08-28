<?php

namespace App\Services\Pattern\Generators;

/** جلیقهٔ شب‌نما: گشاد، رویِ همه‌چیز، با زیپِ جلو. */
class UniformHiVisVestGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_hi_vis_vest';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_hi_vis_vest',
            'title' => 'جلیقه شب‌نما',
            'form' => 'top',
            'use' => 'workshop',
            'length' => 14,
            'grow' => 5.5,
            'armhole' => 7,
            'opening' => 'zip',
            'collar' => 'none',
            'sleeve' => 'none',
            'sleeve_length' => 0,
            'pocket' => false,
            'notes' => ['روی کاپشن هم پوشیده می‌شود؛ نوارِ بازتاب روی سینه و کمر دوخته می‌شود.'],
        ];
    }
}
