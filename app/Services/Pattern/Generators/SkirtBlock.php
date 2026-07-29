<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * درفت پایه همه دامن‌ها.
 *
 * قرارداد این درفت:
 *   ۱. کاهش کمر تا باسن (باسن÷۴ − کمر÷۴) بین «درز پهلو» و «ساسون» تقسیم می‌شود.
 *      نقطه کمرِ درز پهلو در جلو و پشت دقیقاً یکی است، پس درز پهلوی جلو و پشت
 *      همیشه هم‌اندازه درمی‌آید و بدون راست‌سازی به هم دوخته می‌شود.
 *   ۲. اختلاف کمر جلو و پشت (balance) از عمق ساسون گرفته می‌شود نه از درز پهلو؛
 *      یعنی ساسون جلو کم‌عمق‌تر از ساسون پشت است — همان چیزی که روی تن جواب می‌دهد.
 *   ۳. طول درز پهلو با «بالا آوردن گوشه دم دامن» با قد مرکز جلو برابر می‌شود تا
 *      دامنِ کلوش صاف بیفتد؛ این همان کارِ «گونیا کردن دم دامن» است.
 *   ۴. هر چیزی که پارچه را بیشتر از اندازه تمام‌شده می‌کند (چین، پیلی، هم‌پوشانی)
 *      در meta.fullness ثبت می‌شود تا رندر، نقشه برش و برگه فنی آن را ببینند.
 *
 * هر پنل نیم‌قطعه روی تای پارچه است: سهم آن از دور کمر برابر meta.waist_finished
 * و ضریب تکرارش cut_quantity × (on_fold ? ۲ : ۱) است.
 */
trait SkirtBlock
{
    /**
     * اندازه‌های کلیدی درفت دامن.
     *
     * @param  array<string, mixed>  $o  stretch (ضریب پارچه کشی)، balance، dart_share
     * @return array<string, float>
     */
    protected function skirtMetrics(array $m, array $ease, array $params, array $o = []): array
    {
        $stretch = max(0.5, (float) ($o['stretch'] ?? 1.0));
        $waist = ($this->m($m, 'waist', 74) + $this->ease($ease, 'waist', 4)) * $stretch;
        $hip = ($this->m($m, 'hip', 98) + $this->ease($ease, 'hip', 6)) * $stretch;
        $hipY = max(12.0, $this->m($m, 'waist_to_hip', 21));

        $quarterWaist = max(6.0, $waist / 4);
        $quarterHip = max(7.0, $hip / 4);
        $suppression = max(0.0, $quarterHip - $quarterWaist);

        $dartShare = min(0.9, max(0.0, (float) $this->param($params, 'dart_share', $o['dart_share'] ?? 0.5)));
        $dartTotal = $suppression * $dartShare;

        // ساسون کم‌عمق‌تر از ۱٫۲ سانتی‌متر روی پارچه دیده نمی‌شود؛ همه‌اش به پهلو می‌رود
        if ($dartTotal < 1.2) {
            $dartTotal = 0.0;
        }

        $balance = $dartTotal > 0.0
            ? min(max(0.0, (float) ($o['balance'] ?? 1.0)), max(0.0, $dartTotal - 1.0))
            : 0.0;

        return [
            'waist_target' => round($quarterWaist * 4, 3),
            'hip_target' => round($quarterHip * 4, 3),
            'quarter_waist' => $quarterWaist,
            'quarter_hip' => $quarterHip,
            'hip_y' => $hipY,
            'suppression' => $suppression,
            'dart_total' => $dartTotal,
            'balance' => $balance,
            'side_suppression' => $suppression - $dartTotal,
            'side_waist_x' => $quarterWaist + $dartTotal,
            'stretch' => $stretch,
        ];
    }

