<?php

namespace App\Services\Pattern\Generators;

/** پافرِ مردانه: لایهٔ پُرِ کاپوک، پس آزادی‌اش باید حجمِ خودِ لایه را هم جا بدهد. */
class MensPufferGenerator extends JacketPufferGenerator
{
    public static function key(): string
    {
        return 'mens_puffer';
    }

    public function label(): string
    {
        return 'کاپشن پافر مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.5,
            'armhole_depth_extra' => 7.5,
        ]);
    }
}
