<?php

namespace App\Services\Pattern\Generators;

/** شلوارِ پاراشوتیِ گشاد با دمِ پای بنددار و جیبِ بغل. */
class PantsParachuteGenerator extends PantsCargoGenerator
{
    public static function key(): string
    {
        return 'pants_parachute';
    }

    public function label(): string
    {
        return 'شلوار پاراشوتی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 24,
            'knee_ease' => 26,
            'hem_ease' => 20,
        ]);
    }
}
