<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پایهٔ لباس‌های محلی: پیراهنِ بلندِ گشاد، با یا بدون دامنِ چین‌دار.
 *
 * لباس محلی چند چیز مشترک دارد که ارزشِ یک پایهٔ جدا را می‌سازد:
 *
 *   ۱. تنه هیچ‌وقت قالبِ بدن نیست. کمرگیری اگر باشد از بند یا کش می‌آید، نه از
 *      ساسون؛ پس شکلِ پایه «راست» یا «ذوزنقه» است.
 *   ۲. بلندی از زانو پایین‌تر است و لبه باز می‌شود.
 *   ۳. آستین بلند است و اغلب گشاد؛ چاکِ یقه روی مرکز جلو و کوتاه.
 *   ۴. بسیاری‌شان دامنِ چین‌دارِ جدا دارند که به خط کمرِ تنه دوخته می‌شود — و
 *      پُریِ آن دامن، نه برشِ تنه، چیزی است که سایه‌ی لباس را می‌سازد.
 *
 * هر مدل تنها همان چند عددِ خودش را عوض می‌کند: پُریِ دامن، بلندی، پهنای آستین و
 * جای چاک. بقیه از این‌جا می‌آید.
 */
abstract class RegionalDressBaseGenerator extends TraditionalBaseGenerator
{
    /**
     * عددهای شناسنامه‌ی هر مدل.
     *
     * @return array<string, mixed>
     */
    abstract protected function regional(): array;

