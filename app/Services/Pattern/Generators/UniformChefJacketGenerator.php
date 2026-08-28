<?php

namespace App\Services\Pattern\Generators;

/** کتِ آشپزی: دوردکمهٔ دوطرفه تا وقتی یک رو لک شد، رویِ دیگر جلو بیاید. */
class UniformChefJacketGenerator extends UniformBaseGenerator
{
    public static function key(): string
    {
        return 'uniform_chef_jacket';
    }

    protected function uniform(): array
    {
        return [
            'prefix' => 'uniform_chef_jacket',
            'title' => 'کت آشپزی',
            'form' => 'top',
            'use' => 'kitchen',
            'length' => 22,
            'grow' => 4,
            'armhole' => 5,
            'opening' => 'button',
            'buttons' => 8,
            'button_stand' => 6,
            'collar' => 'stand',
            'collar_height' => 4,
            'sleeve_length' => 56,
            'pocket' => true,
            'notes' => ['اضافه جای دکمه پهن است تا کت دوطرفه بسته شود؛ رویِ لک‌شده به داخل برمی‌گردد.'],
        ];
    }
}
