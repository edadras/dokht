<?php

namespace App\Services\Pattern\Generators;

/** بادیِ آستین‌بلندِ چسبان با قزنِ فاق. */
class TopBodysuitLongSleeveGenerator extends TopBodysuitGenerator
{
    public static function key(): string
    {
        return 'top_bodysuit_long_sleeve';
    }

    public function label(): string
    {
        return 'بادی آستین‌بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'shoulder_width' => 'full',
            'sleeve_style' => 'set_in',
            'sleeve_length' => 58,
        ]);
    }
}
