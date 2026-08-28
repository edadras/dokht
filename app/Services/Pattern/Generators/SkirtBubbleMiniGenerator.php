<?php

namespace App\Services\Pattern\Generators;

/** دامنِ بادکنکیِ کوتاه که دمش به زیر برمی‌گردد. */
class SkirtBubbleMiniGenerator extends SkirtBubbleGenerator
{
    public static function key(): string
    {
        return 'skirt_bubble_mini';
    }

    public function label(): string
    {
        return 'دامن بادکنکی کوتاه';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'length' => 40,
        ]);
    }
}
