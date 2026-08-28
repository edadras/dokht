<?php

namespace App\Services\Pattern\Generators;

/** پیش‌بندِ کمریِ سرو: بی سینه‌بند، با سه جیب. */
class UniformWaistApronGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_waist_apron';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_waist_apron',
            'title' => 'پیش‌بند کمری سرو',
            'form' => 'apron',
            'use' => 'service',
            'bib_width' => 0,
            'bib_height' => 0,
            'skirt_width' => 74,
            'skirt_length' => 42,
            'pocket_count' => 3,
        ];
    }
}
