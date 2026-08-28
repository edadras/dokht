<?php

namespace App\Services\Pattern\Generators;

/** تاپِ راپ که جلو روی هم می‌افتد و پهلو گره می‌خورد. */
class TopWrapGenerator extends TopCropGenerator
{
    public static function key(): string
    {
        return 'top_wrap';
    }

    public function label(): string
    {
        return 'تاپ راپ';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 1,
        ]);
    }
}
