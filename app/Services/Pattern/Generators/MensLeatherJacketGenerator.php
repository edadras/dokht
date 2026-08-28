<?php

namespace App\Services\Pattern\Generators;

/** کتِ چرمِ مردانه: همان بایکر با بلوکِ مردانه؛ زیپِ مورب و یقهٔ برگردانِ کوتاه. */
class MensLeatherJacketGenerator extends JacketBikerGenerator
{
    public static function key(): string
    {
        return 'mens_leather_jacket';
    }

    public function label(): string
    {
        return 'کت چرم مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.0,
            'armhole_depth_extra' => 4.5,
        ]);
    }
}
