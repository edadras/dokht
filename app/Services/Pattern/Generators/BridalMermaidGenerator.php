<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ ماهی: تا زیرِ زانو چسبان و بعد باز. */
class BridalMermaidGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_mermaid';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_mermaid',
            'title' => 'لباس عروس ماهی',
            'skirt' => 'skirt_mermaid',
            'length' => 120,
            'skirt_params' => ['flare_start' => 'knee', 'flare' => 60],
            'neckline' => 'straight',
            'fit' => 'fitted',
            'notes' => ['چاکِ پشت لازم است، وگرنه با این دامن نمی‌شود قدم برداشت.'],
        ];
    }
}
