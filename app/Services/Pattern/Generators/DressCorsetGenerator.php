<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * لباس کرست‌دار.
 *
 * بالاتنه‌اش کرست است، نه بالاتنهٔ فنردار. تفاوت این دو در کاتالوگ لباس شب یک چیز
 * است: بالاتنهٔ معمولی فرم بدن را *نشان* می‌دهد و کرست فرم بدن را *عوض* می‌کند.
 * پس این مدل چیزهایی می‌خواهد که بقیهٔ خانواده نمی‌خواهند:
 *
 *   جمع‌کردن کمر   کمر عمداً چند سانتی‌متر کوچک‌تر از دور بدن بریده می‌شود. این
 *                  عدد پارامتر است، چون بیش از هشت سانتی‌متر بدون کرست‌سازِ حرفه‌ای
 *                  نه پوشیدنی است نه سالم.
 *   پارچهٔ پشتیبان لایهٔ کوتیل زیر پوسته. کرست روی ساتنِ تنها می‌ترکد.
 *   کانال تیغه     تیغهٔ فنر داخل کانالِ نواری می‌نشیند، نه روی درز؛ کانال قطعهٔ
 *                  جداست و باید بریده شود.
 *   نوار کمر       تمام کشش کرست روی نوار کمرِ داخلی می‌افتد، نه روی درزها.
 *   لبهٔ بند کشی   پشتِ بند کشی باید پاکتِ محافظ داشته باشد، وگرنه بند روی پوست
 *                  می‌افتد و لبهٔ لباس باز دیده می‌شود.
 *
 * دامنش عمداً باریک است: کرست خودش پُر است و دامن پُر روی کرست، بالاتنه را
 * کوچک‌تر از آنچه هست نشان می‌دهد.
 */
class DressCorsetGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'dress_corset';
    }

    public function label(): string
    {
        return 'لباس کرست‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(92, 'بلندی دامن از خط کمر'), [
                    'waist_reduction' => [
                        'label' => 'جمع‌شدن کمر', 'min' => 0, 'max' => 8, 'step' => 0.5,
                        'default' => 4, 'unit' => 'سانتی‌متر',
                        'hint' => 'کمر این‌قدر کوچک‌تر از دور بدن بریده می‌شود؛ بیش از هشت سانتی‌متر کار کرست‌ساز است، نه الگو.',
                    ],
                    'boning_count' => [
                        'label' => 'تعداد تیغهٔ فنر', 'min' => 4, 'max' => 16, 'step' => 2,
                        'default' => 10,
                    ],
                    'coutil' => [
                        'label' => 'لایهٔ کوتیل (پارچهٔ پشتیبان)', 'type' => 'toggle', 'default' => true,
                    ],
                    'back_vent' => [
                        'label' => 'بلندی چاک پشت دامن', 'min' => 0, 'max' => 35, 'step' => 1,
                        'default' => 18, 'unit' => 'سانتی‌متر',
                    ],
                ]),
                ['neckline' => 'sweetheart', 'closure' => 'lacing', 'boning' => true, 'bust_cups' => true],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        // کرست کمر را کوچک‌تر می‌گیرد. این آزادیِ منفی روی *هر دو* قطعه می‌نشیند،
        // وگرنه کمرِ کرست از کمرِ دامن کوچک‌تر می‌شود و دو لبه به هم نمی‌رسند.
        $reduction = max(0.0, (float) $this->param($params, 'waist_reduction', 4));
        $ease['waist'] = $this->ease($ease, 'waist', 2) - $reduction;

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'corset']);

        $length = (float) $this->param($params, 'skirt_length', 92);

        $skirt = $this->gownSkirt('skirt_straight', $measurements, $ease, [
            'length' => $length,
            'hem_change' => 1.0,
            'vent_length' => (float) $this->param($params, 'back_vent', 18),
            'back_darts' => 2,
        ], 'corset');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        // کانال تیغهٔ فنر: نوار باریکی که تیغه داخلش می‌رود. بلندی هر کانال دو
        // سانتی‌متر کمتر از بلندی بالاتنه است تا سر تیغه از لبه بیرون نزند.
        $bodiceHeight = 0.0;

        foreach ($bodice as $piece) {
            if (in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                $bodiceHeight = max($bodiceHeight, Geometry::height($piece['outline']));
            }
        }

        $count = max(4, (int) $this->param($params, 'boning_count', 10));

        $pieces[] = $this->bandPiece('corset-boning-channel', 'کانال تیغهٔ فنر', max(10.0, $bodiceHeight - 2), 2.4, [
            'cut' => $count, 'part' => 'binding',
            'meta' => [
                'girth_role' => 'trim',
                'boning' => true,
                // «تیغهٔ فنر» نوعِ شناخته‌شدهٔ صورت مواد نیست؛ با نوع عمومی ثبت
                // می‌شود تا در فهرست خرید بیاید، ولی نامش کامل نوشته شده است.
                'notions' => [[
                    'type' => 'other',
                    'label' => 'تیغهٔ فنر مارپیچ',
                    'count' => $count,
                    'length' => round(max(8.0, $bodiceHeight - 4), 1),
                ]],
                'notes' => ['تیغه دو سانتی‌متر کوتاه‌تر از کانال بریده می‌شود و دو سرش گرد و پوشیده می‌گردد.'],
            ],
        ]);

        // نوار کمر داخلی: تمام کشش کرست روی این می‌افتد، نه روی درزها
        $pieces[] = $this->bandPiece('corset-waist-stay', 'نوار کمر داخلی', max(20.0, $waist + 6), 2.5, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'girth_role' => 'trim',
                'target_waist' => round($waist, 2),
                'notes' => ['نوار گروگرن به اندازهٔ '.$this->fa(round($waist, 1)).' سانتی‌متر روی خط کمر داخل لباس دوخته می‌شود و با قزن بسته می‌گردد.'],
            ],
        ]);

        if ((string) $this->param($params, 'closure', 'lacing') === 'lacing') {
            $pieces[] = $this->bandPiece('corset-modesty-panel', 'پاکت محافظ پشتِ بند کشی', max(14.0, $bodiceHeight), 14, [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'girth_role' => 'trim',
                    'interfacing' => true,
                    'notes' => ['زیر بند کشی می‌نشیند تا پوست دیده نشود و بند روی تن نیفتد؛ یک لبه‌اش به لباس دوخته و لبهٔ دیگر آزاد می‌ماند.'],
                ],
            ]);
        }

        if ($this->flag($params, 'coutil', true)) {
            foreach ($bodice as $piece) {
                if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                    continue;
                }

                $coutil = $piece;
                $coutil['code'] = ($piece['code'] ?? 'panel').'-coutil';
                $coutil['name'] = 'کوتیل '.($piece['name'] ?? '');
                $coutil['layer'] = 'lining';
                $coutil['meta']['girth_role'] = 'lining';
                $coutil['meta']['part'] = 'lining';
                $coutil['meta']['coutil'] = true;
                unset($coutil['meta']['notions']);
                $coutil['meta']['notes'] = ['هم‌اندازهٔ قطعهٔ رو از پارچهٔ کوتیل؛ کانال تیغه روی همین لایه دوخته می‌شود، نه روی پوسته.'];

                $pieces[] = $coutil;
            }
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'کمر '.$this->fa(round($reduction, 1)).' سانتی‌متر کوچک‌تر از دور بدن بریده شده است؛ همین عدد روی دامن هم اعمال شده تا دو کمر به هم برسند.',
            'کانال تیغه روی لایهٔ کوتیل دوخته می‌شود، نه روی پوسته؛ روی ساتن، درزِ کانال از رو دیده می‌شود.',
            'تمام کشش کرست روی نوار کمر داخلی می‌افتد. بدون آن، درزهای پهلو کم‌کم باز می‌شوند.',
            $reduction > 5.5
                ? 'هشدار: جمع‌شدن کمر بیش از پنج و نیم سانتی‌متر است. این اندازه بدون کرست‌سازِ حرفه‌ای پوشیدنی نیست؛ در پرو با لباسِ نیم‌دوخت بسنجید.'
                : 'جمع‌شدن کمر در محدودهٔ پوشیدنی است؛ در پرو حتماً با لباسِ نیم‌دوخت و روی خودِ تن سنجیده شود.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
