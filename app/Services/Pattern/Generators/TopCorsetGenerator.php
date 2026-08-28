<?php

namespace App\Services\Pattern\Generators;

/** تاپِ کرستِ تیغه‌دار با بستِ پشت. */
class TopCorsetGenerator extends TopBustierGenerator
{
    public static function key(): string
    {
        return 'top_corset';
    }

    public function label(): string
    {
        return 'تاپ کرست';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 0,
        ]);
    }
}