    public function paramsSchema(): array
    {
        $own = $this->regional();

        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => (float) ($own['neck_width'] ?? 1),
                'front_neck_depth_extra' => (float) ($own['neck_depth'] ?? 2),
                'back_neck_depth' => 2,
                'armhole_depth_extra' => (float) ($own['armhole'] ?? 3),
            ]),
            $this->fitParam($own['fit'] ?? 'loose'),
            $this->garmentLengthParam((float) ($own['length'] ?? 110), 40, 160),
            $this->collarParam($own['collar'] ?? 'none', [
                'none' => 'بدون یقه (نوار اریب دور یقه)',
                'stand' => 'یقه ایستاده کوتاه',
            ], 3.0),
            $this->sleeveParam('set_in', (float) ($own['sleeve'] ?? 58), [
                'none' => 'بدون آستین (نوار حلقه)',
                'set_in' => 'آستین حلقه‌ای',
            ]),
            [
                'skirt_fullness' => [
                    'label' => 'پُری دامن', 'min' => 1, 'max' => 4, 'step' => 0.1,
                    'default' => (float) ($own['fullness'] ?? 1),
                    'hint' => 'یک یعنی دامنِ جدا ندارد و تنه یک‌تکه تا پایین می‌آید.',
                ],
                'waist_drop' => [
                    'label' => 'جای درز کمر از سرشانه', 'min' => 20, 'max' => 60, 'step' => 1,
                    'default' => (float) ($own['waist_drop'] ?? 40), 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => (float) ($own['flare'] ?? 8), 'unit' => 'سانتی‌متر',
                ],
                'front_slit' => [
                    'label' => 'بلندی چاک عمودی جلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => (float) ($own['slit'] ?? 18), 'unit' => 'سانتی‌متر',
                ],
                'side_vent' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 60, 'step' => 1,
                    'default' => (float) ($own['vent'] ?? 0), 'unit' => 'سانتی‌متر',
                ],
                'sleeve_width' => [
                    'label' => 'گشادی دم آستین', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => (float) ($own['cuff_flare'] ?? 0), 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی آستینِ راست؛ عددِ بزرگ یعنی دمِ آستینِ باز و بلند.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $own = $this->regional();
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 1.0, 'regular' => 3.0, 'loose' => 6.0]);
        $length = (float) $this->param($params, 'length', $own['length'] ?? 110);
        $fullness = max(1.0, (float) $this->param($params, 'skirt_fullness', $own['fullness'] ?? 1));
        $drop = (float) $this->param($params, 'waist_drop', $own['waist_drop'] ?? 40);
        $slit = (float) $this->param($params, 'front_slit', $own['slit'] ?? 18);
        $vent = (float) $this->param($params, 'side_vent', $own['vent'] ?? 0);
        $split = $fullness > 1.05 && $drop < $length - 12;

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => $own['prefix'],
            'grow' => $grow,
            'shape' => $split ? 'straight' : ($own['shape'] ?? 'trapeze'),
            'length' => $split ? $drop : $length,
            'opening' => 'closed',
            'facing' => false,
            'hem_flare' => $split ? 0.0 : (float) $this->param($params, 'hem_flare', $own['flare'] ?? 8),
            'front_name' => 'تنه جلوی '.$own['title'],
            'back_name' => 'تنه پشت '.$own['title'],
            'panel' => ['waist_dart' => false, 'bottom_tag' => $split ? 'waist' : 'hem'],
            'sleeve' => ['sleeve_name' => 'آستین '.$own['title']],
        ]);

        if ($vent > 0) {
            $pieces[0] = $this->markSideVent($pieces[0], $vent);
            $pieces[1] = $this->markSideVent($pieces[1], $vent);
        }

        if ($slit > 0) {
            $pieces[0]['markers'][] = $this->marker(
                'slit',
                'چاک عمودی جلو',
                0,
                $g['front_neck_depth'],
                0,
                $g['front_neck_depth'] + $slit,
            );
            $pieces[0]['meta']['placket'] = round($slit, 2);
        }

        if ($split) {
            /*
             * دامن روی *لبهٔ خودِ تنه* چین می‌خورد، نه روی دور کمرِ بدن.
             *
             * تنهٔ این لباس‌ها گشاد است: لبهٔ پایینش می‌تواند ۱۱۰ سانتی‌متر باشد در
             * حالی که کمرِ بدن ۷۴ است. اگر دامن را روی ۷۴ ببندیم، درزِ کمر
             * سی‌وشش سانتی‌متر کم می‌آورد و اصلاً بسته نمی‌شود.
             */
            $seat = 2 * ($this->panelWidthAt($pieces[0], $drop) + $this->panelWidthAt($pieces[1], $drop));

            foreach ($this->regionalSkirt($seat, $own, $length - $drop, $fullness) as $panel) {
                $pieces[] = $panel;
            }
        }

        $cuff = (float) $this->param($params, 'sleeve_width', $own['cuff_flare'] ?? 0);

        if ($cuff > 0.5) {
            foreach ($pieces as $at => $piece) {
                if (($piece['meta']['part'] ?? null) === 'sleeve') {
                    $pieces[$at] = $this->flareSleeve($piece, $cuff);
                }
            }
        }

        $pieces[] = $this->bandPiece(
            $own['prefix'].'neck-binding',
            'نوار اریب دور یقه',
            (2 * $this->neckOf([$pieces[0], $pieces[1]])) + $slit * 2 + 6,
            3,
            [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'bias' => true,
                    'girth_role' => 'trim',
                    'notes' => ['روی اریب بریده می‌شود تا هم دور یقه و هم دو لبهٔ چاک را بپوشاند.'],
                ],
            ],
        );

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $length,
            'hem_at' => $length >= 100 ? 'روی مچ پا' : ($length >= 75 ? 'زیر زانو' : 'روی زانو'),
            'sleeve' => (string) $this->param($params, 'sleeve_style', 'set_in') === 'none'
                ? 'ندارد؛ حلقه با نوار تمام می‌شود'
                : $this->fa(round((float) $this->param($params, 'sleeve_length', $own['sleeve'] ?? 58))).' سانتی‌متر از سرشانه',
            'neck' => $slit > 0
                ? 'بسته، با چاک '.$this->fa(round($slit)).' سانتی‌متری روی مرکز جلو'
                : 'بسته و گرد',
        ]);

        $pieces[0]['meta']['notes'] = array_merge(
            $pieces[0]['meta']['notes'] ?? [],
            $this->modestNotes($own['notes'] ?? []),
        );

        return $this->finishBlock($pieces, $g, $grow);
    }

    /**
     * دامنِ چین‌دارِ جدا، دوخته به خط کمرِ تنه.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function regionalSkirt(float $seat, array $own, float $length, float $fullness): array
    {
        $seat = max(50.0, $seat);
        $tiers = max(1, (int) ($own['tiers'] ?? 1));
        $out = [];
        $top = $seat * $fullness;

        for ($tier = 0; $tier < $tiers; $tier++) {
            $height = $length / $tiers;
            $bottom = $top * (float) ($own['tier_grow'] ?? 1.6);

            $out[] = $this->piece([
                'code' => $own['prefix'].'skirt-'.($tier + 1),
                'name' => $tiers > 1 ? 'طبقهٔ '.$this->fa($tier + 1).' دامن' : 'دامن چین‌دار',
                'cut_quantity' => 2,
                'on_fold' => true,
                'mirror' => false,
                'outline' => [
                    Geometry::point(0, 0),
                    Geometry::point($top / 4, 0),
                    Geometry::point($bottom / 4, $height),
                    Geometry::point(0, $height),
                ],
                'grainline' => $this->grainline($top / 8, 2, $height - 2),
                'meta' => [
                    /*
                     * تنها طبقهٔ نخست به تنه دوخته می‌شود؛ طبقه‌های بعدی روی
                     * طبقهٔ بالای خودشان چین می‌خورند — همان کاری که دامن
                     * طبقه‌ای می‌کند، و همان برچسبی که آن به کار می‌برد.
                     */
                    'part' => $tier === 0 ? 'skirt_front' : 'skirt_tier',
                    'side' => 'front',
                    'edges' => [$tier === 0 ? 'waist' : 'default', 'side', 'hem', 'side'],
                    'fold_edges' => [3],
                    'gathers' => [[
                        'edge' => 0,
                        'amount' => round(($top - $seat) / 4, 2),
                        'note' => 'چینِ خط کمر، یکنواخت دور تا دور',
                    ]],
                    'girth' => [],
                    'girth_factor' => 0,
                    'girth_role' => 'trim',
                    'notes' => [
                        'لبهٔ بالا روی لبهٔ پایینِ تنه چین می‌خورد؛ چین را یکنواخت پخش کنید.',
                        'اندازهٔ بسته‌شدهٔ لبهٔ بالا برابرِ لبهٔ پایینِ تنه است ('
                            .$this->fa(round($seat)).' سانتی‌متر).',
                    ],
                ],
            ]);

            $top = $bottom;
        }

        return $out;
    }

    /** دمِ آستین را باز می‌کند: آستینِ گشادِ محلی. */
    protected function flareSleeve(array $piece, float $flare): array
    {
        $points = Geometry::flatten($piece['outline'] ?? []);
        $count = count($points);

        if ($count < 4) {
            return $piece;
        }

        $lowest = 0.0;

        foreach ($points as $point) {
            $lowest = max($lowest, (float) $point['y']);
        }

        $piece['outline'] = array_map(function (array $point) use ($lowest, $flare) {
            if (abs(((float) ($point['y'] ?? 0)) - $lowest) > 0.5) {
                return $point;
            }

            $point['x'] = ((float) ($point['x'] ?? 0)) + (($point['x'] ?? 0) > 0 ? $flare / 2 : -$flare / 2);

            return $point;
        }, $piece['outline']);

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'دمِ آستین '.$this->fa(round($flare)).' سانتی‌متر بازتر از آستینِ راست است.',
        ]);

        return $piece;
    }
}
