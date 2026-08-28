<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ کار: پارچهٔ سنگین، جیبِ بغل، رانِ گشاد برای نشستن و بالا رفتن. */
class UniformWorkTrousersGenerator extends PantsCargoGenerator
{
    /** این مدل در فهرست، زیر «یونیفرم و لباس کار» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'uniform';
    }

    public static function key(): string
    {
        return 'uniform_work_trousers';
    }

    public function label(): string
    {
        return 'شلوار کار';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 18,
            'knee_ease' => 16,
            'hem_ease' => 16,
        ]);
    }
}
