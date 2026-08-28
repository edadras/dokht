<?php

namespace App\Services\Pattern\Generators;

/** تنه با نخِ کشی چند رج کش‌دوزی می‌شود و روی تن می‌نشیند. */
class BlouseShirredGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_shirred';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_shirred', 'title' => 'شومیز کش‌دوزی',
            'fit' => 'regular', 'neckline' => 'square', 'collar' => 'none', 'sleeve' => 'flutter',
            'use' => 'summer', 'gathers' => 14, 'bust_dart' => false,
            'notes' => ['چینِ کش‌دوزی روی الگو باز بریده می‌شود و با نخِ کشِ ماسوره جمع می‌شود.']];
    }
}
