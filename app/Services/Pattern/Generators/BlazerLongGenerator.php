<?php

namespace App\Services\Pattern\Generators;

/** کتِ بلند تا میانهٔ ران. */
class BlazerLongGenerator extends BlazerGenerator
{
    public static function key(): string
    {
        return 'blazer_long';
    }

    public function label(): string
    {
        return 'کت بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 46,
            'ease_extra' => 4,
            'armhole_depth_extra' => 5,
        ]);
    }
}
