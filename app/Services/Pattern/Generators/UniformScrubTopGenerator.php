<?php

namespace App\Services\Pattern\Generators;

/** بالاتنهٔ اسکراب: سرخود، یقهٔ هفت، دو جیبِ پایین. */
class UniformScrubTopGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_scrub_top';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_scrub_top',
            'title' => 'بالاتنه اسکراب',
            'form' => 'top',
            'use' => 'medical',
            'length' => 18,
            'grow' => 3.5,
            'armhole' => 4.5,
            'opening' => 'closed',
            'collar' => 'none',
            'sleeve_length' => 22,
            'pocket' => true,
            'schema' => ['neck_width_extra' => 3, 'front_neck_depth_extra' => 9],
            'notes' => ['یقه هفت است تا از سر رد شود؛ اسکراب هیچ بستی ندارد.'],
        ];
    }
}
