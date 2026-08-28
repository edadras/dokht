<?php

namespace App\Services\Pattern\Generators;

/** شومیز رسمی: جذب‌تر، یقهٔ مردانهٔ بلندتر و دکمهٔ نزدیک‌تر تا لای دکمه باز نماند. */
class BlouseFormalGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_formal';
    }

    public function label(): string
    {
        return 'شومیز رسمی';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-formal', 'title' => 'شومیز رسمی', 'fit' => 'fitted',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'office',
            'collar_height' => 8, 'defaults' => ['button_spacing' => 6]];
    }
}
