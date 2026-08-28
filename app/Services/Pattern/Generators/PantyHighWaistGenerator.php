<?php

namespace App\Services\Pattern\Generators;

/** شورتِ کمرِ بلند: کمر روی گودیِ کمر می‌نشیند و شکم را می‌پوشاند. */
class PantyHighWaistGenerator extends PantyBriefGenerator
{
    public static function key(): string
    {
        return 'panty_high_waist';
    }

    public function label(): string
    {
        return 'شورت کمر بلند';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise_drop' => 0,
            'side_seam' => 10,
            'coverage' => 'full',
            'seat' => 5,
            'waist_binding' => true,
        ]);
    }
}
