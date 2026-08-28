<?php

namespace App\Services\Pattern\Generators;

/** دامنِ پیلیِ پهنِ کوتاه — همان دامنِ مدرسه‌ایِ امروزی. */
class SkirtBoxPleatMiniGenerator extends SkirtBoxPleatGenerator
{
    public static function key(): string
    {
        return 'skirt_box_pleat_mini';
    }

    public function label(): string
    {
        return 'دامن پیلی‌پهن کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 42,
        ]);
    }
}
