<?php

namespace App\Services\Pattern\Generators;

/** لباسِ عروسِ پرنسسی با دامنِ پفیِ چندترکه و زیردامنی. */
class BridalBallGownGenerator extends EveningGownBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_ball_gown';
    }

    protected function gown(): array
    {
        return [
            'prefix' => 'bridal_ball_gown',
            'title' => 'لباس عروس پفی',
            'skirt' => 'skirt_ball_gown',
            'length' => 118,
            'skirt_params' => ['panels' => 8, 'volume' => 'full', 'petticoat' => true],
            'neckline' => 'sweetheart',
            'notes' => ['زیردامنی جدا بریده می‌شود و وزنِ دامن روی همان می‌نشیند، نه روی کمرِ عروس.'],
        ];
    }
}
