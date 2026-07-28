<?php

namespace App\Services\Pattern\Generators;

/** نیم‌کلوش: نصف دایره؛ موج ملایم‌تر و پارچه کمتر از کلوش کامل. */
class SkirtHalfCircleGenerator extends SkirtCircleBase
{
    public static function key(): string
    {
        return 'skirt_circle_half';
    }

    public function label(): string
    {
        return 'دامن نیم‌کلوش';
    }

    protected function fraction(): float
    {
        return 0.5;
    }
}
