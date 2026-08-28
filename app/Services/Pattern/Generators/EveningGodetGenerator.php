<?php

namespace App\Services\Pattern\Generators;

/** لباسِ شب با گودهٔ مثلثی: دامنِ راسته که فقط از پایین باز می‌شود. */
class EveningGodetGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'evening_godet';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'evening_godet',
            'title' => 'لباس شب گوده‌دار',
            'skirt' => 'skirt_godet',
            'length' => 112,
            'skirt_params' => ['panels' => 6, 'godet_count' => 6, 'godet_length' => 40],
            'neckline' => 'sweetheart',
        ];
    }
}
