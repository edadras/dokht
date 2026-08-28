<?php

namespace App\Services\Pattern\Generators;

/** شنل‌پالتوی بلند با شکافِ دست. */
class CapeCoatGenerator extends CoatCapeGenerator
{
    public static function key(): string
    {
        return 'cape_coat';
    }

    public function label(): string
    {
        return 'شنل‌پالتو';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 88,
        ]);
    }
}
