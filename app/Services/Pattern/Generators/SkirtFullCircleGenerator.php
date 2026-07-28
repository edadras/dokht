<?php

namespace App\Services\Pattern\Generators;

/** دامن کلوش کامل: کمر روی یک دایره کامل نشسته و دم دامن بیشترین موج را دارد. */
class SkirtFullCircleGenerator extends SkirtCircleBase
{
    public static function key(): string
    {
        return 'skirt_circle_full';
    }

    public function label(): string
    {
        return 'دامن کلوش کامل';
    }

    protected function fraction(): float
    {
        return 1.0;
    }
}
