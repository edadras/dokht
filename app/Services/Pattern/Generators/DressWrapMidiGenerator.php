<?php

namespace App\Services\Pattern\Generators;

/** پیراهنِ راپِ میدی با گرهِ پهلو. */
class DressWrapMidiGenerator extends DressCatalogBaseGenerator
{
    public static function key(): string
    {
        return 'dress_wrap_midi';
    }

    protected function dress(): array
    {
        return [
            'prefix' => 'dress_wrap_midi',
            'title' => 'پیراهن راپ میدی',
            'form' => 'waisted',
            'skirt' => 'skirt_wrap',
            'skirt_length' => 72,
            'closure' => 'none',
            'sleeve' => 'set_in',
            'sleeve_length' => 40,
            'fit' => 'fitted'
        ];
    }
}
