<?php

namespace App\Services\Pattern\Generators;

/** پولوی کشبافِ زنانه با یقهٔ برگردانِ کوتاه. */
class KnitPoloGenerator extends PoloShirtGenerator
{
    public static function key(): string
    {
        return 'knit_polo';
    }

    public function label(): string
    {
        return 'پولوی کشباف زنانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 1.5,
        ]);
    }
}
