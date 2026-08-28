<?php

namespace App\Services\Pattern\Generators;

/** دامنِ پیلیِ ریزِ میدی — همان دامنِ پرفروشِ ویترین‌ها. */
class SkirtMidiPleatedGenerator extends SkirtKnifePleatGenerator
{
    public static function key(): string
    {
        return 'skirt_midi_pleated';
    }

    public function label(): string
    {
        return 'دامن پیلی‌دار میدی';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 76,
        ]);
    }
}
