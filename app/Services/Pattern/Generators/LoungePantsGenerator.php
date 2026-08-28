<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ راحتیِ خانه: کمرِ کشی، پای راسته و گشاد. */
class LoungePantsGenerator extends PantsElasticWaistGenerator
{
    /** این مدل در فهرست، زیر «زیر و راحتی» می‌نشیند نه زیر «پایین‌تنه». */
    public static function group(): string
    {
        return 'sleepwear';
    }

    public static function key(): string
    {
        return 'lounge_pants';
    }

    public function label(): string
    {
        return 'شلوار راحتی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 16,
            'knee_ease' => 16,
            'hem_ease' => 18,
        ]);
    }
}
