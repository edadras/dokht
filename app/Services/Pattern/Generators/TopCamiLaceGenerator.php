<?php

namespace App\Services\Pattern\Generators;

/** تاپِ بندی با لبهٔ توری. */
class TopCamiLaceGenerator extends TopCamisoleGenerator
{
    public static function key(): string
    {
        return 'top_cami_lace';
    }

    public function label(): string
    {
        return 'تاپ بندی توری';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'strap_width' => 1.2,
        ]);
    }
}
