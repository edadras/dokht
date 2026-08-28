<?php

namespace App\Services\Pattern\Generators;

/** روپوشِ کارگاه و هنر: گشاد، سرخود، آستینِ بلند. */
class UniformSmockGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_smock';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_smock',
            'title' => 'روپوش کارگاه',
            'form' => 'top',
            'use' => 'workshop',
            'length' => 34,
            'length_max' => 90,
            'grow' => 6,
            'armhole' => 6,
            'opening' => 'closed',
            'collar' => 'none',
            'sleeve_length' => 58,
            'pocket' => true,
            'schema' => ['neck_width_extra' => 4, 'front_neck_depth_extra' => 6],
            'notes' => ['از سر پوشیده می‌شود، پس یقه باید به‌اندازهٔ دور سر باز باشد.'],
        ];
    }
}
