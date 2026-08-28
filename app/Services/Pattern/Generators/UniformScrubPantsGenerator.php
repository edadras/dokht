<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ اسکراب: کمرِ کشی و بندی، پای راسته، جیبِ بغل. */
class UniformScrubPantsGenerator extends PantsElasticWaistGenerator
{
    /** این مدل در فهرست، زیر «یونیفرم و لباس کار» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'uniform';
    }

    public static function key(): string
    {
        return 'uniform_scrub_pants';
    }

    public function label(): string
    {
        return 'شلوار اسکراب';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 14,
            'knee_ease' => 14,
            'hem_ease' => 14,
        ]);
    }
}