    /**
     * پنل پایه دامن: نیم‌جلو یا نیم‌پشت روی تای پارچه.
     *
     * گزینه‌ها: side، length (قد مرکز)، hem_delta (مثبت = کلوش، منفی = تنگ)،
     * dart_count، dart_apex، vent، flare_from (شروع کلوش برای ماهی/ترومپت)،
     * flare_waist (تنگی در محل شروع کلوش)، code، name، part.
     *
     * @return array<string, mixed>
     */
    protected function blockPanel(array $mx, array $o = []): array
    {
        $isFront = ($o['side'] ?? 'front') === 'front';
        $length = max(15.0, (float) ($o['length'] ?? 60));
        $hemDelta = (float) ($o['hem_delta'] ?? 8);
        $quarterHip = $mx['quarter_hip'];
        $hipY = min($mx['hip_y'], $length * 0.55);
        $waistX = $mx['side_waist_x'];
        $balance = $mx['balance'];

        $dartIntake = max(0.0, $mx['dart_total'] + ($isFront ? -$balance : $balance));
        $panelWaist = $mx['quarter_waist'] + ($isFront ? $balance : -$balance);
        $dartCount = $dartIntake > 0.8 ? max(1, (int) ($o['dart_count'] ?? 1)) : 0;
        $hemX = max(3.0, $quarterHip + $hemDelta);

        $flareFrom = isset($o['flare_from'])
            ? max($hipY + 5, min((float) $o['flare_from'], $length - 5))
            : null;
        $flareX = max(3.0, $quarterHip + (float) ($o['flare_waist'] ?? 0));

        // هم‌پوشانی جلو (راپ و لاله‌ای): لبه مرکز از خط مرکز جلو رد می‌شود
        $overlap = max(0.0, (float) ($o['overlap'] ?? 0));
        $overlapHem = (float) ($o['overlap_hem'] ?? -$overlap);
        $waistPleat = max(0.0, (float) ($o['waist_pleat'] ?? 0));
        $waistX += $waistPleat;

        $build = function (float $yHem) use ($waistX, $quarterHip, $hipY, $hemX, $length, $flareFrom, $flareX, $overlap, $overlapHem) {
            $points = [
                $overlap > 0.01 && $overlapHem > -$overlap
                    ? Geometry::curve(-$overlap, 0, -$overlap, $length * 0.55)
                    : Geometry::point(-$overlap, 0),
                Geometry::point($waistX, 0),
                Geometry::curve($quarterHip, $hipY, $waistX + (($quarterHip - $waistX) * 0.55), $hipY * 0.36),
            ];
            $edges = ['waist', 'side'];

            if ($flareFrom !== null) {
                $points[] = Geometry::curve(
                    $flareX,
                    $flareFrom,
                    $quarterHip + (($flareX - $quarterHip) * 0.25),
                    $hipY + (($flareFrom - $hipY) * 0.55),
                );
                $edges[] = 'side';
                $points[] = Geometry::curve(
                    $hemX,
                    $yHem,
                    $flareX + (($hemX - $flareX) * 0.2),
                    $flareFrom + (($yHem - $flareFrom) * 0.6),
                );
                $edges[] = 'side';
            } else {
                $points[] = Geometry::point($hemX, $yHem);
                $edges[] = 'side';
            }

            $points[] = Geometry::curve($overlapHem, $length, ($hemX + $overlapHem) * 0.5, $length);
            $edges[] = 'hem';
            $edges[] = $overlap > 0.01 ? 'side' : 'default';

            return [$points, $edges];
        };

        $sideEdges = $flareFrom !== null ? [1, 2, 3] : [1, 2];

        // گونیا کردن دم دامن: گوشه پهلو آن‌قدر بالا می‌آید که درز پهلو با قد مرکز برابر شود
        $yHem = $this->fitSeamLength(
            fn (float $y) => Geometry::edgesLength($build($y)[0], $sideEdges),
            $length,
            $length * 0.3,
            $length,
        );

        [$outline, $edges] = $build($yHem);
        $count = count($outline);

        $darts = [];
        $apexDepth = (float) ($o['dart_apex'] ?? ($isFront ? $hipY * 0.55 : $hipY * 0.75));

        for ($i = 0; $i < $dartCount; $i++) {
            $centerX = $waistX * (($i + 1) / ($dartCount + 1));
            $darts[] = $this->dart(
                'waist',
                $dartCount > 1 ? 'ساسون کمر '.($i + 1) : 'ساسون کمر',
                0,
                $centerX,
                0,
                $dartIntake / $dartCount,
                $centerX,
                $apexDepth,
            );
        }

        $markers = [
            $this->marker('hip', 'خط باسن', 0, $hipY, $quarterHip),
            $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, 0, 0, $length),
        ];

        $pleats = [];
        $fullnessList = $o['fullness'] ?? [];
        $notes = $o['notes'] ?? [];

        if ($overlap > 0.01) {
            $fullnessList[] = $this->fullness('overlap', 0, $panelWaist + $overlap, $panelWaist, [
                'label' => 'هم‌پوشانی جلو',
                'width' => round($overlap, 2),
            ]);
            $notes[] = 'این پنل '.$this->fa(round($overlap, 1)).' سانتی‌متر روی پنل روبه‌رو می‌افتد؛ این اندازه جزو دور کمر نیست.';
        }

