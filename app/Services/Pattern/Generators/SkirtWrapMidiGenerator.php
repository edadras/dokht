<?php

namespace App\Services\Pattern\Generators;

/** دامنِ راپِ میدی که از پهلو گره می‌خورد. */
class SkirtWrapMidiGenerator extends SkirtWrapGenerator
{
    public static function key(): string
    {
        return 'skirt_wrap_midi';
    }

    public function label(): string
    {
        return 'دامن راپ میدی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 74,
        ]);
    }
}
