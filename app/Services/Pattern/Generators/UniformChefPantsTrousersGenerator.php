<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ آشپزی: کمرِ کشی و پای گشاد، تا بشود ساعت‌ها ایستاد. */
class UniformChefPantsTrousersGenerator extends PantsElasticWaistGenerator
{
    /** این مدل در فهرست، زیر «یونیفرم و لباس کار» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'uniform';
    }

    public static function key(): string
    {
        return 'uniform_chef_pants';
    }

    public function label(): string
    {
        return 'شلوار آشپزی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 20,
            'knee_ease' => 20,
            'hem_ease' => 18,
        ]);
    }
}
