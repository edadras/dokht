<?php

namespace App\Services\Pattern\Generators;

/** دامنِ چرمِ مصنوعیِ مدادی. */
class SkirtFauxLeatherGenerator extends PencilSkirtGenerator
{
    public static function key(): string
    {
        return 'skirt_faux_leather';
    }

    public function label(): string
    {
        return 'دامن چرمی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 58,
        ]);
    }
}
