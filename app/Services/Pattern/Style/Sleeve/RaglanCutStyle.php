<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * پایه آستین‌هایی که سرشانه بالاتنه را می‌برند و به سر آستین می‌دهند: رگلان و سدل.
 *
 * کار هر دو یکی است و فقط جای برش فرق می‌کند:
 *
 *   ۱. روی جلو و پشت یک خط برش از خط یقه تا حلقه آستین کشیده می‌شود. تکه بالای این
 *      خط — که سرگردن، سرشانه و بالای حلقه را در خود دارد — از بالاتنه جدا می‌شود.
 *   ۲. بالاتنه می‌ماند با: بخشی از خط یقه، درز رگلان، و باقی حلقه آستین تا زیر بغل.
 *      درز سرشانه دیگر روی بالاتنه نیست.
 *   ۳. آستین با «پرگار» ساخته می‌شود: نقطه سرشانه سر آستین است، سرگردن به فاصله
 *      دقیقاً همان طول سرشانه بالای آن می‌نشیند، سر خط یقه با همان زاویه‌ای که روی
 *      بالاتنه داشت از سرگردن جدا می‌شود، و نقطه رسیدن درز رگلان به حلقه از برخورد
 *      دو کمان (طول درز رگلان از سر یقه، و طول حلقه پایین از زیر بغل) پیدا می‌شود.
 *      پس هر چهار طولِ دوختنی عیناً حفظ می‌شود و دو درز روی هم پیاده می‌شوند.
 *   ۴. ارتفاع سر آستین تا جایی بالا برده می‌شود که این دو کمان به هم برسند؛ همین
 *      است که آستین رگلان همیشه سرِ کوتاه‌تر و افتاده‌تری از آستین حلقه‌ای دارد.
 */
abstract class RaglanCutStyle extends SleeveBodiceStyle
{
    /** بیشترین نسبت وتر به مجموع دو درز؛ نزدیک ۱ یعنی درز رگلان تقریباً صاف. */
    protected const SEAM_MARGIN = 0.93;

    /**
     * جای برش روی هر قطعه.
     *
     * @return array{neck: float, armhole: float} فاصله روی خط یقه از سرگردن، و
     *                                            فاصله روی حلقه از زیر بغل که روی بالاتنه می‌ماند
     */
    abstract protected function cutPlan(array $anchors, array $params): array;

    /** نام درز تازه برای یادداشت‌ها. */
    protected function seamName(): string
    {
        return 'درز رگلان';
    }

