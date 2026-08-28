<?php

namespace App\Services\Pattern\Generators;

/** کت کت‌وشلوارِ مردانه: جذب‌تر از کت تک، با آسترِ کامل و دو چاکِ پشت. */
class MensSuitJacketGenerator extends SuitJacketGenerator
{
    public static function key(): string
    {
        return 'mens_suit_jacket';
    }

    public function label(): string
    {
        return 'کت کت‌وشلوار مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'neck_width_extra' => 2.0,
            'armhole_depth_extra' => 3.5,
        ]);
    }
}
