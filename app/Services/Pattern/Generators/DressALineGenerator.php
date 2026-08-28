<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ خط A: از سرشانه تا دم به‌آرامی باز می‌شود. */
class DressALineGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_a_line';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_a_line',
            'title' => 'پیراهن خط A',
            'form' => 'onepiece',
            'shape' => 'straight',
            'length' => 48,
            'hem_flare' => 7,
            'sleeve' => 'set_in',
            'sleeve_length' => 20,
        ];
    }
}
