<?php

namespace App\Services\Pattern\Generators;

/** مایوی پشت‌باز: گودیِ پشتِ زیاد با بندِ باریک. */
class SwimOnepieceDeepBackGenerator extends SwimOnePieceGenerator
{
    public static function key(): string
    {
        return 'swim_onepiece_deep_back';
    }

    public function label(): string
    {
        return 'مایو پشت‌باز';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_drop' => 12,
            'back_drop' => 28,
            'strap_width' => 2.5,
            'leg_rise' => 9,
        ]);
    }
}
