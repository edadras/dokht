<?php

namespace App\Services\Pattern\Generators;

/** تاپِ توریِ آستین‌بلند که روی تاپِ دیگر پوشیده می‌شود. */
class TopMeshGenerator extends TshirtLongSleeveGenerator
{
    public static function key(): string
    {
        return 'top_mesh';
    }

    public function label(): string
    {
        return 'تاپ توری';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 0.5,
            'neck_width_extra' => 2,
        ]);
    }
}
