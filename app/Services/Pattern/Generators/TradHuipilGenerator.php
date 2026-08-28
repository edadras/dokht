<?php

namespace App\Services\Pattern\Generators;

/**
 * اوئیپیل.
 *
 * ساده‌ترین لباسِ این فهرست: دو مستطیل که از سرشانه به هم دوخته می‌شوند و وسط
 * سرشانه سوراخِ سر می‌ماند. هیچ حلقهٔ آستینی نیست و درزِ پهلو تا زیر بغل باز
 * است. تمامِ کار روی نوارهای گلدوزیِ افقی است.
 */
class TradHuipilGenerator extends RegionalDressBaseGenerator
{
    public static function key(): string
    {
        return 'trad_huipil';
    }

    public function label(): string
    {
        return 'اوئیپیل (Huipil)';
    }

    protected function regional(): array
    {
        return [
            'prefix' => 'huipil-',
            'title' => 'اوئیپیل',
            'length' => 92,
            'sleeve' => 0,
            'fullness' => 1.0,
            'flare' => 0,
            'slit' => 10,
            'vent' => 30,
            'neck_depth' => 6,
            'shape' => 'straight',
            'notes' => [
                'آستین ندارد؛ درزِ پهلو از زیر بغل شروع می‌شود و بالایش باز می‌ماند.',
                'نوارهای گلدوزیِ افقی روی قطعهٔ تخت کار می‌شوند.',
            ],
        ];
    }
}
