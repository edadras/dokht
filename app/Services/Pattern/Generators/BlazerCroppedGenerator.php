<?php

namespace App\Services\Pattern\Generators;

/** کتِ کوتاه تا خطِ کمر. */
class BlazerCroppedGenerator extends BlazerGenerator
{
    public static function key(): string
    {
        return 'blazer_cropped';
    }

    public function label(): string
    {
        return 'کت کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 6,
            'ease_extra' => 2,
        ]);
    }
}
