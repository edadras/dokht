<?php

namespace App\Services\Pattern\Generators;

/** پلیورِ کوتاه که بالای خطِ کمر تمام می‌شود. */
class KnitCroppedSweaterGenerator extends HoodieSweatshirtGenerator
{
    public static function key(): string
    {
        return 'knit_cropped_sweater';
    }

    public function label(): string
    {
        return 'پلیور کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 0,
            'ease_extra' => 4,
        ]);
    }
}