        if ($waistPleat > 0.01) {
            $pleats[] = [
                'label' => 'پیلی کمر',
                'style' => 'knife',
                'depth' => round($waistPleat / 2, 2),
                'intake' => round($waistPleat, 2),
                'from' => Geometry::point($waistX * 0.4, 0),
                'to' => Geometry::point($waistX * 0.4, min($length, $hipY * 1.2)),
            ];
            $fullnessList[] = $this->fullness('pleat', 0, $panelWaist + $waistPleat, $panelWaist, [
                'label' => 'پیلی کمر',
                'count' => 1,
                'depth' => round($waistPleat / 2, 2),
            ]);
        }

        $meta = [
            'part' => $o['part'] ?? ($isFront ? 'skirt_front' : 'skirt_back'),
            'edges' => $edges,
            'fold_edges' => $overlap > 0.01 ? [] : [$count - 1],
            'side' => $isFront ? 'front' : 'back',
            'waist_edges' => [0],
            'side_edges' => $sideEdges,
            'hem_edges' => [$count - 2],
            'waist_target' => $mx['waist_target'],
            'waist_finished' => round($panelWaist, 2),
            'hip_y' => round($hipY, 2),
            'quarter_hip' => round($quarterHip, 2),
            'hem_x' => round($hemX, 2),
            'length' => round($length, 2),
            'seam_length' => Geometry::edgesLength($outline, $sideEdges),
            'seam_group' => $o['seam_group'] ?? 'skirt',
            'fullness' => $fullnessList,
        ];

        if ($notes !== []) {
            $meta['notes'] = $notes;
        }

        // آستر یا لایه دوم به دور کمر تمام‌شده اضافه نمی‌شود
        if (($o['count_waist'] ?? true) === false) {
            $meta['waist_lining'] = $meta['waist_finished'];
            unset($meta['waist_finished']);
        }

        $vent = (float) ($o['vent'] ?? 0);

        if ($vent > 0.5) {
            $vent = min($vent, $length - 8);
            $markers[] = $this->marker('vent', 'چاک پشت', 0, $length - $vent, 0, $length);
            $meta['vent'] = round($vent, 2);
        }

