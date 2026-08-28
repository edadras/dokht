<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ چرمِ مصنوعیِ چسبان؛ پارچه کشش دارد ولی برنمی‌گردد. */
class PantsFauxLeatherGenerator extends PantsSkinnyGenerator
{
    public static function key(): string
    {
        return 'pants_faux_leather';
    }

    public function label(): string
    {
        return 'شلوار چرمی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 2,
            'knee_ease' => 1,
            'hem_ease' => 1,
        ]);
    }
}
