<?php

namespace App\Services\Pattern\Generators;

/** ربع‌کلوش: یک‌چهارم دایره؛ نزدیک به دامن A ولی بدون ساسون. */
class SkirtQuarterCircleGenerator extends SkirtCircleBase
{
    public static function key(): string
    {
        return 'skirt_circle_quarter';
    }

    public function label(): string
    {
        return 'دامن ربع‌کلوش';
    }

    protected function fraction(): float
    {
        return 0.25;
    }
}
