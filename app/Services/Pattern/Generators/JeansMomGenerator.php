<?php

namespace App\Services\Pattern\Generators;

/** جینِ مام‌فیت: کمرِ بلند، رانِ گشاد، پاچهٔ کمی جمع‌شونده. */
class JeansMomGenerator extends PantsTaperedGenerator
{
    public static function key(): string
    {
        return 'jeans_mom';
    }

    public function label(): string
    {
        return 'شلوار جین مام‌فیت';
    }

    public function defaultParams(): array
    {
        return array_merge(parent::defaultParams(), [
            'rise' => 'high',
            'thigh_ease' => 12,
            'knee_ease' => 8,
            'hem_ease' => 4,
        ]);
    }
}
