<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس لری.
 *
 * پیراهنِ بلند با دامنِ بسیار پُر و آستینِ گشاد. تفاوتش با قشقایی در تک‌طبقه
 * بودنِ دامن است: یک تکهٔ پهنِ چین‌خورده به‌جای چند طبقه، پس چینِ خط کمر
 * سنگین‌تر و درشت‌تر است.
 */
class TradLoriGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_lori';
    }

    public function label(): string
    {
        return 'لباس لری';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'lori-',
            'title' => 'لباس لری',
            'length' => 124,
            'sleeve' => 60,
            'cuff_flare' => 16,
            'fullness' => 2.8,
            'waist_drop' => 36,
            'slit' => 18,
            'notes' => [
                'پُریِ دامن زیاد است؛ چینِ خط کمر را با دو رجِ کوکِ درشت جمع کنید.',
            ],
        ];
    }
}
