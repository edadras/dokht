<?php

namespace App\Services\Pattern\Generators;

/** شومیز مجلسی: خط یقهٔ باز، آستین پفی و رافلِ جلو؛ برشِ تنه همان جذب است. */
class BlousePartyGenerator extends BlouseBaseGenerator
{
    public static function key(): string
    {
        return 'blouse_party';
    }

    public function label(): string
    {
        return 'شومیز مجلسی';
    }

    protected function blouse(): array
    {
        return ['prefix' => 'blouse-party', 'title' => 'شومیز مجلسی', 'fit' => 'fitted',
            'neckline' => 'sweetheart', 'collar' => 'none', 'sleeve' => 'puff', 'use' => 'party',
            'ruffle' => 5, 'gathers' => 4];
    }
}
