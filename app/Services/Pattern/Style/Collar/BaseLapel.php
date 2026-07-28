<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Support\Format;

/**
 * پایه یقه‌های برگردان: انگلیسی، نوک‌تیز و شال.
 *
 * این سه یقه با بقیه یک تفاوت بنیادی دارند: برگردانشان قطعه جدا نیست، تکه‌ای از
 * خودِ تنه جلوست که روی خط خواب برمی‌گردد. پس نمی‌شود فقط یک قطعه به لباس
 * افزود؛ باید اول تنه جلو دوباره شکل بگیرد و بعد یقه به اندازه خط یقه‌ای که
 * باقی مانده درفت شود.
 *
 * ترتیب کار، همان ترتیب روی کاغذ:
 *
 *   ۱. «نقطه شکست» روی خط مرکز جلو علامت می‌خورد؛ یقه از این نقطه به پایین بسته
 *      نمی‌شود و از همین‌جا برمی‌گردد.
 *   ۲. «خط خواب» از نقطه شکست به نقطه‌ای روی امتداد سرشانه (به اندازه بلندی پایه
 *      بالاتر از سرگردن) کشیده می‌شود. همه پهنای برگردان از این خط اندازه گرفته
 *      می‌شود، نه از خط مرکز.
 *   ۳. «نقطه گلوگاه» روی خط یقه، چند سانتی‌متر پایین‌تر از سرگردن گذاشته می‌شود؛
 *      از این نقطه به بعد دیگر خط یقه نیست، «خط گلو» است که به سر یقه دوخته می‌شود.
 *   ۴. برگردان از نقطه شکست تا نوک، و از نوک تا گلوگاه بریده می‌شود. فرق یقه
 *      انگلیسی و نوک‌تیز و شال فقط در همین چند نقطه است.
 *   ۵. یقه (رویه و زیره) به اندازه خط یقه باقی‌مانده — از مرکز پشت تا گلوگاه —
 *      درفت می‌شود و سر جلویش به اندازه خط گلو بریده می‌شود.
 *
 * چون خط یقه پس از گام ۴ کوتاه‌تر از خط یقه اولیه است، اندازه‌گیری بعد از
 * شکل‌دادن تنه انجام می‌شود؛ همان چیزی که BaseCollar با prepare() فراهم می‌کند.
 */
abstract class BaseLapel extends BaseCollar
{
    /** خط یقه پیش از بریدن برگردان؛ برای گرفتن شعاع یقه لازم است. */
    protected array $beforeNeck = ['full' => 0.0, 'half' => 0.0, 'front' => 0.0, 'back' => 0.0, 'pieces' => 0];

    /** اندازه خط گلو، تا سر یقه به همان اندازه بریده شود. */
    protected float $gorgeLength = 0.0;

    /* ---------------------------------------------------------------------
     |  پذیرش
     * ------------------------------------------------------------------- */

