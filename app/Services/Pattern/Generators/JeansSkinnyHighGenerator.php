<?php

namespace App\Services\Pattern\Generators;

/** جینِ جذبِ کمربلند؛ با پارچهٔ کشی. */
class JeansSkinnyHighGenerator extends PantsSkinnyGenerator
{
    public static function key(): string
    {
        return 'jeans_skinny_high';
    }

    public function label(): string
    {
        return 'شلوار جین جذب کمربلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise' => 'high',
            'thigh_ease' => 4,
            'knee_ease' => 2,
            'hem_ease' => 2,
        ]);
    }
}
