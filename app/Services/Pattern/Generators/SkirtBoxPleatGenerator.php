<?php

namespace App\Services\Pattern\Generators;

/** دامن پیلی جعبه‌ای: دو تای روبه‌روی هم که رو به بیرون باز می‌شوند؛ جای هر پیلی ۴×عمق. */
class SkirtBoxPleatGenerator extends SkirtPleatBase
{
    public static function key(): string
    {
        return 'skirt_pleat_box';
    }

    public function label(): string
    {
        return 'دامن پیلی جعبه‌ای';
    }

    protected function pleatStyle(): string
    {
        return 'box';
    }

    protected function pleatConsumption(float $depth): float
    {
        return 4 * $depth;
    }

    protected function defaultCount(): int
    {
        return 8;
    }

    protected function defaultDepth(): float
    {
        return 5.0;
    }
}