    protected function supportsSleeve(array $pieces, array $context): true|string
    {
        $sides = [];

        foreach ($this->armholePieces($pieces) as $index) {
            $anchors = $this->bodiceAnchors($pieces[$index]);

            if ($anchors !== null) {
                $sides[$anchors['side']] = true;
            }
        }

        if (count($sides) < 2) {
            return $this->label().' هم جلو و هم پشت لازم دارد، چون سر آستین از سرشانه هر دو ساخته می‌شود؛ '
                .'در این الگو فقط «'.(isset($sides['front']) ? 'جلو' : 'پشت').'» حلقه آستین دارد.';
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     |  اجرا
     * ------------------------------------------------------------------- */

    public function apply(array $pieces, array $context): array
    {
        $p = $this->params($context);
        $notes = [];
        $cuts = [];

        foreach ($this->armholePieces($pieces) as $index) {
            $anchors = $this->bodiceAnchors($pieces[$index]);

            if ($anchors === null) {
                continue;
            }

            $cut = $this->cutBodice($pieces[$index], $anchors, $p);
            $pieces[$index] = $cut['piece'];
            $cuts[$anchors['side']] = $cut;
        }

        $sleeve = $this->buildSleeve($cuts, $p, $context);
        $walk = $this->walkSeams($pieces, $sleeve['pieces'], $cuts);

        foreach ($cuts as $side => $cut) {
            $label = $side === 'front' ? 'جلو' : 'پشت';
            $notes[] = 'حلقه آستین '.$label.' برداشته شد و سرشانه به آستین منتقل شد؛ '
                .$this->seamName().' '.$label.' '.Format::cm($cut['raglan'], 1)
                .' و حلقه پایینِ مانده '.Format::cm($cut['lower'], 1).'.';
        }

        $notes[] = 'سرشانه منتقل‌شده '.Format::cm($cuts['front']['shoulder'], 1).' (جلو) و '
            .Format::cm($cuts['back']['shoulder'], 1).' (پشت) بود؛ روی آستین '
            .Format::cm($sleeve['shoulder'], 1).' درفت شد.';

        $notes[] = 'ارتفاع سر آستین '.Format::cm($sleeve['cap_height'], 1).' درآمد'
            .($sleeve['cap_limited']
                ? ' — بیش از این بالا نمی‌رفت، چون آن‌وقت '.$this->seamName().' و حلقه پایین به هم نمی‌رسیدند.'
                : '.');

        foreach ($sleeve['notes'] as $note) {
            $notes[] = $note;
        }

        $notes[] = $walk['matched']
            ? 'درزهای رگلان و حلقه پایین روی هم پیاده شدند و همه با اختلاف کمتر از '
                .Format::cm(0.1, 1).' جور درآمدند.'
            : 'در پیاده‌کردن درزها بیشترین اختلاف '.Format::cm($walk['worst'], 2)
                .' ماند؛ پیش از برش این درز را با خط‌کش منحنی راست کنید.';

        return [
            'pieces' => array_merge(array_values($pieces), $sleeve['pieces']),
            'notes' => $notes,
            'meta' => [
                'sleeve' => [
                    'style' => static::key(),
                    'head' => $p['head'] ?? 'one_piece',
                    'cap_height' => $sleeve['cap_height'],
                    'bicep_width' => $sleeve['width'],
                    'shoulder_length' => $sleeve['shoulder'],
                    'seams' => $walk['seams'],
                    'matched' => $walk['matched'],
                ],
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     |  بریدن بالاتنه
     * ------------------------------------------------------------------- */

    /**
     * خط رگلان را روی یک قطعه بالاتنه می‌برد.
     *
     * @return array<string, mixed>
     */
    protected function cutBodice(array $piece, array $a, array $p): array
    {
        $outline = array_values($piece['outline']);
        $plan = $this->cutPlan($a, $p);
        $centroid = $a['centroid'];

        // ۱) سر خط رگلان روی خط یقه؛ بیش از نیمه خط یقه را نمی‌گیرد، وگرنه چیزی از
        //    یقه روی بالاتنه نمی‌ماند و خط رگلان روی خودش تا می‌خورد
        $neckSpan = max(1.5, min($a['neck_length'] * 0.55, $plan['neck']));
        $tNeck = $this->tAtLength($outline, $a['neck_edge'], $a['neck_length'] - $neckSpan);
        [$neckCut, , $neckPoint] = $this->splitEdge($outline, $a['neck_edge'], $tNeck);
        $neckArc = $a['neck_length'] - Geometry::edgeLength(
            [Geometry::point($a['cf']['x'], $a['cf']['y']), $neckCut],
            0,
        );

        // ۲) رسیدن خط رگلان به حلقه آستین
        $keep = max(2.0, min($a['armhole_length'] - 1.5, $plan['armhole']));
        $join = $this->alongChain($outline, $a['armhole_edges'], $keep, true);
        $upperArc = $a['armhole_length'] - $keep;

        // ۳) زیر بغل تازه: به اندازه خواسته‌شده روی درز پهلو پایین‌تر
        $drop = max(0.0, min($a['side_length'] * 0.35, (float) $p['underarm_drop']));
        $underarm = $a['underarm'];
        $sideRest = $outline[$a['side_edge'] + 1];

        if ($drop > 0.05) {
            $tSide = $this->tAtLength($outline, $a['side_edge'], $drop);
            [, $sideRest, $underarm] = $this->splitEdge($outline, $a['side_edge'], $tSide);
        }

        // ۴) خط رگلان و حلقه پایین، با خمیدگی خواسته‌شده
        $raglanEnd = $this->curveTo($neckPoint, $join, (float) $p['raglan_curve'], $a['tip']);
        $lowerEnd = $this->curveTo($join, $underarm, (float) $p['armhole_scoop'], $centroid);

        $raglanArc = Geometry::edgeLength([Geometry::point($neckPoint['x'], $neckPoint['y']), $raglanEnd], 0);
        $lowerArc = Geometry::edgeLength([Geometry::point($join['x'], $join['y']), $lowerEnd], 0);

        // ۵) نشاندن مسیر تازه جای سرگردن، نوک سرشانه و زیر بغل
        $outline[$a['side_edge'] + 1] = $sideRest;
        $piece['outline'] = $outline;

        // از سرگردن تا زیر بغل (سرشانه و همه لبه‌های حلقه) با سه نقطه تازه جایگزین می‌شود
        $piece = $this->replacePoints(
            $piece,
            $a['neck_edge'] + 1,
            $a['side_edge'] - $a['neck_edge'],
            [$neckCut, $raglanEnd, $lowerEnd],
            ['armhole', 'armhole', 'side'],
        );

        $side = $a['side'];
        $label = $side === 'front' ? 'جلو' : 'پشت';

        $piece = $this->dropArmholeNotches($this->dropNotches($piece, ['shoulder']));
        $raglanEdge = $a['neck_edge'] + 1;
        $lowerEdge = $a['neck_edge'] + 2;
        $raglanMid = Geometry::pointOnEdge($piece['outline'], $raglanEdge, 0.5);
        $lowerMid = Geometry::pointOnEdge($piece['outline'], $lowerEdge, 0.5);

        $piece['notches'][] = $this->notch($raglanMid['x'], $raglanMid['y'], $raglanEdge, 'وسط '.$this->seamName().' '.$label, 'raglan_'.$side);
        $piece['notches'][] = $this->notch($lowerMid['x'], $lowerMid['y'], $lowerEdge, 'وسط حلقه پایین '.$label, 'underarm_'.$side);

        $piece['meta']['armhole_edge'] = $lowerEdge;
        $piece['meta']['armhole_length'] = round($raglanArc + $lowerArc, 2);
        $piece['meta']['raglan'] = [
            'style' => static::key(),
            'seam' => round($raglanArc, 2),
            'lower_armhole' => round($lowerArc, 2),
            'neck_taken' => round($neckArc, 2),
            'shoulder_taken' => $a['shoulder_length'],
            'upper_armhole_taken' => round($upperArc, 2),
            'underarm_drop' => round($drop, 2),
        ];

        $piece = Geometry::normalizePiece($piece);

        return [
            'piece' => $piece,
            'side' => $side,
            'raglan' => round($raglanArc, 3),
            'raglan_chord' => round($this->len($this->vec($neckPoint, $join)), 3),
            'lower' => round($lowerArc, 3),
            'lower_chord' => round($this->len($this->vec($join, $underarm)), 3),
            'neck' => round($neckArc, 3),
            'neck_chord' => round($this->len($this->vec($neckPoint, $a['snp'])), 3),
            'shoulder' => $a['shoulder_length'],
            'upper' => round($upperArc, 3),
            'drop' => round($drop, 3),
            'raglan_edge' => $raglanEdge,
            'lower_edge' => $lowerEdge,
            // زاویه‌ای که خط یقه در سرگردن با سرشانه می‌سازد؛ روی آستین عیناً تکرار می‌شود
            'neck_angle' => round($this->angleBetween(
                $this->vec($a['snp'], $a['tip']),
                $this->vec($a['snp'], $neckPoint),
            ), 3),
        ];
    }

    /* ---------------------------------------------------------------------
     |  ساختن آستین
     * ------------------------------------------------------------------- */

    /**
     * @param  array<string, array<string, mixed>>  $cuts
     * @return array<string, mixed>
     */
    protected function buildSleeve(array $cuts, array $p, array $context): array
    {
        $front = $cuts['front'];
        $back = $cuts['back'];
        $notes = [];

        $bicep = $this->measurement($context, 'bicep', 28.5);
        $wrist = $this->measurement($context, 'wrist', 16.5);
        $arm = $this->measurement($context, 'arm_length', 58.5);
        $ease = (float) ($context['ease']['bicep'] ?? 4);

        $width = max($bicep * 0.9, $bicep + $ease);
        $shoulder = round(($front['shoulder'] + $back['shoulder']) / 2, 3);
        $head = (string) ($p['head'] ?? 'one_piece');
        $bend = $head === 'two_piece' ? (float) $p['shoulder_shaping'] / 2 : 0.0;
        $bend = min($bend, $shoulder * 0.35);
        $dartIntake = $head === 'dart' ? (float) $p['shoulder_shaping'] : 0.0;

        $hemHalf = max(4.0, min($width / 2 - 1.0, ($wrist + (float) $p['hem_ease']) / 2));

        // ارتفاع سر آستین: بلندترین ارتفاعی که دو کمان رگلان هنوز به هم می‌رسند
        $maxHeight = max(4.0, (float) $p['cap_softness'] * 0.42 * $width);
        $minHeight = 0.14 * $width;
        $height = $maxHeight;
        $limited = false;

        for ($step = 0; $step < 26; $step++) {
            if ($this->capFits($front, $back, $width, $height, $shoulder, $bend, $dartIntake)) {
                break;
            }

            $limited = true;
            $height = max($minHeight, $height - (($maxHeight - $minHeight) / 25));
        }

        // آستین باید به‌اندازه کافی پایین‌تر از خط بازو تمام شود، وگرنه دم آستین به حلقه می‌خورد
        $length = max($height + 10.0, $arm + (float) $p['length_extra']);
        $layout = $this->capLayout($front, $back, $width, $height, $shoulder, $bend, $dartIntake);

        if (! $layout['front']['reached'] || ! $layout['back']['reached']) {
            $notes[] = 'اندازه بازو نسبت به حلقه آستین این بالاتنه بزرگ است و دو کمان رگلان با هم '
                .'برخورد نکردند؛ نقطه رسیدن درز به حلقه تقریبی گذاشته شد. حلقه آستین را گودتر بگیرید '
                .'یا زیر بغل را بیشتر پایین بیاورید.';
        }

        $pieces = $head === 'two_piece'
            ? $this->twoPieceSleeve($layout, $front, $back, $length, $hemHalf, $p)
            : [$this->onePieceSleeve($layout, $front, $back, $length, $hemHalf, $dartIntake, $p)];

        if ($head === 'one_piece') {
            $notes[] = 'سر آستین یک‌تکه و بدون ساسون درفت شد؛ گردیِ سرشانه در دوخت با کمی جذب گرفته '
                .'می‌شود و شانه نرم‌تر از حالت ساسون‌دار می‌ایستد.';
        } elseif ($head === 'dart') {
            $notes[] = 'ساسون سرشانه به پهنای '.Format::cm($dartIntake, 1).' روی خط یقه آستین گذاشته شد؛ '
                .'با بستن آن سر آستین روی شانه قالب می‌شود و طول خط یقه عوض نمی‌شود.';
        } else {
            $top = PieceOps::walk($pieces[0], [1, 2], $pieces[1], [1, 2]);
            $notes[] = 'آستین دوتکه شد: درزی از خط یقه، از روی نقطه سرشانه، تا مچ. شکست درز روی نقطه '
                .'سرشانه '.Format::cm($p['shoulder_shaping'], 1).' گردی به شانه می‌دهد. '
                .'این درز روی هر دو تکه '.Format::cm($top['a']['seam'], 1).' شد'
                .($top['matched'] ? '.' : ' (اختلاف '.Format::cm(abs($top['difference']), 2).').');
        }

        return [
            'pieces' => $pieces,
            'notes' => $notes,
            'cap_height' => round($height, 2),
            'cap_limited' => $limited,
            'width' => round($width, 2),
            'shoulder' => $shoulder,
        ];
    }

    /** آیا با این ارتفاع، دو کمان درز رگلان و حلقه پایین به هم می‌رسند؟ */
    protected function capFits(array $front, array $back, float $width, float $height, float $shoulder, float $bend, float $dartIntake): bool
    {
        $layout = $this->capLayout($front, $back, $width, $height, $shoulder, $bend, $dartIntake);

        foreach (['front', 'back'] as $side) {
            $cut = $side === 'front' ? $front : $back;
            $reach = $cut['raglan_chord'] + $cut['lower_chord'];
            $span = $this->len($this->vec($layout[$side]['neck'], $layout[$side]['underarm']));

            if (! $layout[$side]['reached'] || $span > $reach * static::SEAM_MARGIN) {
                return false;
            }
        }

        return true;
    }

    /**
     * جای همه نقطه‌های کلیدی آستین، با نقطه سرشانه در مبدأ و محور آستین روی x = 0.
     *
     * @return array<string, mixed>
     */
    protected function capLayout(array $front, array $back, float $width, float $height, float $shoulder, float $bend, float $dartIntake): array
    {
        $snp = ['x' => 0.0, 'y' => -sqrt(max(0.01, ($shoulder * $shoulder) - ($bend * $bend)))];
        $centre = ['x' => 0.0, 'y' => $height * 0.6];
        $out = ['snp' => $snp, 'centre' => $centre];

        foreach (['front' => -1, 'back' => 1] as $side => $sign) {
            $cut = $side === 'front' ? $front : $back;
            $tip = ['x' => $bend * $sign, 'y' => 0.0];
            $shoulderDir = $this->unit($this->vec($snp, $tip));
            $neckDir = $this->rotateVector($shoulderDir, $cut['neck_angle'] * -$sign);
            $neckChord = $cut['neck_chord'] + ($dartIntake / 2);
            $neck = $this->move($snp, $neckDir, $neckChord);
            $underarm = ['x' => ($width / 2) * $sign, 'y' => $height];

            // نقطه‌ای که درز رگلان به حلقه می‌رسد باید روی سمت خودش و میان محور آستین
            // و خط بازو بماند، وگرنه دو نیمه آستین از هم رد می‌شوند
            $ideal = ['x' => 0.32 * $width * $sign, 'y' => $height * 0.55];
            $join = $this->triangulate($neck, $cut['raglan_chord'], $underarm, $cut['lower_chord'], $ideal);
            $join = $this->keepJoinOnItsSide($join, $neck, $underarm, $sign, $width, $bend);

            $out[$side] = [
                // مرجع باد کردن درزهای این طرف: بیرون از آستین، تا سر آستین محدب بماند
                'outward' => ['x' => 1.4 * $width * $sign, 'y' => $height * 0.5],
                'tip' => $tip,
                'neck' => $neck,
                'underarm' => $underarm,
                'join' => ['x' => $join['x'], 'y' => $join['y']],
                'reached' => $join['reached'] && $join['on_side'],
                'neck_target' => $cut['neck'] + ($dartIntake / 2),
            ];
        }

        return $out;
    }

    /**
     * نقطه برخورد را روی سمت خودش نگه می‌دارد.
     *
     * نقطه به سمت وترِ «سر یقه تا زیر بغل» کشیده می‌شود. چون هر نقطه‌ای روی این
     * مسیر به هر دو سر نزدیک‌تر است تا خود نقطه برخورد، طول هیچ‌کدام از دو درز خراب
     * نمی‌شود: فقط شکم درز بیشتر می‌شود و طولش سر جای خودش می‌ماند.
     *
     * @return array{x: float, y: float, reached: bool, on_side: bool}
     */
    protected function keepJoinOnItsSide(array $join, array $neck, array $underarm, int $sign, float $width, float $bend): array
    {
        $inner = ($bend + (0.10 * $width)) * $sign;
        $outer = 0.48 * $width * $sign;
        $low = min($inner, $outer);
        $high = max($inner, $outer);

        if ($join['x'] >= $low && $join['x'] <= $high) {
            return $join + ['on_side' => true];
        }

        // نقطه روی پاره‌خط «پای عمود ← نقطه برخورد» می‌لغزد تا x به بازه مجاز برسد
        $u = $this->unit($this->vec($neck, $underarm));
        $d = $this->len($this->vec($neck, $underarm));
        $along = max(0.0, min($d, $this->dot($this->vec($neck, $join), $u)));
        $foot = $this->move($neck, $u, $along);
        $target = min($high, max($low, $join['x']));
        $span = $join['x'] - $foot['x'];

        $ratio = abs($span) < 1e-6 ? 0.0 : max(0.0, min(1.0, ($target - $foot['x']) / $span));
        $onSide = $foot['x'] >= $low && $foot['x'] <= $high;

        return [
            'x' => $foot['x'] + ($span * $ratio),
            'y' => $foot['y'] + (($join['y'] - $foot['y']) * $ratio),
            'reached' => $join['reached'],
            'on_side' => $onSide || abs(($foot['x'] + ($span * $ratio)) - $target) < 0.05,
        ];
    }

    /**
     * آستین یک‌تکه (با یا بدون ساسون سرشانه).
     *
     * @return array<string, mixed>
     */
    protected function onePieceSleeve(array $layout, array $front, array $back, float $length, float $hemHalf, float $dartIntake, array $p): array
    {
        $snp = $layout['snp'];
        $centre = $layout['centre'];
        $hemCentre = ['x' => 0.0, 'y' => $length];
        $hemFront = ['x' => -$hemHalf, 'y' => $length];
        $hemBack = ['x' => $hemHalf, 'y' => $length];

        $outline = [
            Geometry::point($layout['front']['neck']['x'], $layout['front']['neck']['y']),
            $this->bowSeam($layout['front']['neck'], $snp, $layout['front']['neck_target'], $centre),
            $this->bowSeam($snp, $layout['back']['neck'], $layout['back']['neck_target'], $centre),
            $this->bowSeam($layout['back']['neck'], $layout['back']['join'], $back['raglan'], $layout['back']['outward']),
            $this->bowSeam($layout['back']['join'], $layout['back']['underarm'], $back['lower'], $layout['back']['outward']),
            Geometry::point($hemBack['x'], $hemBack['y']),
            Geometry::point($hemCentre['x'], $hemCentre['y']),
            Geometry::point($hemFront['x'], $hemFront['y']),
            Geometry::point($layout['front']['underarm']['x'], $layout['front']['underarm']['y']),
            $this->bowSeam($layout['front']['underarm'], $layout['front']['join'], $front['lower'], $layout['front']['outward']),
        ];

        $edges = ['neck', 'neck', 'armhole', 'armhole', 'side', 'hem', 'hem', 'side', 'armhole', 'armhole'];

        // لبه بسته‌شدن (از سر درز رگلان جلو به خط یقه) هم باید طول درست داشته باشد
        $outline[0] = $this->bowSeam($layout['front']['join'], $layout['front']['neck'], $front['raglan'], $layout['front']['outward']);

        $piece = $this->piece([
            'code' => $this->sleeveCode(),
            'name' => $this->sleeveName(),
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'meta' => [
                'part' => 'sleeve',
                'edges' => $edges,
                'fold_edges' => [],
                'sleeve_style' => static::key(),
                'head' => (string) ($p['head'] ?? 'one_piece'),
            ],
        ]);

        return $this->finishSleeve($piece, [
            'snp' => 1,
            'shoulder_point' => $layout['front']['tip'],
            'front_raglan_edge' => 9,
            'front_lower_edge' => 8,
            'back_raglan_edge' => 2,
            'back_lower_edge' => 3,
            'length' => $length,
            'dart' => $dartIntake,
            'front_neck_edge' => 0,
            'back_neck_edge' => 1,
        ]);
    }

    /**
     * آستین دوتکه: جلو و پشت با درزی که از یقه، از روی سرشانه، تا مچ می‌رود.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function twoPieceSleeve(array $layout, array $front, array $back, float $length, float $hemHalf, array $p): array
    {
        $pieces = [];

        foreach (['front' => -1, 'back' => 1] as $side => $sign) {
            $cut = $side === 'front' ? $front : $back;
            $half = $layout[$side];
            $snp = $layout['snp'];
            $centre = ['x' => $sign * $hemHalf * 0.35, 'y' => $layout['centre']['y']];

            $outline = [
                $this->bowSeam($half['join'], $half['neck'], $cut['raglan'], $half['outward']),
                $this->bowSeam($half['neck'], $snp, $half['neck_target'], $centre),
                Geometry::point($half['tip']['x'], $half['tip']['y']),
                Geometry::point(0.0, $length),
                Geometry::point($sign * $hemHalf, $length),
                Geometry::point($half['underarm']['x'], $half['underarm']['y']),
                $this->bowSeam($half['underarm'], $half['join'], $cut['lower'], $half['outward']),
            ];

            $piece = $this->piece([
                'code' => $this->sleeveCode().'-'.($side === 'front' ? 'front' : 'back'),
                'name' => $this->sleeveName().' '.($side === 'front' ? 'جلو' : 'پشت'),
                'cut_quantity' => 2,
                'mirror' => true,
                'outline' => $outline,
                'meta' => [
                    'part' => 'sleeve',
                    'edges' => ['neck', 'shoulder', 'side', 'hem', 'side', 'armhole', 'armhole'],
                    'fold_edges' => [],
                    'sleeve_style' => static::key(),
                    'head' => 'two_piece',
                    'side' => $side,
                ],
            ]);

            $pieces[] = $this->finishSleeve($piece, [
                'snp' => 1,
                'shoulder_point' => $half['tip'],
                'length' => $length,
                'dart' => 0.0,
                $side.'_raglan_edge' => 6,
                $side.'_lower_edge' => 5,
            ]);
        }

        return $pieces;
    }

    /** نشانه‌ها، خط راستا و خط‌های کمکی آستین. */
    protected function finishSleeve(array $piece, array $spec): array
    {
        $outline = $piece['outline'];
        $bounds = Geometry::bounds($outline);
        $notches = [];

        foreach (['front', 'back'] as $side) {
            $label = $side === 'front' ? 'جلو' : 'پشت';

            foreach ([['raglan', $this->seamName()], ['lower', 'حلقه پایین']] as [$key, $title]) {
                $edge = $spec[$side.'_'.$key.'_edge'] ?? null;

                if ($edge === null) {
                    continue;
                }

                $on = Geometry::pointOnEdge($outline, (int) $edge, 0.5);
                $pair = ($key === 'raglan' ? 'raglan_' : 'underarm_').$side;
                $notches[] = $this->notch($on['x'], $on['y'], (int) $edge, 'وسط '.$title.' '.$label, $pair);
            }
        }

        if (isset($spec['snp'])) {
            $snp = $outline[(int) $spec['snp']];
            $notches[] = $this->notch((float) $snp['x'], (float) $snp['y'], (int) $spec['snp'], 'سرگردن', 'shoulder');
        }

        $piece['notches'] = array_merge($piece['notches'] ?? [], $notches);

        $tip = $spec['shoulder_point'];
        $piece['markers'][] = $this->marker(
            'shoulder_point',
            'نقطه سرشانه',
            (float) $tip['x'] - 1.5,
            (float) $tip['y'],
            (float) $tip['x'] + 1.5,
            (float) $tip['y'],
        );

        if (($spec['dart'] ?? 0.0) > 0.05) {
            $front = (int) ($spec['front_neck_edge'] ?? 0);
            $back = (int) ($spec['back_neck_edge'] ?? 1);
            $half = ((float) $spec['dart']) / 2;
            $legA = $this->alongEdge($outline, $front, $half, true);
            $legB = $this->alongEdge($outline, $back, $half);

            $piece['darts'][] = $this->dart(
                'shoulder',
                'ساسون سرشانه آستین',
                $front,
                $legA,
                $legB,
                $tip,
                (float) $spec['dart'],
            );
        }

        $piece['grainline'] = $this->grainline(
            ($bounds[0] + $bounds[2]) / 2,
            $bounds[1] + 2.0,
            $bounds[3] - 2.0,
        );

        $piece['meta']['sleeve_length'] = round((float) $spec['length'], 2);

        return Geometry::normalizePiece($piece);
    }

    protected function sleeveName(): string
    {
        return $this->label();
    }

    protected function sleeveCode(): string
    {
        return 'raglan-sleeve';
    }

    /* ---------------------------------------------------------------------
     |  پیاده‌کردن درزها
     * ------------------------------------------------------------------- */

    /**
     * درز رگلان و حلقه پایینِ هر طرف را روی آستین پیاده می‌کند.
     *
     * @return array{seams: array<string, float>, worst: float, matched: bool}
     */
    protected function walkSeams(array $pieces, array $sleeves, array $cuts): array
    {
        $seams = [];
        $worst = 0.0;

        foreach ($cuts as $side => $cut) {
            $bodice = null;

            foreach ($pieces as $piece) {
                if (($piece['meta']['raglan']['seam'] ?? null) !== null && ($this->isFront($piece) ? 'front' : 'back') === $side) {
                    $bodice = $piece;
                }
            }

            if ($bodice === null) {
                continue;
            }

            foreach ($sleeves as $sleeve) {
                foreach ([['raglan', $cut['raglan_edge']], ['lower', $cut['lower_edge']]] as [$key, $edge]) {
                    $pair = ($key === 'raglan' ? 'raglan_' : 'underarm_').$side;
                    $sleeveEdge = $this->notchEdge($sleeve, $pair);

                    if ($sleeveEdge === null) {
                        continue;
                    }

                    $walk = PieceOps::walk($bodice, (int) $edge, $sleeve, $sleeveEdge);
                    $seams[$side.'_'.$key] = round($walk['difference'], 3);
                    $worst = max($worst, abs($walk['difference']));
                }
            }
        }

        return ['seams' => $seams, 'worst' => round($worst, 3), 'matched' => $worst <= 0.1];
    }

    /** شماره لبه‌ای که نشانه با این جفت روی آن نشسته. */
    protected function notchEdge(array $piece, string $pair): ?int
    {
        foreach ($piece['notches'] ?? [] as $notch) {
            if (($notch['pair'] ?? null) === $pair) {
                return (int) $notch['edge'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /** @return array<string, array<string, mixed>> */
    protected function sharedFields(float $shaping = 2.0): array
    {
        return [
            'underarm_drop' => [
                'label' => 'پایین‌آوردن زیر بغل', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 1.5,
                'unit' => 'سانتی‌متر',
                'hint' => 'حلقه را گودتر می‌کند؛ بیشتر یعنی راحت‌تر برای بالا بردن دست و کمی افتاده‌تر.',
            ],
            'raglan_curve' => [
                'label' => 'خمیدگی خط رگلان', 'min' => 0, 'max' => 3, 'step' => 0.25, 'default' => 0.75,
                'unit' => 'سانتی‌متر', 'hint' => 'شکم خط برش نسبت به خط راست؛ صفر یعنی خط کاملاً صاف.',
            ],
            'armhole_scoop' => [
                'label' => 'گودی حلقه پایین', 'min' => 0, 'max' => 3, 'step' => 0.25, 'default' => 1,
                'unit' => 'سانتی‌متر',
            ],
            'shoulder_shaping' => [
                'label' => 'گردی سرشانه آستین', 'min' => 0, 'max' => 5, 'step' => 0.25, 'default' => $shaping,
                'unit' => 'سانتی‌متر',
                'hint' => 'در حالت ساسون‌دار پهنای ساسون است و در حالت دوتکه شکست درز روی نقطه سرشانه.',
            ],
            'cap_softness' => [
                'label' => 'بلندی سر آستین', 'min' => 0.35, 'max' => 1, 'step' => 0.05, 'default' => 0.75,
                'hint' => 'نسبت به سر آستین حلقه‌ای؛ کمتر یعنی آستین افتاده‌تر و راحت‌تر.',
            ],
            'length_extra' => $this->lengthField(),
            'hem_ease' => [
                'label' => 'آزادی دم آستین', 'min' => 0, 'max' => 20, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function headField(string $default = 'one_piece'): array
    {
        return [
            'label' => 'سر آستین', 'type' => 'select', 'default' => $default,
            'options' => [
                'one_piece' => 'یک‌تکه ساده',
                'dart' => 'یک‌تکه با ساسون سرشانه',
                'two_piece' => 'دوتکه با درز سرشانه',
            ],
        ];
    }
}