        return $this->syncFullness($this->piece([
            'code' => $o['code'] ?? ($isFront ? 'skirt-front' : 'skirt-back'),
            'name' => $o['name'] ?? ($isFront ? 'دامن جلو' : 'دامن پشت'),
            'layer' => $o['layer'] ?? (($o['count_waist'] ?? true) === false ? 'lining' : 'outer'),
            'cut_quantity' => (int) ($o['cut_quantity'] ?? ($overlap > 0.01 ? 2 : 1)),
            'on_fold' => $overlap <= 0.01,
            'mirror' => (bool) ($o['mirror'] ?? ($overlap > 0.01)),
            'outline' => $outline,
            'grainline' => $this->grainline($waistX * 0.5, 3, $length - 3),
            'darts' => $darts,
            'pleats' => $pleats,
            'notches' => [
                $this->notch($quarterHip, $hipY, 1, 'نشانه باسن روی درز پهلو', 'hip'),
            ],
            'markers' => $markers,
            'meta' => $meta,
        ]));
    }

    /**
     * پنل مستطیلی (چین‌دار، پیلی‌دار، طبقه‌ای).
     *
     * گزینه‌ها: width (پهنای پارچه)، length، finished (سهم تمام‌شده از کمر)،
     * top_edge (برچسب لبه بالا)، fullness، part، on_fold، cut_quantity.
     *
     * @return array<string, mixed>
     */
    protected function rectPanel(array $o): array
    {
        $width = max(3.0, (float) ($o['width'] ?? 40));
        $length = max(5.0, (float) ($o['length'] ?? 45));
        $onFold = (bool) ($o['on_fold'] ?? true);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($width, 0),
            Geometry::point($width, $length),
            Geometry::point(0, $length),
        ];

        $meta = [
            'part' => $o['part'] ?? 'skirt_panel',
            'edges' => [$o['top_edge'] ?? 'waist', 'side', 'hem', $onFold ? 'default' : 'side'],
            'fold_edges' => $onFold ? [3] : [],
            'side' => $o['side'] ?? 'front',
            'waist_edges' => [0],
            'side_edges' => $onFold ? [1] : [1, 3],
            'hem_edges' => [2],
            'length' => round($length, 2),
            'fullness' => $o['fullness'] ?? [],
        ];

        foreach (['waist_target', 'waist_finished', 'notes', 'hip_y'] as $key) {
            if (isset($o[$key])) {
                $meta[$key] = $o[$key];
            }
        }

        return $this->syncFullness($this->piece([
            'code' => $o['code'] ?? 'panel',
            'name' => $o['name'] ?? 'پنل دامن',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 1),
            'on_fold' => $onFold,
            'mirror' => (bool) ($o['mirror'] ?? false),
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 2, $length - 2),
            'pleats' => $o['pleats'] ?? [],
            'notches' => $o['notches'] ?? [],
            'markers' => array_merge([
                $this->marker('grain_top', 'خط کمر', 0, 0, $width),
            ], $o['markers'] ?? []),
            'meta' => $meta,
        ]));
    }

    /**
     * پنل مستطیلی پیلی‌دار.
     *
     * حساب پیلی صریح است: پارچه = اندازه تمام‌شده + تعداد پیلی × جای هر پیلی.
     * جای هر پیلی برای پیلی تیغه‌ای ۲×عمق و برای پیلی جعبه‌ای ۴×عمق است. چون کمر
     * از باسن باریک‌تر است، هر پیلی در خط کمر کمی عمیق‌تر بسته می‌شود؛ همان
     * اختلاف در waist_takeup ثبت شده است.
     *
     * @return array<string, mixed>
     */
    protected function pleatedRectPanel(array $o): array
    {
        $width = max(6.0, (float) ($o['width'] ?? 40));
        $length = max(10.0, (float) ($o['length'] ?? 60));
        $count = max(1, (int) ($o['pleats'] ?? 4));
        $depth = max(0.5, (float) ($o['depth'] ?? 4));
        $finishedWaist = max(1.0, (float) ($o['finished_waist'] ?? ($width * 0.6)));
        $style = (string) ($o['style'] ?? 'knife');
        $spacing = $width / $count;
        $waistTakeup = ($width - $finishedWaist) / $count;

        $labels = ['knife' => 'پیلی تیغه‌ای', 'box' => 'پیلی جعبه‌ای', 'inverted' => 'پیلی جعبه‌ای برعکس', 'accordion' => 'پیلی آکاردئونی'];
        $label = $labels[$style] ?? 'پیلی';

        $pleats = [];
        $notches = [];

        for ($i = 0; $i < $count; $i++) {
            $x = ($i + 0.5) * $spacing;
            $pleats[] = [
                'label' => $label.' '.$this->fa($i + 1),
                'style' => $style,
                'depth' => round($depth, 2),
                'intake' => round($waistTakeup, 2),
                'from' => Geometry::point($x, 0),
                'to' => Geometry::point($x, $length),
            ];
            $notches[] = $this->notch($x, 0, 0, 'خط '.$label, 'pleat');
        }

        return $this->rectPanel(array_merge($o, [
            'width' => $width,
            'length' => $length,
            'pleats' => $pleats,
            'notches' => $notches,
            'waist_finished' => round($finishedWaist, 2),
            'fullness' => array_merge([
                $this->fullness('pleat', 0, $width, $finishedWaist, [
                    'label' => $label,
                    'style' => $style,
                    'count' => $count,
                    'depth' => round($depth, 2),
                    'waist_takeup' => round($waistTakeup, 2),
                ]),
            ], $o['fullness'] ?? []),
        ]));
    }

    /**
     * شعاع کمر دامن کلوش.
     *
     * کمانِ کمر باید دقیقاً به اندازه دور کمر باشد:
     *   طول کمان = کسر دایره × ۲π × شعاع = دور کمر  ⇒  شعاع = دور کمر ÷ (۲π × کسر دایره)
     * پس کلوش کامل شعاع W÷۲π، نیم‌کلوش W÷π و ربع‌کلوش ۲W÷π می‌گیرد.
     */
    protected function circleRadius(float $waist, float $fraction): float
    {
        $fraction = max(0.05, min(1.0, $fraction));

        return $waist / (2 * M_PI * $fraction);
    }

    /**
     * نقطه‌های یک کمان دایره حول مبدأ؛ زاویه صفر رو به پایین است.
     *
     * کمان به تکه‌های حداکثر ۱۵ درجه شکسته می‌شود و هر تکه یک منحنی درجه‌دو با
     * نقطه کنترلِ محل برخورد دو مماس است؛ خطای طول این تقریب کمتر از ۰٫۰۱٪ است.
     *
     * @return array<int, array<string, float|bool>>
     */
    protected function arcPoints(
        float $radius,
        float $from,
        float $to,
        float $originX = 0.0,
        float $originY = 0.0,
        bool $includeFirst = true,
    ): array {
        $sweep = $to - $from;
        $chunks = max(1, (int) ceil(abs($sweep) / deg2rad(15)));
        $step = $sweep / $chunks;
        $at = fn (float $angle, float $r) => [$originX + ($r * sin($angle)), $originY + ($r * cos($angle))];

        $points = [];

        if ($includeFirst) {
            [$x, $y] = $at($from, $radius);
            $points[] = Geometry::point($x, $y);
        }

        $controlRadius = $radius / cos(abs($step) / 2);

        for ($i = 1; $i <= $chunks; $i++) {
            $end = $from + ($step * $i);
            [$cx, $cy] = $at($end - ($step / 2), $controlRadius);
            [$x, $y] = $at($end, $radius);
            $points[] = Geometry::curve($x, $y, $cx, $cy);
        }

        return $points;
    }

    /** تعداد لبه‌هایی که arcPoints() برای این کمان می‌سازد. */
    protected function arcEdgeCount(float $sweep): int
    {
        return max(1, (int) ceil(abs($sweep) / deg2rad(15)));
    }

    /**
     * قطعه کلوش: یک قاچ دایره که مرکز جلو (یا پشت) آن روی تای پارچه است.
     *
     * $fraction کسر دایره کل لباس است و هر قطعه یک‌چهارمِ آن کسر را می‌پوشاند
     * (نیمِ جلو یا نیمِ پشت که دولا بریده می‌شود).
     *
     * @return array<string, mixed>
     */
    protected function circlePanel(array $o): array
    {
        $waist = max(20.0, (float) ($o['waist'] ?? 78));
        $fraction = max(0.05, min(1.0, (float) ($o['fraction'] ?? 1.0)));
        $length = max(10.0, (float) ($o['length'] ?? 60));
        $isFront = ($o['side'] ?? 'front') === 'front';
        $radius = $this->circleRadius($waist, $fraction);
        $sweep = $fraction * M_PI / 2;
        $outer = $radius + $length;

        $waistArc = $this->arcPoints($radius, 0, $sweep);
        $hemArc = $this->arcPoints($outer, $sweep, 0, 0, 0, false);

        $outline = array_merge(
            $waistArc,
            [Geometry::point($outer * sin($sweep), $outer * cos($sweep))],
            $hemArc,
        );

        $arcEdges = $this->arcEdgeCount($sweep);
        $edges = array_merge(
            array_fill(0, $arcEdges, 'waist'),
            ['side'],
            array_fill(0, $arcEdges, 'hem'),
            ['default'],
        );

        $waistEdges = range(0, $arcEdges - 1);
        $hemEdges = range($arcEdges + 1, ($arcEdges * 2));
        $count = count($outline);

        return $this->syncFullness($this->piece([
            'code' => $o['code'] ?? ($isFront ? 'skirt-front' : 'skirt-back'),
            'name' => $o['name'] ?? ($isFront ? 'دامن جلو' : 'دامن پشت'),
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline(min(2.0, $radius * 0.25), $radius + 3, $outer - 3),
            'markers' => [
                $this->marker($isFront ? 'cf' : 'cb', $isFront ? 'خط مرکز جلو' : 'خط مرکز پشت', 0, $radius, 0, $outer),
            ],
            'notches' => [
                $this->notch($radius * sin($sweep), $radius * cos($sweep), $arcEdges, 'نشانه درز پهلو', 'side'),
            ],
            'meta' => array_merge([
                'part' => $o['part'] ?? ($isFront ? 'skirt_front' : 'skirt_back'),
                'edges' => $edges,
                'fold_edges' => [$count - 1],
                'side' => $isFront ? 'front' : 'back',
                'waist_edges' => $waistEdges,
                'side_edges' => [$arcEdges],
                'hem_edges' => $hemEdges,
                'waist_target' => round($o['waist_target'] ?? $waist, 3),
                'waist_finished' => round($o['waist_finished'] ?? ($waist / 4), 2),
                'circle_fraction' => $fraction,
                'waist_radius' => round($radius, 2),
                'hem_radius' => round($outer, 2),
                'length' => round($length, 2),
                'fullness' => $o['fullness'] ?? [],
                'notes' => array_merge([
                    'شعاع کمر از خود اندازه کمر حساب شده است: '.$this->fa(round($radius, 1))
                        .' سانتی‌متر = '.$this->fa(round($waist, 1)).' ÷ (۲π × '.$this->fa($fraction).').',
                ], $o['notes'] ?? []),
            ], $o['meta'] ?? []),
        ]));
    }

    /**
     * کمربند (یا نوار کش) به اندازه یک دور کمرِ داده‌شده.
     *
     * @return array<string, mixed>
     */
    protected function bandPiece(float $girth, array $o = []): array
    {
        $height = max(1.5, (float) ($o['height'] ?? 4));
        $overlap = (float) ($o['overlap'] ?? 3.5);
        $half = max(6.0, ($girth / 2) + $overlap);

        return $this->piece([
            'code' => $o['code'] ?? 'waistband',
            'name' => $o['name'] ?? 'کمربند',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 2),
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($half, 0),
                Geometry::point($half, $height * 2),
                Geometry::point(0, $height * 2),
            ],
            'grainline' => $this->grainline($half * 0.5, 1, ($height * 2) - 1),
            'markers' => [$this->marker('fold', 'خط تای کمربند', 0, $height, $half)],
            'meta' => [
                'part' => $o['part'] ?? 'waistband',
                'edges' => ['waist', 'side', 'waist', 'side'],
                'fold_edges' => [],
                'interfacing' => (bool) ($o['interfacing'] ?? true),
                // کمربند به اندازه رویهم‌آمدن بلندتر از دور کمر بریده می‌شود و
                // همان رویهم‌آمدن باید با چیزی بسته شود؛ کمینه‌اش یک قزن است.
                'notions' => $overlap > 0.5
                    ? [['type' => 'hook', 'label' => 'قزن کمربند', 'count' => 1]]
                    : [],
                'band_girth' => round($girth, 2),
                'band_overlap' => round($overlap, 2),
                'notes' => $o['notes'] ?? [],
            ],
        ]);
    }

    /**
     * یک ترک دامن (پنل گوه‌ای).
     *
     * گزینه‌ها: waist_w، hip_w، hem_w (پهنای کامل ترک)، length، hip_y، half
     * (نیم‌ترک روی تای پارچه)، cut_quantity، code، name، slit (بلندی چاک گودت).
     *
     * @return array<string, mixed>
     */
    protected function gorePanel(array $o): array
    {
        $length = max(15.0, (float) ($o['length'] ?? 60));
        $hipY = min(max(8.0, (float) ($o['hip_y'] ?? 21)), $length * 0.55);
        $half = (bool) ($o['half'] ?? false);
        $waistHalf = max(1.0, ((float) ($o['waist_w'] ?? 20)) / 2);
        $hipHalf = max(1.2, ((float) ($o['hip_w'] ?? 26)) / 2);
        $hemHalf = max(1.2, ((float) ($o['hem_w'] ?? 34)) / 2);

        $profile = fn (float $yHem) => [
            Geometry::curve($hipHalf, $hipY, $waistHalf + (($hipHalf - $waistHalf) * 0.55), $hipY * 0.4),
            Geometry::point($hemHalf, $yHem),
        ];

        $build = function (float $yHem) use ($profile, $waistHalf, $hipHalf, $hemHalf, $hipY, $length, $half) {
            $right = $profile($yHem);

            if ($half) {
                return [array_merge(
                    [Geometry::point(0, 0), Geometry::point($waistHalf, 0)],
                    $right,
                    [Geometry::curve(0, $length, $hemHalf * 0.5, $length)],
                ), ['waist', 'side', 'side', 'hem', 'default']];
            }

            $points = array_merge(
                [
                    Geometry::curve(-$waistHalf, 0, -($waistHalf + (($hipHalf - $waistHalf) * 0.55)), $hipY * 0.4),
                    Geometry::point($waistHalf, 0),
                ],
                $right,
                [
                    Geometry::curve(0, $length, $hemHalf * 0.5, $length),
                    Geometry::curve(-$hemHalf, $yHem, -$hemHalf * 0.5, $length),
                    Geometry::curve(-$hipHalf, $hipY, -$hemHalf, $hipY + (($yHem - $hipY) * 0.5)),
                ],
            );

            return [$points, ['waist', 'side', 'side', 'hem', 'hem', 'side', 'side']];
        };

        $yHem = $this->fitSeamLength(
            fn (float $y) => Geometry::edgesLength($build($y)[0], [1, 2]),
            $length,
            $length * 0.3,
            $length,
        );

        [$outline, $edges] = $build($yHem);
        $count = count($outline);
        $slit = max(0.0, (float) ($o['slit'] ?? 0));

        $notches = [$this->notch($hipHalf, $hipY, 1, 'نشانه باسن روی درز ترک', 'hip')];
        $markers = [$this->marker('hip', 'خط باسن', $half ? 0 : -$hipHalf, $hipY, $hipHalf)];

        if ($slit > 0.5) {
            $slitY = max($hipY + 2, $length - $slit);
            $notches[] = $this->notch(
                Geometry::pointOnEdge($outline, 2, max(0.0, min(1.0, ($slitY - $hipY) / max(0.1, $yHem - $hipY))))['x'],
                $slitY,
                2,
                'سر چاک گودت',
                'godet',
            );
            $markers[] = $this->marker('godet', 'چاک گودت', $hipHalf, $slitY, $hemHalf, $yHem);
        }

        return $this->piece([
            'code' => $o['code'] ?? 'gore',
            'name' => $o['name'] ?? 'ترک دامن',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 1),
            'on_fold' => $half,
            'mirror' => (bool) ($o['mirror'] ?? false),
            'outline' => $outline,
            'grainline' => $this->grainline($half ? $waistHalf * 0.4 : 0, 3, $length - 3),
            'notches' => $notches,
            'markers' => $markers,
            'meta' => array_merge([
                'part' => $o['part'] ?? 'skirt_panel',
                'edges' => $edges,
                'fold_edges' => $half ? [$count - 1] : [],
                'side' => $o['side'] ?? 'front',
                'waist_edges' => [0],
                'side_edges' => $half ? [1, 2] : [1, 2, 5, 6],
                'hem_edges' => $half ? [3] : [3, 4],
                'waist_finished' => round($half ? $waistHalf : $waistHalf * 2, 2),
                'hip_y' => round($hipY, 2),
                'length' => round($length, 2),
                'seam_length' => Geometry::edgesLength($outline, [1, 2]),
                'seam_group' => $o['seam_group'] ?? 'skirt',
                'gore_slit' => round($slit, 2),
                'fullness' => [],
            ], $o['meta'] ?? []),
        ]);
    }

    /**
     * گودت: قاچ دایره‌ای که در چاک درز دوخته می‌شود.
     *
     * شعاع قاچ همان بلندی چاک است و زاویه آن از پهنای دم گودت درمی‌آید
     * (θ = پهنا ÷ بلندی)، پس طول کمان دم دقیقاً همان پهنای خواسته‌شده است و دو
     * لبه گودت دقیقاً به اندازه چاک درمی‌آید.
     *
     * @return array<string, mixed>
     */
    protected function godetPiece(float $height, float $width, array $o = []): array
    {
        $height = max(6.0, $height);
        $width = max(4.0, $width);
        $sweep = min(M_PI, $width / $height);
        $arc = $this->arcPoints($height, -$sweep / 2, $sweep / 2);
        $outline = array_merge([Geometry::point(0, 0)], $arc);
        $arcEdges = $this->arcEdgeCount($sweep);

        return $this->piece([
            'code' => $o['code'] ?? 'godet',
            'name' => $o['name'] ?? 'گودت',
            'cut_quantity' => (int) ($o['cut_quantity'] ?? 1),
            'outline' => $outline,
            'grainline' => $this->grainline(0, $height * 0.35, $height - 1),
            'notches' => [$this->notch(0, 0, 0, 'نوک گودت روی سر چاک', 'godet')],
            'meta' => [
                'part' => 'godet',
                'edges' => array_merge(['side'], array_fill(0, $arcEdges, 'hem'), ['side']),
                'fold_edges' => [],
                'side_edges' => [0, $arcEdges + 1],
                'hem_edges' => range(1, $arcEdges),
                'godet_height' => round($height, 2),
                'godet_width' => round($width, 2),
                'fullness' => [],
                'notes' => [
                    'گودت به بلندی '.$this->fa(round($height, 1)).' سانتی‌متر در چاک درز دوخته می‌شود و '
                        .$this->fa(round($width, 1)).' سانتی‌متر به دور دم دامن اضافه می‌کند.',
                ],
            ],
        ]);
    }

    /**
     * ثبت گشادی اضافه پارچه روی یک لبه.
     *
     * @return array<string, mixed>
     */
    protected function fullness(string $type, int $edge, float $fabric, float $finished, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'edge' => $edge,
            'fabric' => round($fabric, 2),
            'finished' => round($finished, 2),
            'takeup' => round($fabric - $finished, 2),
            'ratio' => $finished > 0.01 ? round($fabric / $finished, 3) : 0.0,
        ], $extra);
    }

    /**
     * ترجمه meta.fullness به زبان مشترک قطعه‌ها.
     *
     * meta.fullness زبان خودِ دامن است و رندر و برگه فنی آن را می‌خوانند، اما
     * هر اندازه‌گیر عمومیِ دیگری در سامانه — PieceOps::seamLength، سازنده روابط
     * دوخت، ممیزی کاتالوگ، دوختن دامن به بالاتنه — فقط meta.gathers و meta.pleats
     * را می‌بیند. تا وقتی این دو یکی نشوند، یک پنل چین‌دار پهنای خام پارچه‌اش را
     * اندازه کمر تمام‌شده گزارش می‌کند؛ همان چیزی که کمر دامن چین‌دار (دیرندل) را
     * دو برابر و کمر لباس امپایر را جفت‌وجور نشدنی نشان می‌داد.
     *
     * «هم‌پوشانی» عمداً ترجمه نمی‌شود: پارچه‌اش در درز خورده نمی‌شود، روی پنل
     * روبه‌رو می‌افتد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function syncFullness(array $piece): array
    {
        foreach ($piece['meta']['fullness'] ?? [] as $entry) {
            $type = (string) ($entry['type'] ?? '');
            $takeup = round((float) ($entry['takeup'] ?? 0), 3);
            $edge = (int) ($entry['edge'] ?? 0);

            if ($takeup <= 0.01 || ! in_array($type, ['gather', 'pleat'], true)) {
                continue;
            }

            $kind = $type === 'pleat' ? 'pleats' : 'gathers';

            // اگر خود مدل قبلاً ثبتش کرده، دوباره حساب نمی‌کنیم
            if (FullnessRecorder::amountOn($piece, $edge, $kind) > 0.01) {
                continue;
            }

            $label = (string) ($entry['label'] ?? ($kind === 'pleats' ? 'پیلی' : 'چین'));
            $style = (string) ($entry['style'] ?? 'knife');
            $count = max(1, (int) ($entry['count'] ?? 1));

            // روی دامن کلوش، کمر یک لبه نیست؛ کمانی است از چند لبه. سهم هر لبه
            // به نسبت بلندی خودش کم می‌شود، وگرنه همه چین روی یک تکه کمان می‌افتد.
            $edges = $this->fullnessEdges($piece, $edge);
            $span = 0.0;

            foreach ($edges as $index) {
                $span += Geometry::edgeLength($piece['outline'], $index);
            }

            if ($span <= 0.01) {
                continue;
            }

            foreach ($edges as $index) {
                $share = $takeup * (Geometry::edgeLength($piece['outline'], $index) / $span);

                $piece = $kind === 'gathers'
                    ? FullnessRecorder::gathers($piece, $index, $share, ['label' => $label])
                    : FullnessRecorder::pleats($piece, $index, $share, [
                        'label' => $label,
                        'type' => in_array($style, FullnessRecorder::PLEAT_TYPES, true) ? $style : 'knife',
                        'count' => max(1, (int) round($count / count($edges))),
                    ]);
            }
        }

        return $piece;
    }

    /**
     * لبه‌هایی که یک گشادی روی آن‌ها پخش می‌شود.
     *
     * اگر لبه ثبت‌شده عضو یک گروه لبه (کمر، دم، پهلو) باشد، گشادی مال کل آن درز
     * است نه یک تکه‌اش.
     *
     * @param  array<string, mixed>  $piece
     * @return array<int, int>
     */
    protected function fullnessEdges(array $piece, int $edge): array
    {
        foreach (['waist_edges', 'hem_edges', 'side_edges'] as $group) {
            $edges = array_map('intval', $piece['meta'][$group] ?? []);

            if (count($edges) > 1 && in_array($edge, $edges, true)) {
                return $edges;
            }
        }

        return [$edge];
    }

    /**
     * بالا آوردن گوشه دم تا هم‌اندازه شدن درز با قد مرکز.
     *
     * تابع $length با بالا رفتن گوشه کوتاه می‌شود، پس یک دوبخشی ساده کافی است.
     */
    protected function fitSeamLength(callable $length, float $target, float $low, float $high): float
    {
        if ($length($high) <= $target) {
            return $high;
        }

        for ($i = 0; $i < 28; $i++) {
            $mid = ($low + $high) / 2;

            if ($length($mid) > $target) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return round(($low + $high) / 2, 3);
    }

    /** عدد فارسی برای یادداشت‌ها. */
    protected function fa(float|int|string $value): string
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }
}