    protected function supportsCollar(array $pieces, array $context): true|string
    {
        if (! $this->frontOpening($pieces, $context)) {
            return $this->label().' برگردانِ خودِ تنه جلوست و روی لباس بدون چاک جلو درفت نمی‌شود؛'
                .' اول بست جلو (دکمه یا زیپ) را اضافه کنید تا مرکز جلو باز شود.';
        }

        foreach ($this->frontIndexes($pieces) as $index) {
            if (! empty($pieces[$index]['meta']['lapel'])) {
                return 'برگردان یک بار روی «'.$pieces[$index]['name'].'» بریده شده است؛'
                    .' اگر دوباره بریده شود، تنه جلو از خط گلو به بالا دو بار کوتاه می‌شود.'
                    .' اول سبک یقه را بردارید و بعد یقه تازه بگذارید.';
            }

            if (! empty($pieces[$index]['on_fold'])) {
                return 'تنه جلو («'.$pieces[$index]['name'].'») هنوز روی تای پارچه بریده می‌شود، پس مرکز جلو تا است نه چاک؛'
                    .' '.$this->label().' جایی برای برگشتن ندارد. اول بست جلو را بگذارید تا تنه دو تکه شود.';
            }
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     |  شکل‌دادن تنه جلو
     * ------------------------------------------------------------------- */

    protected function prepare(array $pieces, array $p, array $context): array
    {
        $this->beforeNeck = $this->measureNeckline($pieces);
        $this->gorgeLength = 0.0;

        $notes = [];
        $extra = [];
        $done = 0;

        foreach ($this->frontIndexes($pieces) as $index) {
            $shaped = $this->shapeFront($pieces[$index], $p);

            if ($shaped === null) {
                $notes[] = 'خط یقه «'.$pieces[$index]['name'].'» با سرشانه دنبال نمی‌شود، پس برگردان روی آن بریده نشد؛'
                    .' یقه فقط به اندازه خط یقه موجود درفت می‌شود.';

                continue;
            }

            $pieces[$index] = $shaped['piece'];
            $this->gorgeLength = (float) $shaped['frame']['gorge_length'];
            $done++;

            foreach ($shaped['notes'] as $note) {
                $notes[] = $note;
            }

            if (! empty($p['facing'])) {
                $facing = $this->facingOfFront($shaped['piece'], (float) $p['facing_width']);

                if ($facing === null) {
                    $notes[] = 'سجاف جلو با پهنای خواسته‌شده روی خودش می‌افتد و ساخته نشد؛ پهنای سجاف را کمتر بگیرید.';
                } else {
                    $extra[] = $facing;
                }
            }
        }

        if ($done > 0) {
            $notes[] = 'برگردان روی تنه جلو بریده شد، پس خط یقه کوتاه‌تر از پیش شد: از مرکز پشت تا نقطه گلوگاه.'
                .' یقه به اندازه همین خط یقه تازه درفت می‌شود، نه خط یقه اولیه.';
        }

        return ['pieces' => array_merge($pieces, $extra), 'notes' => $notes];
    }

    /**
     * بریدن برگردان روی یک تنه جلو.
     *
     * @param  array<string, mixed>  $piece
     * @return array{piece: array<string, mixed>, frame: array<string, mixed>, notes: array<int, string>}|null
     */
    protected function shapeFront(array $piece, array $p): ?array
    {
        $frame = $this->lapelFrame($piece, $p);

        if ($frame === null) {
            return null;
        }

        $chain = $this->lapelChain($frame, $p);
        $outline = $frame['outline'];
        $tags = $frame['tags'];
        $count = count($outline);
        $gi = $frame['gorge_index'];
        $gorge = $chain['gorge'];

        $points = array_merge(
            [$gorge],
            array_slice($outline, $gi + 1),
            [Geometry::point($frame['break']['x'], $frame['break']['y'])],
            $chain['middle'],
        );

        $edges = array_merge(
            array_slice($tags, $gi, $count - 1 - $gi),
            $chain['tags'],
        );

        if (count($edges) !== count($points)) {
            return null;
        }

        $piece['outline'] = Geometry::round($points);
        $piece['meta']['edges'] = $edges;
        $piece['meta']['fold_edges'] = [];
        $piece['meta']['lapel'] = static::key();
        $piece['meta']['roll_line'] = [
            'from' => Geometry::point($frame['break']['x'], $frame['break']['y']),
            'to' => Geometry::point($frame['roll_top']['x'], $frame['roll_top']['y']),
        ];
        $piece['meta']['break_point'] = Geometry::point($frame['break']['x'], $frame['break']['y']);
        $piece['meta']['gorge_length'] = round((float) $frame['gorge_length'], 2);
        $piece['meta']['lapel_width'] = round((float) $p['lapel_width'], 2);
        // شمار نقطه‌هایی که برگردان به ته مسیر افزوده؛ سجاف از همین‌جا می‌فهمد کجا بایستد
        $piece['meta']['lapel_points'] = count($chain['middle']) + 1;

        $piece['markers'][] = $this->marker(
            'roll_line',
            'خط خواب برگردان',
            $frame['break']['x'],
            $frame['break']['y'],
            $frame['roll_top']['x'],
            $frame['roll_top']['y'],
        );

        $piece['notches'][] = $this->notch(
            $frame['break']['x'],
            $frame['break']['y'],
            0,
            'نقطه شکست یقه',
            'break_point',
        );

        $piece['notches'][] = $this->notch(
            $gorge['x'],
            $gorge['y'],
            0,
            'نقطه گلوگاه (سر خط گلو)',
            'gorge',
        );

        $piece = $this->reindexAnchors($piece);
        $piece = Geometry::normalizePiece($piece);

        $problems = Geometry::validatePiece($piece);

        if ($problems !== []) {
            return null;
        }

        return [
            'piece' => $piece,
            'frame' => $frame,
            'notes' => [
                'نقطه شکست '.Format::cm((float) $p['break_depth']).' پایین‌تر از خط یقه روی مرکز جلو نشست؛'
                    .' خط خواب از همان‌جا تا '.Format::cm((float) $p['stand']).' بالاتر از سرگردن کشیده شد.',
                'خط گلو '.Format::cm((float) $frame['gorge_length']).' درآمد و سر یقه به همین اندازه بریده می‌شود؛'
                    .' اگر این دو با هم نخوانند، خرک یقه بالا و پایین می‌افتد.',
            ],
        ];
    }

    /**
     * چارچوب برگردان: نقطه شکست، خط خواب، نقطه گلوگاه و جهت‌ها.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>|null
     */
    protected function lapelFrame(array $piece, array $p): ?array
    {
        $outline = array_values($piece['outline'] ?? []);
        $tags = Geometry::edgeTags($piece);
        $count = count($outline);
        $neckEdges = $this->edgesWithTag($piece, 'neck');

        if ($count < 5 || $neckEdges === []) {
            return null;
        }

        $first = $neckEdges[0];
        $last = $neckEdges[count($neckEdges) - 1];

        // لبه‌های یقه باید پشت سر هم باشند و پس از آن‌ها سرشانه بیاید
        if ($last - $first !== count($neckEdges) - 1 || ($tags[$last + 1] ?? null) !== 'shoulder' || $last + 2 >= $count) {
            return null;
        }

        $neckLength = Geometry::edgesLength($outline, $neckEdges);
        $gorgeFromSnp = max(1.0, min($neckLength - 1.0, (float) $p['gorge_run']));
        $at = $this->pointAtDistance($outline, $neckEdges, $neckLength - $gorgeFromSnp);
        $t = max(0.03, min(0.97, (float) $at['t']));

        $outline = Geometry::splitEdgeAt($outline, (int) $at['edge'], $t);
        array_splice($tags, (int) $at['edge'], 0, ['neck']);
        $count++;
        $gi = ((int) $at['edge']) + 1;

        $neckTop = $this->pt($outline[$first]);
        $snp = $this->pt($outline[$last + 2]);
        $tip = $this->pt($outline[$last + 3]);
        $lastPoint = $this->pt($outline[$count - 1]);
        $centerX = min($this->pt($outline[0])['x'], $lastPoint['x']);

        $breakDepth = max(2.0, min($lastPoint['y'] - $neckTop['y'] - 4.0, (float) $p['break_depth']));
        $break = ['x' => $centerX, 'y' => $neckTop['y'] + $breakDepth];

        $shoulder = $this->unit($this->vec($snp, $tip));
        $rollTop = $this->add($snp, $shoulder, -max(0.5, (float) $p['stand']));
        $up = $this->unit($this->vec($break, $rollTop));

        // عمود بر خط خواب، رو به بیرون لباس (سمت مرکز جلو)
        $out = ['x' => $up['y'], 'y' => -$up['x']];

        if ($out['x'] > 0) {
            $out = ['x' => -$out['x'], 'y' => -$out['y']];
        }

        return [
            'outline' => $outline,
            'tags' => $tags,
            'gorge_index' => $gi,
            'gorge' => $this->pt($outline[$gi]),
            'gorge_length' => 0.0,
            'break' => $break,
            'roll_top' => $rollTop,
            'roll_length' => $this->length($this->vec($break, $rollTop)),
            'up' => $up,
            'out' => $out,
            'snp' => $snp,
            'neck_top' => $neckTop,
            'center_x' => $centerX,
        ];
    }

    /**
     * نقطه‌های برگردان.
     *
     * مسیر تازه از لبه مرکز جلو می‌آید، به نقطه شکست می‌رسد، دور برگردان می‌چرخد
     * و به نقطه گلوگاه می‌بندد. سبک فقط این را می‌دهد:
     *
     *   gorge  نقطه گلوگاه (می‌تواند منحنی باشد، برای یقه شال)
     *   middle نقطه‌های میان شکست و گلوگاه، به همان ترتیب
     *   tags   برچسب لبه‌های تازه: مرکز جلو←شکست، شکست←میانی‌ها، آخری←گلوگاه
     *
     * سبک باید gorge_length را هم روی چارچوب بنویسد تا سر یقه به همان اندازه
     * بریده شود.
     *
     * @return array{gorge: array<string, mixed>, middle: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    abstract protected function lapelChain(array &$frame, array $p): array;

    /* ---------------------------------------------------------------------
     |  سجاف جلو
     * ------------------------------------------------------------------- */

    /**
     * سجاف جلو: نواری که برگردان و لبه مرکز جلو را می‌پوشاند.
     *
     * برگردان که برمی‌گردد، پشتش دیده می‌شود؛ پس سجاف جلو در یقه برگردان تزیینی
     * نیست، رویه‌ی دیده‌شونده برگردان است.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>|null
     */
    protected function facingOfFront(array $piece, float $width): ?array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $shoulder = $this->edgeWithTag($piece, 'shoulder');
        $appended = (int) ($piece['meta']['lapel_points'] ?? 0);
        $bottom = $count - 1 - $appended;   // نقطه پایین لبه مرکز جلو

        if ($count < 6 || $width < 1.5 || $shoulder === null || $bottom <= $shoulder + 1) {
            return null;
        }

        // کمی روی سرشانه، تا سجاف در درز سرشانه گرفته شود
        $on = Geometry::pointOnEdge($outline, $shoulder, min(0.75, $width / max(1.0, Geometry::edgeLength($outline, $shoulder))));
        $path = [['x' => $on['x'], 'y' => $on['y']]];

        // از سرشانه به عقب تا نقطه گلوگاه (نقطه صفر)
        for ($i = $shoulder; $i >= 0; $i--) {
            $path[] = $this->pt($outline[$i]);
        }

        // دور برگردان: از گوشه خرک تا نقطه شکست و پایین مرکز جلو
        for ($i = $count - 1; $i >= $bottom; $i--) {
            $path[] = $this->pt($outline[$i]);
        }

        // کمی روی لبه دم، تا سجاف در درز دم گرفته شود
        $hem = $bottom - 1;
        $hemLength = max(1.0, Geometry::edgeLength($outline, $hem));
        $path[] = Geometry::pointOnEdge($outline, $hem, max(0.1, 1 - min(0.6, $width / $hemLength)));

        $path = $this->dedupePath($path);

        if (count($path) < 4) {
            return null;
        }

        $centroid = Geometry::centroid($outline);
        $inner = $this->cleanOffset($path, $this->offsetPath($path, -$width, $centroid));

        if (count($inner) < 3) {
            return null;
        }

        $points = [];
        $tags = [];

        foreach ($path as $index => $point) {
            $points[] = Geometry::point((float) $point['x'], (float) $point['y']);
            $tags[] = $index === 0 ? 'shoulder' : 'default';
        }

        $tags[count($tags) - 1] = 'hem';

        foreach (array_reverse($inner) as $point) {
            $points[] = Geometry::point((float) $point['x'], (float) $point['y']);
            $tags[] = 'default';
        }

        $facing = $this->newPiece([
            'code' => $piece['code'].'-facing',
            'name' => 'سجاف جلو (روی برگردان)',
            'cut_quantity' => max(2, (int) ($piece['cut_quantity'] ?? 2)),
            'mirror' => true,
            'outline' => $points,
            'meta' => [
                'part' => 'facing',
                'facing_for' => $piece['code'],
                'edges' => $tags,
                'fold_edges' => [],
                'interfacing' => true,
                'width' => round($width, 2),
                'lapel' => static::key(),
            ],
        ]);

        if (Geometry::validatePiece($facing, 8.0) !== []) {
            return null;
        }

        $bounds = Geometry::bounds($facing['outline']);
        $facing['grainline'] = $this->grainline(
            $bounds[0] + (($bounds[2] - $bounds[0]) * 0.4),
            $bounds[1] + 1.0,
            $bounds[3] - 1.0,
        );

        return $facing;
    }

    /**
     * برداشتن نقطه‌های روی هم افتاده از یک مسیر باز.
     *
     * @param  array<int, array{x: float, y: float}>  $path
     * @return array<int, array{x: float, y: float}>
     */
    protected function dedupePath(array $path): array
    {
        $out = [];

        foreach ($path as $point) {
            if ($out === [] || Geometry::distance($out[count($out) - 1], $point) > 0.15) {
                $out[] = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            }
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  یقه
     * ------------------------------------------------------------------- */

    /**
     * رویه و زیره یقه، به اندازه خط یقه باقی‌مانده.
     *
     * @return array{pieces: array<int, array<string, mixed>>, notes: array<int, string>, meta: array<string, mixed>}
     */
    protected function draft(array $neck, array $p, array $pieces, array $context): array
    {
        $stand = (float) $p['stand'];
        $fall = max($stand + 0.5, (float) $p['fall']);
        $width = $stand + $fall;
        $target = max(4.0, $neck['half'] + (float) $p['ease']);
        $gorge = $this->gorgeLength > 0.5 ? $this->gorgeLength : (float) $p['gorge_run'];

        [$upper, $length, $difference] = $this->fitToNeckline(
            fn (float $span) => $this->collarPiece($span, $width, $stand, $gorge, $p),
            $target,
        );

        $upper = $this->halfCollarNotches($upper, $neck, $target);
        $outer = $this->seamOf($upper, 'hem');

        $under = $upper;
        $under['code'] = 'collar-under';
        $under['name'] = 'زیره یقه (روی مورب)';
        $under['on_fold'] = false;
        $under['cut_quantity'] = 2;
        $under['meta']['part'] = 'collar';
        $under['meta']['fold_edges'] = [];
        $under['meta']['under_collar'] = true;
        $under['meta']['bias'] = true;
        $under['meta']['note'] = 'زیره یقه روی مورب و در دو تکه با درز مرکز پشت بریده می‌شود تا خط خواب نرم بیفتد.';

        $bounds = Geometry::bounds($under['outline']);
        $span = min($bounds[2] - $bounds[0], $bounds[3] - $bounds[1]) * 0.6;
        $under['grainline'] = $this->grainlineBetween(
            ['x' => $bounds[0] + 0.8, 'y' => $bounds[1] + 0.8 + $span],
            ['x' => $bounds[0] + 0.8 + $span, 'y' => $bounds[1] + 0.8],
            'مورب ۴۵ درجه',
        );

        $made = [$upper, $under];

        if (! empty($p['interfacing'])) {
            $made[] = $this->collarInterfacing($under, 'لایه چسب زیره یقه');
        }

        $notes = [
            'خط یقه باقی‌مانده (مرکز پشت تا گلوگاه) '.Format::cm($neck['half'])
                .' است در برابر '.Format::cm($this->beforeNeck['half'] ?: $neck['half'])
                .' پیش از بریدن برگردان؛ یقه به اندازه همین درفت شد.',
            'لبه بیرونی یقه '.Format::cm($outer).' درآمد، یعنی '.Format::cm(max(0, $outer - $length))
                .' بلندتر از لبه یقه؛ همین اضافه است که یقه را روی خط خواب می‌خواباند.',
            'سر یقه '.Format::cm($gorge).' بریده شد تا روی خط گلوی برگردان بنشیند.',
            'زیره یقه روی مورب بریده می‌شود و رویه روی راستای پارچه؛ رویه را موقع دوخت کمی گشادتر بگیرید تا در برگشت نکشد.',
        ];

        return [
            'pieces' => $made,
            'notes' => $notes,
            'meta' => [
                'target' => round($target, 2),
                'measured' => $length,
                'ease' => round((float) $p['ease'], 2),
                'difference' => $difference,
                'stand' => $stand,
                'fall' => $fall,
                'outer_edge' => round($outer, 2),
                'gorge_length' => round($gorge, 2),
                'neckline_before' => $this->beforeNeck['half'] ?? null,
            ],
        ];
    }

    /**
     * تنه یقه برگردان.
     *
     * @return array<string, mixed>
     */
    protected function collarPiece(float $span, float $width, float $stand, float $gorge, array $p): array
    {
        $arc = $this->collarArc($span, $width, $this->arcRadiusFor($span, $width, (float) $p['spread']), 'fall');
        $front = $this->collarFrontEnd($arc, $gorge, $p);
        $shell = $this->assembleCollar($arc, $front['points'], 'hem', $front['tags']);

        return $this->newPiece([
            'code' => 'collar-upper',
            'name' => 'رویه یقه برگردان',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $shell['outline'],
            'grainline' => $this->collarGrainline($arc),
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', $arc['cb_neck']['x'], $arc['cb_neck']['y'], $arc['cb_outer']['x'], $arc['cb_outer']['y']),
                $this->rollLineMarker($arc, $stand, 'خط خواب یقه'),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => $shell['edges'],
                'fold_edges' => [count($shell['edges']) - 1],
                'interfacing' => true,
                'girth_role' => 'trim',
                'collar_kind' => 'lapel',
                'stand_height' => round($stand, 2),
                'roll_line' => round($stand, 2),
                'gorge_length' => round($gorge, 2),
                'radius' => $arc['radius'],
            ],
        ]);
    }

    /**
     * سر جلوی یقه: خط گلو و گوشه‌ای که نیمه دیگر خرک را می‌سازد.
     *
     * @return array{points: array<int, array<string, mixed>>, tags: array<int, string>}
     */
    protected function collarFrontEnd(array $arc, float $gorge, array $p): array
    {
        $neck = $this->pt($arc['cf_neck']);
        $outer = $this->pt($arc['cf_outer']);
        $tangent = $this->unit($arc['cf_tangent']);
        $across = $this->unit($this->vec($neck, $outer));
        $angle = deg2rad(45.0);

        $gorgeEnd = $this->add(
            $this->add($neck, $tangent, $gorge * cos($angle)),
            $across,
            $gorge * sin($angle),
        );

        $corner = $this->add($gorgeEnd, $across, max(1.0, (float) $p['collar_point']));

        return [
            'points' => [
                Geometry::point($gorgeEnd['x'], $gorgeEnd['y']),
                Geometry::point($corner['x'], $corner['y']),
                Geometry::point($outer['x'], $outer['y']),
            ],
            'tags' => ['side', 'side', 'hem'],
        ];
    }

    /* ---------------------------------------------------------------------
     |  پارامترهای مشترک برگردان
     * ------------------------------------------------------------------- */

    /** @return array<string, array<string, mixed>> */
    protected function lapelFields(float $lapelWidth = 8.0, float $breakDepth = 20.0): array
    {
        return [
            'break_depth' => [
                'label' => 'گودی نقطه شکست', 'min' => 4, 'max' => 45, 'step' => 0.5, 'default' => $breakDepth,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط یقه روی مرکز جلو به پایین؛ یقه از این نقطه برمی‌گردد.',
            ],
            'lapel_width' => [
                'label' => 'پهنای برگردان', 'min' => 3, 'max' => 16, 'step' => 0.5, 'default' => $lapelWidth,
                'unit' => 'سانتی‌متر', 'hint' => 'از خط خواب تا لبه بیرونی برگردان.',
            ],
            'stand' => [
                'label' => 'بلندی پایه یقه', 'min' => 1.5, 'max' => 5, 'step' => 0.25, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'خط خواب به همین اندازه بالاتر از سرگردن تمام می‌شود.',
            ],
            'fall' => [
                'label' => 'پهنای برگشت یقه', 'min' => 2.5, 'max' => 9, 'step' => 0.25, 'default' => 4.5,
                'unit' => 'سانتی‌متر', 'hint' => 'دست‌کم یک سانت بیشتر از پایه، تا درز خط یقه پنهان بماند.',
            ],
            'gorge_run' => [
                'label' => 'خط گلو', 'min' => 2, 'max' => 10, 'step' => 0.5, 'default' => 4,
                'unit' => 'سانتی‌متر', 'hint' => 'فاصله نقطه گلوگاه از سرگردن؛ سر یقه به همین اندازه بریده می‌شود.',
            ],
            'spread' => [
                'label' => 'گشادی لبه بیرونی یقه', 'min' => 0.5, 'max' => 8, 'step' => 0.25, 'default' => 3,
                'unit' => 'سانتی‌متر',
            ],
            'collar_point' => [
                'label' => 'بلندی سر یقه', 'min' => 1, 'max' => 8, 'step' => 0.5, 'default' => 3,
                'unit' => 'سانتی‌متر', 'hint' => 'گوشه‌ای که نیمه دیگر خرک را می‌سازد.',
            ],
            'facing' => [
                'label' => 'سجاف جلو', 'type' => 'toggle', 'default' => true,
                'hint' => 'در یقه برگردان لازم است؛ پشت برگردان از رو دیده می‌شود.',
            ],
            'facing_width' => [
                'label' => 'پهنای سجاف جلو', 'min' => 4, 'max' => 16, 'step' => 0.5, 'default' => 8,
                'unit' => 'سانتی‌متر',
            ],
            'ease' => $this->easeField(),
            'interfacing' => $this->interfacingField(),
        ];
    }
}
