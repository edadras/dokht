<?php

namespace App\Services\Pattern\Generators;

/** مایوی یقه‌بسته: یقهٔ جلو کم‌گود و پشتِ بسته؛ برای شنایِ پوششی. */
class SwimOnepieceHighNeckGenerator extends SwimOnePieceGenerator
{
    public static function key(): string
    {
        return 'swim_onepiece_high_neck';
    }

    public function label(): string
    {
        return 'مایو یقه‌بسته';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_drop' => 2,
            'back_drop' => 4,
            'leg_rise' => 3,
            'strap_width' => 6,
        ]);
    }
}
