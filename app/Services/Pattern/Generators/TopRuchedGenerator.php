<?php

namespace App\Services\Pattern\Generators;

/** تاپِ چین‌دارِ پهلو. */
class TopRuchedGenerator extends TopCropGenerator
{
    public static function key(): string
    {
        return 'top_ruched';
    }

    public function label(): string
    {
        return 'تاپ چین‌دار';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 0.5,
        ]);
    }
}
