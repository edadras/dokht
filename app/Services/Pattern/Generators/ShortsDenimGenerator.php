<?php

namespace App\Services\Pattern\Generators;

/** شلوارکِ جینِ کوتاه با دمِ پای تاخورده. */
class ShortsDenimGenerator extends ShortsShortGenerator
{
    public static function key(): string
    {
        return 'shorts_denim';
    }

    public function label(): string
    {
        return 'شلوارک جین';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'thigh_ease' => 7,
            'hem_ease' => 5,
        ]);
    }
}
