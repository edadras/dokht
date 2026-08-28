<?php

namespace App\Services\Pattern\Generators;

/** بمبرِ ساتنِ زنانه با نوارِ کشبافِ یقه و مچ. */
class JacketBomberSatinGenerator extends CoatBomberGenerator
{
    public static function key(): string
    {
        return 'jacket_bomber_satin';
    }

    public function label(): string
    {
        return 'کاپشن بمبر ساتن';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'ease_extra' => 5,
        ]);
    }
}
