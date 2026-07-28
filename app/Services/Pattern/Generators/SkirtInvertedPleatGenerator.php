<?php

namespace App\Services\Pattern\Generators;

/** دامن پیلی جعبه‌ای برعکس: تای پیلی‌ها به هم می‌رسند و درز وسط پنهان می‌شود. */
class SkirtInvertedPleatGenerator extends SkirtPleatBase
{
    public static function key(): string
    {
        return 'skirt_pleat_inverted';
    }

    public function label(): string
    {
        return 'دامن پیلی جعبه‌ای برعکس';
    }

    protected function pleatStyle(): string
    {
        return 'inverted';
    }

    protected function pleatConsumption(float $depth): float
    {
        return 4 * $depth;
    }

    protected function defaultCount(): int
    {
        return 4;
    }

    protected function defaultDepth(): float
    {
        return 6.0;
    }
}
