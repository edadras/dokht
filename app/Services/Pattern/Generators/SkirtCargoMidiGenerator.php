<?php

namespace App\Services\Pattern\Generators;

/** دامنِ کارگویِ میدی با جیبِ بغل. */
class SkirtCargoMidiGenerator extends SkirtCargoGenerator
{
    public static function key(): string
    {
        return 'skirt_cargo_midi';
    }

    public function label(): string
    {
        return 'دامن کارگو میدی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 72,
        ]);
    }
}
