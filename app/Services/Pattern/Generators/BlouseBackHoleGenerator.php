<?php

namespace App\Services\Pattern\Generators;

/** جلوِ بسته و پشتِ باز با گرهِ گردنی. */
class BlouseBackHoleGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_backless';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse_backless', 'title' => 'شومیز پشت‌باز',
            'fit' => 'fitted', 'neckline' => 'halter', 'collar' => 'none', 'sleeve' => 'none',
            'use' => 'party', 'back_slit' => 22,
            'notes' => ['پشت تا خطِ کمر باز است؛ سجافِ لبه باید لایی بخورد وگرنه لبه تاب می‌خورد.']];
    }
}
