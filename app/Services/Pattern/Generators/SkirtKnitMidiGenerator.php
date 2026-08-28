<?php

namespace App\Services\Pattern\Generators;

/** دامنِ کشبافِ میدی با کمرِ کشی. */
class SkirtKnitMidiGenerator extends PencilSkirtGenerator
{
    public static function key(): string
    {
        return 'skirt_knit_midi';
    }

    public function label(): string
    {
        return 'دامن کشباف میدی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 74,
        ]);
    }
}
