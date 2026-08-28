<?php

namespace App\Services\Pattern\Generators;

/** تایتِ سه‌ربع: تا زیرِ زانو، برای هوای گرم. */
class ActiveCapriTightsGenerator extends ActiveTightsGenerator
{
    public static function key(): string
    {
        return 'active_capri_tights';
    }

    public function label(): string
    {
        return 'تایت سه‌ربع';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length_extra' => -22,
        ]);
    }
}
