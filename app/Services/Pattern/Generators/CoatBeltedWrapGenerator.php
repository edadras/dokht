<?php

namespace App\Services\Pattern\Generators;

/** پالتوی راپِ کمربندی، بی‌دکمه. */
class CoatBeltedWrapGenerator extends CoatWrapGenerator
{
    public static function key(): string
    {
        return 'coat_belted_wrap';
    }

    public function label(): string
    {
        return 'پالتو کمربندی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 92,
        ]);
    }
}
