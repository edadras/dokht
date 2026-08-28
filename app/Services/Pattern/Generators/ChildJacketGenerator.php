<?php

namespace App\Services\Pattern\Generators;

/** کاپشنِ سبکِ بچگانه با زیپِ جلو و کلاه. */
class ChildJacketGenerator extends ChildGarmentBaseGenerator
{
    public static function key(): string
    {
        return 'child_jacket';
    }

    protected function child(): array
    {
        return [
            'prefix' => 'child_jacket',
            'title' => 'کاپشن بچگانه',
            'form' => 'top',
            'use' => 'outdoor',
            'play' => 3.5,
            'growth' => 3,
            'length' => 18,
            'shape' => 'straight',
            'opening' => 'zip',
            'collar' => 'hood',
            'collar_height' => 6,
            'sleeve_length' => 42,
            'pocket' => true,
            'schema' => ['armhole_depth_extra' => 4],
        ];
    }
}
