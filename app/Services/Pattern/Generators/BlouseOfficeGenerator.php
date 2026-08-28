<?php

namespace App\Services\Pattern\Generators;

/** شومیز اداری: فرم معمولی، بلندتر تا زیر کمربندِ دامن یا شلوار بماند. */
class BlouseOfficeGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_office';
    }

    public function label(): string
    {
        return 'شومیز اداری';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-office', 'title' => 'شومیز اداری', 'fit' => 'regular',
            'neckline' => 'round', 'collar' => 'shirt', 'sleeve' => 'long', 'use' => 'office',
            'body_length' => 22, 'pocket' => false];
    }
}
