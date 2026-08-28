<?php

namespace App\Services\Pattern\Generators;

/**
 * تی‌شرت مردانه.
 *
 * تفاوتش با تی‌شرت زنانه در سه عدد است: سرشانهٔ پهن‌تر، حلقهٔ گودتر و یقهٔ
 * فراخ‌تر. بلوکِ کشی همان است.
 */
class MensTshirtGenerator extends TShirtGenerator
{
    public static function key(): string
    {
        return 'mens_tshirt';
    }

    public function label(): string
    {
        return 'تی‌شرت مردانه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            "shoulder_slope" => 4.0,
            "neck_width_extra" => 2.5,
            "armhole_depth_extra" => 4.0,
            "body_length" => 26,
        ]);
    }
}
