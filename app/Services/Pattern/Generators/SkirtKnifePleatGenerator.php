<?php

namespace App\Services\Pattern\Generators;

/** دامن پیلی تیغه‌ای: همه پیلی‌ها به یک سمت می‌خوابند؛ جای هر پیلی ۲×عمق. */
class SkirtKnifePleatGenerator extends SkirtPleatBase
{
    public static function key(): string
    {
        return 'skirt_pleat_knife';
    }

    public function label(): string
    {
        return 'دامن پیلی تیغه‌ای';
    }

    protected function pleatStyle(): string
    {
        return 'knife';
    }

    protected function pleatConsumption(float $depth): float
    {
        return 2 * $depth;
    }
}
