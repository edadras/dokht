<?php

namespace App\Services\Pattern\Generators;

/** پالتوی خزِ مصنوعی (تدی): گشاد، کوتاه، با یقهٔ ایستاده. */
class CoatTeddyGenerator extends CoatOvercoatGenerator
{
    public static function key(): string
    {
        return 'coat_teddy';
    }

    public function label(): string
    {
        return 'پالتو تدی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 62,
            'ease_extra' => 7,
            'collar' => 'stand',
            'collar_height' => 7,
            'armhole_depth_extra' => 7,
        ]);
    }
}
