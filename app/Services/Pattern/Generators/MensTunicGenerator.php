<?php

namespace App\Services\Pattern\Generators;

/** تونیک مردانه: تا میان ران، چاک پهلو و یقهٔ ایستاده؛ روی شلوار پوشیده می‌شود. */
class MensTunicGenerator extends MensShirtBaseGenerator
{
    public static function key(): string
    {
        return 'mens_tunic';
    }

    public function label(): string
    {
        return 'تونیک مردانه';
    }

    protected function mens(): array
    {
        return ['prefix' => 'mens-tunic', 'title' => 'تونیک مردانه', 'fit' => 'loose',
            'collar' => 'stand', 'cuff' => 'none', 'use' => 'daily', 'body_length' => 40,
            'collar_height' => 4, 'pocket' => false];
    }
}
