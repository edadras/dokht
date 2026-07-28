<?php

namespace App\Services\Pattern\Style\Sleeve;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * پایه آستین‌هایی که اصلاً حلقه ندارند: کیمونو، دولمان و بت‌وینگ.
 *
 * در این‌ها آستین دوخته نمی‌شود، بلکه از خود بالاتنه «در می‌آید»: خط سرشانه از نوک
 * شانه با زاویه دلخواه ادامه پیدا می‌کند تا سر آستین، دم آستین عمود بر همان خط
 * بسته می‌شود و از آنجا یک درز بلند به درز پهلو برمی‌گردد. پس روی قطعه دیگر
 * برچسب «حلقه آستین» نمی‌ماند و درز سرشانه تا مچ ادامه می‌یابد.
 *
 * زاویه آستین همه‌چیز را تعیین می‌کند: هرچه به افق نزدیک‌تر باشد دست راحت‌تر بالا
 * می‌رود ولی زیر بغل پارچه جمع می‌شود؛ هرچه پایین‌تر بیاید لباس قالب‌تر می‌ایستد
 * ولی دست را می‌بندد. راه‌حل کلاسیک این تنگی، لوزی زیربغل است.
 */
abstract class GrownOnSleeveStyle extends SleeveBodiceStyle
{
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
            return $this->label().' آستین را از خود تنه درمی‌آورد، پس جلو و پشت را با هم لازم دارد تا '
                .'درز زیر آستین دو طرف هم‌اندازه شود؛ در این الگو فقط یک طرف حلقه آستین دارد.';
        }

        return true;
    }

    /** نام و کد قطعه، و اینکه لوزی زیربغل در دسترس هست یا نه. */
    protected function hasGusset(array $p): bool
    {
        return (bool) ($p['gusset'] ?? false);
    }

    /* ---------------------------------------------------------------------
     |  اجرا
     * ------------------------------------------------------------------- */

    public function apply(array $pieces, array $context): array
    {
        $p = $this->params($context);
        $plans = [];

        foreach ($this->armholePieces($pieces) as $index) {
            $anchors = $this->bodiceAnchors($pieces[$index]);

            if ($anchors === null) {
                continue;
            }

            $plans[$anchors['side']] = $this->planSide($pieces[$index], $anchors, $p, $context)
                + ['index' => $index, 'anchors' => $anchors];
        }

        $notes = [];
        $extra = [];

        // زاویه آستین در جلو و پشت باید یکی باشد و از حدی که دم آستین روی تنه بیفتد تندتر نشود
        $wanted = min(array_column($plans, 'angle'));
        $angle = max(
            max(array_column($plans, 'shoulder_angle')) + 1.0,
            min($wanted, min(array_column($plans, 'max_angle'))),
        );

        if ($angle < $wanted - 0.5) {
            $notes[] = 'زاویه آستین از '.Format::number($wanted).' به '.Format::number($angle).' درجه کم شد؛ '
                .'با زاویه خواسته‌شده دم آستین روی خود تنه می‌افتاد و الگو بریدنی نمی‌ماند.';
        }

        // درز سرشانه–بالای آستین و درز زیر آستین باید در جلو و پشت هم‌اندازه دربیایند
        $topSeam = max(array_map(fn (array $plan) => $plan['shoulder'] + $plan['length'], $plans));
        $underarm = 0.0;
        $ceiling = INF;

        foreach ($plans as $side => $plan) {
            $plans[$side]['angle'] = $angle;
            $plans[$side]['length'] = $topSeam - $plan['shoulder'];
            $plans[$side] = $this->placeSleeve($plans[$side], $p);
            $underarm = max($underarm, $plans[$side]['underarm_seam']);
            $ceiling = min($ceiling, $plans[$side]['underarm_chord'] * 1.22);
        }

        // شکم درز زیر آستین نباید آن‌قدر زیاد شود که درز به خود تنه بزند
        $underarm = max(max(array_column($plans, 'underarm_chord')), min($underarm, $ceiling));

        foreach ($plans as $side => $plan) {
            $pieces[$plan['index']] = $this->growSleeve($pieces[$plan['index']], $plan, $underarm, $p);
            $label = $side === 'front' ? 'جلو' : 'پشت';
            $notes[] = 'حلقه آستین '.$label.' برداشته شد و آستین از خود تنه درآمد؛ درز سرشانه تا سر '
                .'آستین '.Format::cm($topSeam, 1).' و درز زیر آستین '.Format::cm($underarm, 1)
                .' (زیر بغل '.Format::cm($plan['drop'], 1).' پایین‌تر از حلقه اصلی).';
        }

        $walk = PieceOps::walk(
            $pieces[$plans['front']['index']],
            $this->underarmEdge($pieces[$plans['front']['index']]),
            $pieces[$plans['back']['index']],
            $this->underarmEdge($pieces[$plans['back']['index']]),
        );

        $notes[] = abs($walk['difference']) <= 0.1
            ? 'درز زیر آستین جلو و پشت روی هم پیاده شد و هر دو '.Format::cm($walk['a']['seam'], 1).' درآمد.'
            : 'درز زیر آستین جلو و پشت '.Format::cm(abs($walk['difference']), 2).' اختلاف دارد؛ پیش از برش راستش کنید.';

        if ($this->hasGusset($p)) {
            $gusset = $this->gussetPiece((float) $p['gusset_size']);
            $extra[] = $gusset;
            $notes[] = 'لوزی زیربغل ساخته شد: چهار ضلع هرکدام '.Format::cm($gusset['meta']['gusset_side'], 1)
                .' و دو قطر '.Format::cm($gusset['meta']['gusset_diagonal'], 1).'. روی هر قطعه از نقطه زیر '
                .'بغل یک چاک به همان اندازه ضلع زده می‌شود و دو ضلع لوزی در آن می‌نشیند؛ دو ضلع دیگر در '
                .'قطعه روبه‌رو. با همین لوزی دست بدون بالا کشیدن لباس بالا می‌رود.';
        } elseif (array_key_exists('gusset', $this->paramsSchema())) {
            $notes[] = 'این آستین لوزی زیربغل ندارد؛ با زاویه '.Format::number($p['sleeve_angle'])
                .' درجه، بالا بردن دست کل لباس را از پهلو بالا می‌کشد و زیر بغل چین می‌افتد. اگر آزادی '
                .'حرکت مهم است یا آستین را تنگ‌تر می‌خواهید، لوزی زیربغل بگذارید یا زاویه آستین را به افق نزدیک‌تر کنید.';
        }

        $notes[] = $this->shapeNote($p, $plans);

        return [
            'pieces' => array_merge(array_values($pieces), $extra),
            'notes' => $notes,
            'meta' => [
                'sleeve' => [
                    'style' => static::key(),
                    'angle' => round((float) $p['sleeve_angle'], 1),
                    'top_seam' => round($topSeam, 2),
                    'underarm_seam' => round($underarm, 2),
                    'underarm_matched' => abs($walk['difference']) <= 0.1,
                    'gusset' => $this->hasGusset($p) ? round((float) $p['gusset_size'], 2) : null,
                ],
            ],
        ];
    }

    /** یادداشت ویژه هر سبک درباره فرم لباس. */
    abstract protected function shapeNote(array $p, array $plans): string;

    /* ---------------------------------------------------------------------
     |  درفت
     * ------------------------------------------------------------------- */

    /**
     * اندازه‌های خام یک طرف، پیش از هماهنگ‌کردن دو طرف.
     *
     * @return array<string, mixed>
     */
    protected function planSide(array $piece, array $a, array $p, array $context): array
    {
        $wrist = $this->measurement($context, 'wrist', 16.5);
        $arm = $this->measurement($context, 'arm_length', 58.5);
        $bicep = $this->measurement($context, 'bicep', 28.5);

        $shoulderDir = $this->unit($this->vec($a['snp'], $a['tip']));
        $shoulderAngle = rad2deg(atan2($shoulderDir['y'], max(1e-6, $shoulderDir['x'])));
        $angle = max($shoulderAngle + 1.0, min(80.0, (float) $p['sleeve_angle']));
        $drop = max(0.5, min($a['side_length'] * 0.8, (float) $p['underarm_drop']));

        $outline = array_values($piece['outline']);
        $tSide = $this->tAtLength($outline, $a['side_edge'], $drop);
        $under = Geometry::pointOnEdge($outline, $a['side_edge'], $tSide);

        $length = max(10.0, $arm + (float) $p['length_extra']);
        $hemHalf = max(3.5, min($bicep * 0.7, ($wrist + (float) $p['hem_ease']) / 2));

        // آستین نباید آن‌قدر تند بیفتد که دم آستین روی خود تنه بنشیند: گوشه پایینی دم
        // آستین باید دست‌کم دو سانت بیرون‌تر از درز پهلو بماند
        $outer = max((float) $under['x'], (float) $outline[$a['side_edge'] + 1]['x'], (float) $a['underarm']['x']);
        $radius = sqrt(($length * $length) + ($hemHalf * $hemHalf));
        $reach = ($outer + 2.0) - (float) $a['tip']['x'];
        $limit = rad2deg(acos(max(-1.0, min(1.0, $reach / max(0.01, $radius)))) - atan2($hemHalf, $length));

        return [
            'shoulder' => $a['shoulder_length'],
            'shoulder_angle' => round($shoulderAngle, 2),
            'angle' => $angle,
            'max_angle' => $limit,
            'length' => $length,
            'hem_half' => $hemHalf,
            'drop' => $drop,
            'underarm' => ['x' => (float) $under['x'], 'y' => (float) $under['y']],
        ];
    }

    /**
     * جای سر آستین و دم آستین، پس از هم‌اندازه‌شدن درز سرشانه دو طرف.
     *
     * @return array<string, mixed>
     */
    protected function placeSleeve(array $plan, array $p): array
    {
        $axis = ['x' => cos(deg2rad($plan['angle'])), 'y' => sin(deg2rad($plan['angle']))];
        $tip = $this->move($plan['anchors']['tip'], $axis, $plan['length']);
        $hem = $this->move($tip, $this->rotateVector($axis, 90.0), $plan['hem_half']);

        $chord = $this->len($this->vec($hem, $plan['underarm']));

        return $plan + [
            'axis' => $axis,
            'sleeve_tip' => $tip,
            'sleeve_hem' => $hem,
            'underarm_chord' => $chord,
            'underarm_seam' => $chord + $this->curveExtra($p),
        ];
    }

    /** درز زیر آستین چقدر بلندتر از خط راست کشیده می‌شود. */
    protected function curveExtra(array $p): float
    {
        return $this->hasGusset($p) ? 0.0 : max(0.0, (float) $p['underarm_curve']);
    }

    /**
     * آستین را روی قطعه می‌رویاند.
     *
     * @return array<string, mixed>
     */
    protected function growSleeve(array $piece, array $plan, float $underarmTarget, array $p): array
    {
        $a = $plan['anchors'];
        $outline = array_values($piece['outline']);

        // نقطه زیر بغل تازه: روی درز پهلو، به اندازه خواسته‌شده پایین‌تر
        $tSide = $this->tAtLength($outline, $a['side_edge'], $plan['drop']);
        [, $sideRest, $under] = $this->splitEdge($outline, $a['side_edge'], $tSide);

        // درز زیر آستین دو طرف باید دقیقاً هم‌اندازه باشد؛ طرف کوتاه‌تر کمی شکم می‌دهد
        $hem = $plan['sleeve_hem'];
        $end = $this->bowSeam($hem, $under, max($underarmTarget, $this->len($this->vec($hem, $under))), $a['tip']);

        $outline[$a['side_edge'] + 1] = $sideRest;
        $piece['outline'] = $outline;

        $piece = $this->replacePoints(
            $piece,
            $a['armhole_edge'],
            2,
            [
                Geometry::point($a['tip']['x'], $a['tip']['y']),
                Geometry::point($plan['sleeve_tip']['x'], $plan['sleeve_tip']['y']),
                Geometry::point($hem['x'], $hem['y']),
                $end,
            ],
            ['shoulder', 'hem', 'side', 'side'],
        );

        $side = $a['side'];
        $label = $side === 'front' ? 'جلو' : 'پشت';
        $topEdge = $a['armhole_edge'];
        $hemEdge = $topEdge + 1;
        $underEdge = $topEdge + 2;

        $piece = $this->dropNotches($piece, ['armhole']);
        $mid = Geometry::pointOnEdge($piece['outline'], $underEdge, 0.5);
        $piece['notches'][] = $this->notch($mid['x'], $mid['y'], $underEdge, 'وسط درز زیر آستین '.$label, 'underarm_seam');

        $elbow = Geometry::pointOnEdge($piece['outline'], $topEdge, 0.5);
        $piece['notches'][] = $this->notch($elbow['x'], $elbow['y'], $topEdge, 'وسط بالای آستین '.$label, 'sleeve_top');

        if ($this->hasGusset($p)) {
            $piece = $this->markGusset($piece, $underEdge, (float) $p['gusset_size'], $label);
        }

        $piece['markers'][] = $this->marker(
            'sleeve_axis',
            'خط میانی آستین',
            (float) $a['tip']['x'],
            (float) $a['tip']['y'],
            (float) $plan['sleeve_tip']['x'],
            (float) $plan['sleeve_tip']['y'],
        );

        unset($piece['meta']['armhole_edge']);
        $piece['meta']['armhole_length'] = 0.0;
        $piece['meta']['grown_on'] = [
            'style' => static::key(),
            'angle' => round($plan['angle'], 2),
            'top_seam' => round($a['shoulder_length'] + $plan['length'], 2),
            'sleeve_length' => round($plan['length'], 2),
            'hem_half' => round($plan['hem_half'], 2),
            'underarm_drop' => round($plan['drop'], 2),
            'underarm_seam' => round(Geometry::edgeLength($piece['outline'], $underEdge), 2),
            'gusset' => $this->hasGusset($p) ? round((float) $p['gusset_size'], 2) : null,
        ];

        return Geometry::normalizePiece($piece);
    }

    /** چاک لوزی زیربغل: خطی از نقطه زیر بغل به داخل قطعه، به اندازه ضلع لوزی. */
    protected function markGusset(array $piece, int $underEdge, float $size, string $label): array
    {
        $outline = array_values($piece['outline']);
        $corner = $this->at($outline, ($underEdge + 1) % count($outline));
        $before = Geometry::pointOnEdge($outline, $underEdge, 0.85);
        $after = Geometry::pointOnEdge($outline, ($underEdge + 1) % count($outline), 0.15);

        $bisector = $this->unit([
            'x' => $this->unit($this->vec($corner, $before))['x'] + $this->unit($this->vec($corner, $after))['x'],
            'y' => $this->unit($this->vec($corner, $before))['y'] + $this->unit($this->vec($corner, $after))['y'],
        ]);

        $tip = $this->move($corner, $bisector, $size);

        $piece['markers'][] = $this->marker(
            'gusset_slash',
            'چاک لوزی زیربغل',
            (float) $corner['x'],
            (float) $corner['y'],
            $tip['x'],
            $tip['y'],
        );

        $piece['notches'][] = $this->notch((float) $corner['x'], (float) $corner['y'], $underEdge, 'سر چاک لوزی '.$label, 'gusset');

        return $piece;
    }

    /**
     * لوزی زیربغل: چهار ضلع برابر که دو تای آن به چاک جلو و دو تای دیگر به چاک پشت دوخته می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function gussetPiece(float $size): array
    {
        $size = max(4.0, $size);
        $diagonal = $size * M_SQRT2;
        $half = $diagonal / 2;

        $piece = $this->piece([
            'code' => 'underarm-gusset',
            'name' => 'لوزی زیربغل',
            'cut_quantity' => 2,
            'mirror' => false,
            'outline' => [
                Geometry::point($half, 0),
                Geometry::point($diagonal, $half),
                Geometry::point($half, $diagonal),
                Geometry::point(0, $half),
            ],
            'notches' => [
                $this->notch($half, 0, 0, 'سر لوزی — به چاک جلو', 'gusset'),
                $this->notch($half, $diagonal, 2, 'سر لوزی — به چاک پشت', 'gusset'),
            ],
            'meta' => [
                'part' => 'gusset',
                'edges' => ['side', 'side', 'side', 'side'],
                'fold_edges' => [],
                'gusset_side' => round($size, 2),
                'gusset_diagonal' => round($diagonal, 2),
                'sleeve_style' => static::key(),
            ],
        ]);

        // راستای پارچه روی قطر لوزی می‌افتد تا کشش مورب کار کند
        $piece['grainline'] = [
            'from' => Geometry::point($half, 1.0),
            'to' => Geometry::point($half, $diagonal - 1.0),
            'label' => 'راستای پارچه (روی قطر لوزی)',
        ];

        return $piece;
    }

    /** شماره لبه درز زیر آستین یک قطعه. */
    protected function underarmEdge(array $piece): int
    {
        foreach ($piece['notches'] ?? [] as $notch) {
            if (($notch['pair'] ?? null) === 'underarm_seam') {
                return (int) $notch['edge'];
            }
        }

        return 0;
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک
     * ------------------------------------------------------------------- */

    /** @return array<string, array<string, mixed>> */
    protected function grownFields(float $angle, float $drop, float $curve, float $hemEase): array
    {
        return [
            'sleeve_angle' => [
                'label' => 'زاویه آستین', 'min' => 5, 'max' => 75, 'step' => 1, 'default' => $angle,
                'unit' => 'درجه',
                'hint' => 'از خط افق به پایین. کمتر یعنی آستین افقی‌تر: دست راحت‌تر بالا می‌رود و زیر بغل '
                    .'چین می‌خورد. بیشتر یعنی قالب‌تر و بسته‌تر.',
            ],
            'underarm_drop' => [
                'label' => 'پایین‌آمدن زیر بغل روی درز پهلو', 'min' => 0.5, 'max' => 30, 'step' => 0.5,
                'default' => $drop, 'unit' => 'سانتی‌متر',
                'hint' => 'از زیر بغل بلوک به پایین؛ هرچه بیشتر، آستین گشادتر و افتاده‌تر.',
            ],
            'underarm_curve' => [
                'label' => 'گودی درز زیر آستین', 'min' => 0, 'max' => 20, 'step' => 0.5, 'default' => $curve,
                'unit' => 'سانتی‌متر', 'hint' => 'چقدر بلندتر از خط راست؛ بیشتر یعنی زیر بغل خوش‌فرم‌تر و نرم‌تر.',
            ],
            'length_extra' => $this->lengthField(),
            'hem_ease' => [
                'label' => 'آزادی دم آستین', 'min' => 0, 'max' => 40, 'step' => 0.5, 'default' => $hemEase,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }
}
