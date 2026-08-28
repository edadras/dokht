<?php

namespace App\Services\Pattern\Generators;

/** کفتانِ ساحلیِ بلند تا مچِ پا. */
class BeachKaftanLongGenerator extends BeachCoverKaftanGenerator
{
    public static function key(): string
    {
        return 'beach_cover_kaftan_long';
    }

    public function label(): string
    {
        return 'کفتان ساحلی بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 56,
            'ease_extra' => 6,
        ]);
    }
}
