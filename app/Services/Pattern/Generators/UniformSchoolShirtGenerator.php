<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ مدرسه: ساده، بادوام، با آزادیِ رشد. */
class UniformSchoolShirtGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_school_shirt';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_school_shirt',
            'title' => 'پیراهن مدرسه',
            'form' => 'top',
            'use' => 'school',
            'length' => 20,
            'grow' => 3,
            'armhole' => 4,
            'opening' => 'button',
            'buttons' => 6,
            'collar' => 'turn',
            'collar_height' => 5,
            'sleeve_length' => 50,
            'pocket' => true,
        ];
    }
}
