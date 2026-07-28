<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Support\Format;
use Throwable;

/**
 * درفت مشترک خانواده آستین‌های حلقه‌ای (ست‌این).
 *
 * همه آستین‌های این پوشه از یک قاب مشترک ساخته می‌شوند:
 *
 *   ۱. طول حلقه آستین بالاتنه گرفته می‌شود (پارامتر، گزینه یا اندازه بدن).
 *   ۲. پهنای آستین از دور بازو + آزادی به دست می‌آید و اگر آستین «پف» داشته باشد
 *      به همان نسبت باز می‌شود (همان برش‌دادن و بازکردن روی الگو).
 *   ۳. ارتفاع سرآستین با دوپاره‌سازی چند باره تنظیم می‌شود تا طول منحنی سرآستین
 *      دقیقاً به «طول حلقه + آزادی سرآستین» برسد؛ یعنی سرآستین روی حلقه پیاده
 *      (walk) می‌شود، نه اینکه با یک فرمول سرانگشتی ساخته شود.
 *   ۴. نشانه‌ها با قرارداد خیاطی گذاشته می‌شود: یک نشانه تکی روی نیمه جلو و یک
 *      نشانه دوتایی روی نیمه پشت، به‌علاوه نوک آستین و دو زیر بغل.
 *   ۵. خط بازو و خط آرنج علامت می‌خورد و راستای پارچه از وسط آستین می‌گذرد.
 *
 * منحنی سرآستین چهار کمان درجه‌دو دارد: نیمه جلو گودتر و صاف‌تر و نیمه پشت پرتر —
 * همان تفاوتی که باعث می‌شود آستین درست بنشیند.
 */
trait SleeveDraftSupport
{
    /** آزادی سرآستین پیش‌فرض هر خانواده (سانتی‌متر) روی پارچه تخت. */
    protected float $defaultCapEase = 1.5;

    /* ---------------------------------------------------------------------
     |  قاب آستین
     * ------------------------------------------------------------------- */

    /**
     * اندازه‌های پایه آستین: پهنا، ارتفاع سرآستین، بلندی، پهنای دم و خط آرنج.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function sleeveFrame(array $m, array $ease, array $params, array $spec = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5);
        $wrist = $this->m($m, 'wrist', 16.5);
        $armLength = $this->m($m, 'arm_length', 58);

        $armhole = $this->armholeTarget($m, $ease, $params, $spec);
        $capEase = $this->capEase($params, $spec);

        $bicepEase = $this->ease($ease, 'bicep', (float) ($spec['bicep_ease'] ?? 4));
        $baseWidth = max($bicep * 0.85, $bicep + $bicepEase);

        $capFullness = max(0.0, (float) ($spec['cap_fullness'] ?? 0));
        $width = $baseWidth * (1 + $capFullness);
        $capTarget = ($armhole + $capEase) * (1 + $capFullness);

        $shape = $this->capShape($spec);
        $fit = $this->fitCap($width, $capTarget, $shape);
        $capHeight = $fit['height'] + max(0.0, (float) ($spec['cap_lift'] ?? 0));
        $cap = $this->sleeveCap($width, $capHeight, $shape);
        $capLength = Geometry::edgesLength($cap, [0, 1, 2, 3]);

        $length = $this->sleeveLength($params, $spec, $armLength, $capHeight);
        $hem = $this->hemWidth($params, $spec, $baseWidth, $wrist, $capHeight, $length, $armLength);

        // خط آرنج از سرشانه اندازه گرفته می‌شود؛ حدود ۵۵٪ قد آستین
        $elbowY = min($length - 1.5, max($capHeight + 2.0, $armLength * 0.55));

        return [
            'bicep' => $bicep,
            'wrist' => $wrist,
            'arm_length' => $armLength,
            'armhole' => round($armhole, 2),
            'cap_ease' => round($capEase, 2),
            'cap_fullness' => $capFullness,
            'base_width' => round($baseWidth, 2),
            'width' => round($width, 3),
            'cap_height' => round($capHeight, 3),
            'cap' => $cap,
            'cap_length' => $capLength,
            'cap_target' => round($capTarget, 2),
            'cap_status' => $fit['status'],
            'length' => round($length, 3),
            'hem_width' => round($hem, 3),
            'elbow_y' => round($elbowY, 3),
            'center' => round($width / 2, 3),
        ];
    }

    /** طول حلقه آستینی که سرآستین باید روی آن پیاده شود. */
    protected function armholeTarget(array $m, array $ease, array $params, array $spec = []): float
    {
        $armhole = (float) ($spec['armhole_length'] ?? 0);

        if ($armhole <= 0) {
            $armhole = (float) $this->param($params, 'armhole_length', 0);
        }

        if ($armhole <= 0) {
            $armhole = $this->m($m, 'armhole', 42) + max(0, $this->ease($ease, 'bust', 6) / 4);
        }

        return max(20.0, $armhole);
    }

    /** آزادی سرآستین: پارچه کشی صفر، پارچه تخت ۱٫۵ تا ۴ سانتی‌متر. */
    protected function capEase(array $params, array $spec = []): float
    {
        $default = (float) ($spec['cap_ease'] ?? $this->defaultCapEase);

        return max(0.0, (float) $this->param($params, 'cap_ease', $default));
    }

    /** بلندی آستین از سرشانه: درصدی از قد آستین به‌علاوه تنظیم دستی. */
    protected function sleeveLength(array $params, array $spec, float $armLength, float $capHeight): float
    {
        if (isset($spec['length'])) {
            return max($capHeight + 1.5, (float) $spec['length']);
        }

        $percent = (float) $this->param($params, 'length_percent', (float) ($spec['length_percent'] ?? 100));
        $extra = (float) $this->param($params, 'length_extra', 0);

        return max($capHeight + 1.5, ($armLength * $percent / 100) + $extra);
    }

    /** پهنای دم آستین: از بازو تا مچ به نسبت بلندی باریک می‌شود. */
    protected function hemWidth(
        array $params,
        array $spec,
        float $width,
        float $wrist,
        float $capHeight,
        float $length,
        float $armLength,
    ): float {
        if (isset($spec['hem_width'])) {
            return max(6.0, (float) $spec['hem_width']);
        }

        $hemEase = (float) $this->param($params, 'hem_ease', (float) ($spec['hem_ease'] ?? 6));
        $wristWidth = $wrist + $hemEase;

        $span = max(1.0, $armLength - $capHeight);
        $t = max(0.0, min(1.0, ($length - $capHeight) / $span));
        $tapered = $width + (($wristWidth - $width) * $t);

        if (isset($spec['hem_ratio'])) {
            $tapered = $width * (float) $spec['hem_ratio'];
        }

        return max(6.0, $tapered);
    }

    /* ---------------------------------------------------------------------
     |  منحنی سرآستین
     * ------------------------------------------------------------------- */

    /** نسبت‌های شکل سرآستین؛ نیمه جلو (سمت چپ) همیشه گودتر از نیمه پشت است. */
    protected function capShape(array $spec = []): array
    {
        return [
            'front_hollow' => (float) ($spec['front_hollow'] ?? 0.46),
            'back_hollow' => (float) ($spec['back_hollow'] ?? 0.34),
            'apex' => (float) ($spec['cap_apex'] ?? 0.5),
        ];
    }

    /**
     * منحنی سرآستین با چهار کمان درجه‌دو.
     *
     * نقطه (0, h) زیر بغل جلو و (w, h) زیر بغل پشت است؛ پس نیمه چپ الگو جلوی
     * آستین است — همان قراردادی که در باقی سامانه هم رعایت شده.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sleeveCap(float $width, float $height, array $shape = []): array
    {
        $shape = $shape === [] ? $this->capShape() : $shape;
        $apexX = $width * $shape['apex'];

        return [
            Geometry::point(0, $height),
            Geometry::curve($width * 0.25, $height * $shape['front_hollow'], $width * 0.045, $height * 0.88),
            Geometry::curve($apexX, 0, $width * 0.335, $height * 0.055),
            Geometry::curve($width * 0.75, $height * $shape['back_hollow'], $width * 0.665, 0),
            Geometry::curve($width, $height, $width * 0.955, $height * 0.70),
        ];
    }

    /**
     * ارتفاع سرآستین را با دوپاره‌سازی پیدا می‌کند تا طول منحنی به هدف برسد.
     *
     * @return array{height: float, length: float, status: string}
     */
    protected function fitCap(float $width, float $target, array $shape = []): array
    {
        $shape = $shape === [] ? $this->capShape() : $shape;
        $low = $width * 0.18;
        $high = $width * 0.78;

        $lengthAt = fn (float $h) => Geometry::edgesLength($this->sleeveCap($width, $h, $shape), [0, 1, 2, 3]);

        if ($lengthAt($low) >= $target) {
            return ['height' => round($low, 3), 'length' => $lengthAt($low), 'status' => 'too_wide'];
        }

        if ($lengthAt($high) <= $target) {
            return ['height' => round($high, 3), 'length' => $lengthAt($high), 'status' => 'too_narrow'];
        }

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;

            if ($lengthAt($mid) < $target) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $height = round(($low + $high) / 2, 3);

        return ['height' => $height, 'length' => $lengthAt($height), 'status' => 'fitted'];
    }

    /* ---------------------------------------------------------------------
     |  بدنه آستین
     * ------------------------------------------------------------------- */

    /**
     * درز پهلو، دم آستین و درز جلو را به منحنی سرآستین می‌چسباند و قطعه را می‌بندد.
     *
     * @param  array<string, mixed>  $frame  خروجی sleeveFrame()
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function sleeveBody(array $frame, array $spec = []): array
    {
        $width = (float) $frame['width'];
        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];
        $hemHalf = ((float) $frame['hem_width']) / 2;
        $center = $width / 2;

        $bulge = (float) ($spec['side_bulge'] ?? 0.6);
        $bulgeAt = (float) ($spec['side_bulge_at'] ?? 0.45);
        $hemDrop = (float) ($spec['hem_drop'] ?? 0);
        $backExtra = (float) ($spec['back_seam_extra'] ?? 0);

        $outline = $frame['cap'];
        $edges = ['armhole', 'armhole', 'armhole', 'armhole'];

        $backTop = ['x' => $width, 'y' => $capHeight];
        $backHem = ['x' => $center + $hemHalf, 'y' => $length + $backExtra];
        $frontHem = ['x' => $center - $hemHalf, 'y' => $length];
        $frontTop = ['x' => 0.0, 'y' => $capHeight];

        // درز شیپوری: تا نقطه شروع گشادی تنگ می‌ماند و از آنجا باز می‌شود
        $flareStart = (float) ($spec['flare_start'] ?? 0);
        $backKnee = null;
        $frontKnee = null;

        if ($flareStart > 0.05) {
            $kneeY = $capHeight + (($length - $capHeight) * $flareStart);
            $kneeHalf = ((float) ($spec['flare_start_width'] ?? $width * 0.5)) / 2;
            $backKnee = ['x' => $center + $kneeHalf, 'y' => $kneeY];
            $frontKnee = ['x' => $center - $kneeHalf, 'y' => $kneeY];
        }

        // درز پشت آستین: از زیر بغل پشت تا دم آستین، با کمی برآمدگی بیرونی
        if ($backKnee !== null) {
            $upper = $this->seamControl($backTop, $backKnee, $bulge, $bulgeAt, +1);
            $outline[] = Geometry::curve($backKnee['x'], $backKnee['y'], $upper['x'], $upper['y']);
            $edges[] = 'side';
            $lower = $this->seamControl($backKnee, $backHem, 0.4, 0.5, +1);
            $outline[] = Geometry::curve($backHem['x'], $backHem['y'], $lower['x'], $lower['y']);
            $edges[] = 'side';
        } else {
            $backControl = $this->seamControl($backTop, $backHem, $bulge, $bulgeAt, +1);
            $outline[] = Geometry::curve($backHem['x'], $backHem['y'], $backControl['x'], $backControl['y']);
            $edges[] = 'side';
        }

        // دم آستین
        if ($hemDrop > 0.05) {
            $outline[] = Geometry::curve(
                $frontHem['x'],
                $frontHem['y'],
                $center,
                $length + $hemDrop + ($backExtra / 2),
            );
        } else {
            $outline[] = Geometry::point($frontHem['x'], $frontHem['y']);
        }

        $edges[] = 'hem';

        // درز جلو آستین لبه بسته‌شدن است؛ اطلاعات منحنی‌اش روی نقطه اول می‌نشیند
        if ($frontKnee !== null) {
            $lower = $this->seamControl($frontHem, $frontKnee, 0.4, 0.5, -1);
            $outline[] = Geometry::curve($frontKnee['x'], $frontKnee['y'], $lower['x'], $lower['y']);
            $edges[] = 'side';
            $upper = $this->seamControl($frontKnee, $frontTop, $bulge, 1 - $bulgeAt, -1);
            $outline[0] = Geometry::curve(0, $capHeight, $upper['x'], $upper['y']);
        } else {
            $frontControl = $this->seamControl($frontHem, $frontTop, $bulge, 1 - $bulgeAt, -1);
            $outline[0] = Geometry::curve(0, $capHeight, $frontControl['x'], $frontControl['y']);
        }

        $edges[] = 'side';

        return [$outline, $edges];
    }

    /**
     * نقطه کنترل یک درز با برآمدگی مشخص به بیرون.
     *
     * @return array{x: float, y: float}
     */
    protected function seamControl(array $from, array $to, float $bulge, float $at, int $direction): array
    {
        $at = max(0.15, min(0.85, $at));
        $base = Geometry::lerp($from, $to, $at);

        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $length = sqrt(($dx * $dx) + ($dy * $dy));

        if ($length < 0.01) {
            return $base;
        }

        // بردار عمود بر درز؛ جهت مثبت یعنی به سمت بیرون آستین
        $nx = ($dy / $length) * $direction;
        $ny = (-$dx / $length) * $direction;

        return [
            'x' => $base['x'] + (2 * $bulge * $nx),
            'y' => $base['y'] + (2 * $bulge * $ny),
        ];
    }

    /* ---------------------------------------------------------------------
     |  قطعه آماده
     * ------------------------------------------------------------------- */

    /**
     * یک آستین کامل: منحنی سرآستین، بدنه، نشانه‌ها، خط بازو و آرنج و یادداشت فارسی.
     *
     * @return array<string, mixed>
     */
    protected function sleevePiece(array $frame, array $spec = []): array
    {
        [$outline, $edges] = $this->sleeveBody($frame, $spec);

        return $this->finishSleeve($outline, $edges, $frame, $spec);
    }

    /**
     * قطعه آستین را از مسیر و برچسب لبه‌ها می‌سازد (سرآستین باید لبه‌های ۰ تا ۳ باشد).
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @param  array<int, string>  $edges
     * @return array<string, mixed>
     */
    protected function finishSleeve(array $outline, array $edges, array $frame, array $spec = []): array
    {
        $width = (float) $frame['width'];
        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];
        $center = (float) $frame['center'];
        $capEdges = $spec['cap_edges'] ?? [0, 1, 2, 3];

        $capLength = Geometry::edgesLength($outline, $capEdges);
        $share = (float) ($spec['armhole_share'] ?? 1);
        $armhole = ((float) $frame['armhole']) * $share;
        $ease = round($capLength - $armhole, 2);

        $notches = $spec['notches'] ?? $this->capNotches($outline, $frame, $spec);
        $markers = $this->sleeveMarkers($frame, $spec);
        $darts = [];
        $meta = [
            'part' => $spec['part'] ?? 'sleeve',
            'edges' => $edges,
            'fold_edges' => [],
            'sleeve_family' => $spec['family'] ?? 'set_in',
            'cap_height' => round($capHeight, 2),
            'cap_length' => round($capLength, 2),
            'target_armhole' => round((float) $frame['armhole'], 2),
            'armhole_length' => round((float) $frame['armhole'], 2),
            'armhole_share' => round($share, 4),
            'matched_armhole' => round($armhole, 2),
            'cap_ease' => $ease,
            'cap_ease_band' => $spec['ease_band'] ?? null,
            'cap_fit' => $frame['cap_status'],
            'bicep_width' => round($width, 2),
            'hem_width' => round((float) $frame['hem_width'], 2),
            'sleeve_length' => round($length, 2),
            'elbow_y' => round((float) $frame['elbow_y'], 2),
            'notch_convention' => 'یک نشانه جلو، دو نشانه پشت',
        ];

        // ساسون آرنج: بلندی اضافه درز پشت را جمع می‌کند تا دو درز هم‌اندازه شوند
        $elbowDart = (float) ($spec['elbow_dart'] ?? 0);

        if ($elbowDart > 0.4) {
            $backEdge = (int) ($spec['back_seam_edge'] ?? 4);
            $elbowY = (float) $frame['elbow_y'];
            $t = $this->tAtY($outline, $backEdge, $elbowY);
            $on = Geometry::pointOnEdge($outline, $backEdge, $t);

            $darts[] = $this->dart(
                'elbow',
                'ساسون آرنج',
                $backEdge,
                $on['x'],
                $on['y'],
                $elbowDart,
                $center + (($on['x'] - $center) * 0.28),
                $on['y'],
                'y',
            );

            $meta['elbow_dart'] = round($elbowDart, 2);
        }

        $gathers = $this->sleeveGathers($outline, $edges, $frame, $spec);

        if ($gathers !== []) {
            $meta = $this->recordFullness($meta, $gathers);
        }

        $meta['notes'] = $this->sleeveNotes($frame, $meta, $spec);
        $meta = array_merge($meta, $spec['meta'] ?? []);

        return $this->piece([
            'code' => $spec['code'] ?? 'sleeve',
            'name' => $spec['name'] ?? 'آستین',
            'cut_quantity' => (int) ($spec['cut'] ?? 2),
            'mirror' => (bool) ($spec['mirror'] ?? true),
            'outline' => $outline,
            'grainline' => $this->grainline($center, $capHeight * 0.35, $length - 2),
            'darts' => $darts,
            'notches' => $notches,
            'markers' => $markers,
            'meta' => $meta,
        ]);
    }

    /**
     * نشانه‌های سرآستین: نوک آستین، یک نشانه تکی جلو، نشانه دوتایی پشت و دو زیر بغل.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function capNotches(array $outline, array $frame, array $spec = []): array
    {
        $capEdges = array_values($spec['cap_edges'] ?? [0, 1, 2, 3]);
        $front = $capEdges[1] ?? 1;
        $back = $capEdges[2] ?? 2;
        $capHeight = (float) $frame['cap_height'];

        $frontPoint = Geometry::pointOnEdge($outline, $front, 0.55);
        $backOne = Geometry::pointOnEdge($outline, $back, 0.40);
        $backTwo = Geometry::pointOnEdge($outline, $back, 0.52);
        $top = Geometry::pointOnEdge($outline, $front, 1.0);

        $notches = [
            $this->notch($top['x'], $top['y'], $front, 'نوک آستین (سرشانه)', 'sleeve_top'),
            $this->notch($frontPoint['x'], $frontPoint['y'], $front, 'نشانه تکی جلو آستین', 'armhole_front'),
            $this->notch($backOne['x'], $backOne['y'], $back, 'نشانه دوتایی پشت آستین (۱)', 'armhole_back'),
            $this->notch($backTwo['x'], $backTwo['y'], $back, 'نشانه دوتایی پشت آستین (۲)', 'armhole_back'),
        ];

        if (($spec['underarm_notches'] ?? true) && ! isset($spec['cap_edges'])) {
            $lastEdge = count($outline) - 1;
            $notches[] = $this->notch(0, $capHeight, $lastEdge, 'زیر بغل جلو', 'underarm');
            $notches[] = $this->notch((float) $frame['width'], $capHeight, $capEdges[3] + 1, 'زیر بغل پشت', 'underarm');
        }

        return $notches;
    }

    /**
     * خط بازو، خط میانی و خط آرنج.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sleeveMarkers(array $frame, array $spec = []): array
    {
        $width = (float) $frame['width'];
        $capHeight = (float) $frame['cap_height'];
        $length = (float) $frame['length'];
        $center = (float) $frame['center'];
        $elbowY = (float) $frame['elbow_y'];

        if ($spec['replace_markers'] ?? false) {
            return array_values($spec['markers'] ?? []);
        }

        $markers = [
            $this->marker('bicep', 'خط بازو', 0, $capHeight, $width),
            $this->marker('sleeve_center', 'خط میانی آستین', $center, 0, $center, $length),
        ];

        if ($elbowY < $length - 1.0 && $elbowY > $capHeight + 0.5) {
            $markers[] = $this->marker('elbow', 'خط آرنج', $center - ($width * 0.42), $elbowY, $center + ($width * 0.42), $elbowY);
        }

        foreach ($spec['markers'] ?? [] as $marker) {
            $markers[] = $marker;
        }

        return $markers;
    }

    /**
     * چین‌های ثبت‌شده روی سرآستین و دم آستین.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sleeveGathers(array $outline, array $edges, array $frame, array $spec = []): array
    {
        $records = [];
        $capEdges = array_values($spec['cap_edges'] ?? [0, 1, 2, 3]);
        $capLength = Geometry::edgesLength($outline, $capEdges);
        $armhole = ((float) $frame['armhole']) * (float) ($spec['armhole_share'] ?? 1);
        $capExcess = round($capLength - $armhole, 2);

        if (($spec['gather_cap'] ?? false) && $capExcess > 0.3) {
            $records = array_merge($records, $this->spreadOverEdges($outline, $capEdges, $capExcess, 'چین سرآستین'));
        }

        $hemGather = (float) ($spec['hem_gather'] ?? 0);

        if ($hemGather > 0.3) {
            $hemEdges = [];

            foreach ($edges as $index => $tag) {
                if ($tag === 'hem') {
                    $hemEdges[] = $index;
                }
            }

            if ($hemEdges !== []) {
                $records = array_merge($records, $this->spreadOverEdges($outline, $hemEdges, $hemGather, 'چین دم آستین'));
            }
        }

        return $records;
    }

    /**
     * پخش یک مقدار چین روی چند لبه به نسبت طولشان.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function spreadOverEdges(array $outline, array $edgeIndexes, float $amount, string $label): array
    {
        $total = Geometry::edgesLength($outline, $edgeIndexes);

        if ($total <= 0.01) {
            return [];
        }

        $records = [];

        foreach ($edgeIndexes as $index) {
            $share = Geometry::edgeLength($outline, $index) / $total;

            $records[] = [
                'edge' => $index,
                'amount' => round($amount * $share, 2),
                'label' => $label,
            ];
        }

        return $records;
    }

    /**
     * ثبت چین در meta؛ اگر ابزار مشترک FullnessRecorder موجود باشد از آن استفاده می‌شود.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    protected function recordFullness(array $meta, array $records): array
    {
        $recorder = 'App\\Services\\Pattern\\Transform\\FullnessRecorder';

        if (class_exists($recorder) && method_exists($recorder, 'record')) {
            try {
                $result = $recorder::record($meta, $records);

                if (is_array($result) && isset($result['gathers'])) {
                    return $result;
                }
            } catch (Throwable) {
                // ابزار مشترک هنوز امضای دیگری دارد؛ با ثبت محلی ادامه می‌دهیم
            }
        }

        $meta['gathers'] = array_merge($meta['gathers'] ?? [], $records);
        $meta['fullness'] = round(array_sum(array_column($records, 'amount')), 2);

        return $meta;
    }

    /**
     * یادداشت‌های فارسی قطعه: آزادی سرآستین، چین و هشدارها.
     *
     * @return array<int, string>
     */
    protected function sleeveNotes(array $frame, array $meta, array $spec = []): array
    {
        $notes = [];
        $ease = (float) $meta['cap_ease'];
        $armhole = (float) $meta['matched_armhole'];
        $band = $spec['ease_band'] ?? null;

        if (($spec['gather_cap'] ?? false)) {
            $notes[] = 'سرآستین '.Format::cm((float) $meta['cap_length']).' است و روی حلقه '
                .Format::cm($armhole).' چین می‌خورد؛ '.Format::cm($ease).' چین سرشانه.';
        } else {
            $notes[] = 'سرآستین روی حلقه آستین پیاده شد: حلقه '.Format::cm($armhole).'، سرآستین '
                .Format::cm((float) $meta['cap_length']).'، یعنی '.Format::cm($ease)
                .' آزادی برای پرکردن سرشانه.';
        }

        if ($band !== null) {
            $notes[] = 'آزادی مناسب این مدل بین '.Format::cm($band[0]).' و '.Format::cm($band[1]).' است.';
        }

        if (($frame['cap_status'] ?? 'fitted') === 'too_wide') {
            $notes[] = 'دور بازو برای این حلقه آستین بزرگ است؛ سرآستین کوتاه‌تر از حلقه ماند. '
                .'دور بازو را کم کنید یا حلقه آستین را گودتر بگیرید.';
        }

        if (($frame['cap_status'] ?? 'fitted') === 'too_narrow') {
            $notes[] = 'حلقه آستین برای این دور بازو خیلی بلند است؛ آزادی بازو را بیشتر بگیرید.';
        }

        if (! empty($meta['elbow_dart'])) {
            $notes[] = 'ساسون آرنج به اندازه '.Format::cm((float) $meta['elbow_dart'])
                .' گرفته شد تا درز پشت و جلوی آستین هم‌اندازه شوند.';
        }

        foreach ($spec['notes'] ?? [] as $note) {
            $notes[] = $note;
        }

        return $notes;
    }

    /**
     * پنل آستین: ذوزنقه‌ای با درزهای کمی منحنی — نیمه پایین آستین فانوسی و ژولیت.
     *
     * @return array<string, mixed>
     */
    protected function sleevePanelPiece(float $topWidth, float $bottomWidth, float $height, array $o = []): array
    {
        $top = max(6.0, $topWidth);
        $bottom = max(6.0, $bottomWidth);
        $height = max(4.0, $height);
        $bulge = (float) ($o['bulge'] ?? 0.4);
        $left = ($top - $bottom) / 2;

        $rightTop = ['x' => $top, 'y' => 0.0];
        $rightBottom = ['x' => $top - $left, 'y' => $height];
        $leftBottom = ['x' => $left, 'y' => $height];
        $leftTop = ['x' => 0.0, 'y' => 0.0];

        $rightControl = $this->seamControl($rightTop, $rightBottom, $bulge, 0.5, +1);
        $leftControl = $this->seamControl($leftBottom, $leftTop, $bulge, 0.5, -1);

        $outline = [
            Geometry::curve(0, 0, $leftControl['x'], $leftControl['y']),
            Geometry::point($top, 0),
            Geometry::curve($rightBottom['x'], $rightBottom['y'], $rightControl['x'], $rightControl['y']),
            Geometry::point($leftBottom['x'], $leftBottom['y']),
        ];

        $meta = array_merge([
            'part' => $o['part'] ?? 'sleeve_panel',
            'edges' => [$o['top_tag'] ?? 'default', 'side', 'hem', 'side'],
            'fold_edges' => [],
            'top_width' => round($top, 2),
            'bottom_width' => round($bottom, 2),
            'panel_height' => round($height, 2),
        ], $o['meta'] ?? []);

        return $this->piece([
            'code' => $o['code'] ?? 'sleeve-panel',
            'name' => $o['name'] ?? 'پنل آستین',
            'cut_quantity' => (int) ($o['cut'] ?? 2),
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($top / 2, 1.5, $height - 1.5),
            'markers' => [
                $this->marker('panel_center', 'خط میانی', $top / 2, 0, $top / 2, $height),
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * مچ‌بند ساده آستین اسقفی و پفی مچ: نواری دولا با خط تای وسط.
     *
     * @return array<string, mixed>
     */
    protected function sleeveCuffPiece(float $wrist, float $height, array $o = []): array
    {
        $width = max(10.0, $wrist + (float) ($o['overlap'] ?? 5));
        $full = max(3.0, $height) * 2;

        return $this->piece([
            'code' => $o['code'] ?? 'sleeve-cuff',
            'name' => $o['name'] ?? 'مچ آستین',
            'cut_quantity' => 2,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $full - 1.5),
            'markers' => [
                $this->marker('fold', 'خط تای مچ‌بند', 0, $height, $width),
            ],
            'meta' => [
                'part' => 'cuff',
                'edges' => ['default', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
                'wrist' => round($wrist, 2),
                'notes' => ['دم آستین روی این مچ‌بند چین می‌خورد.'],
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     |  ابزار هندسی مشترک
     * ------------------------------------------------------------------- */

    /** نسبت t روی یک لبه که در آن y به مقدار خواسته‌شده می‌رسد (لبه باید یکنوا باشد). */
    protected function tAtY(array $outline, int $edge, float $y): float
    {
        $low = 0.0;
        $high = 1.0;
        $descending = Geometry::pointOnEdge($outline, $edge, 0.0)['y'] > Geometry::pointOnEdge($outline, $edge, 1.0)['y'];

        for ($i = 0; $i < 30; $i++) {
            $mid = ($low + $high) / 2;
            $at = Geometry::pointOnEdge($outline, $edge, $mid)['y'];

            if (($at < $y) !== $descending) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /** نسبت t روی یک لبه که در آن x به مقدار خواسته‌شده می‌رسد (لبه باید یکنوا باشد). */
    protected function tAtX(array $outline, int $edge, float $x): float
    {
        $low = 0.0;
        $high = 1.0;
        $ascending = Geometry::pointOnEdge($outline, $edge, 0.0)['x'] < Geometry::pointOnEdge($outline, $edge, 1.0)['x'];

        for ($i = 0; $i < 30; $i++) {
            $mid = ($low + $high) / 2;
            $at = Geometry::pointOnEdge($outline, $edge, $mid)['x'];

            if (($at < $x) === $ascending) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * شکستن یک کمان درجه‌دو در نسبت t (الگوریتم دی‌کاستلژو).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array{x: float, y: float}}
     */
    protected function splitCurve(array $from, array $to, float $t): array
    {
        $p0 = ['x' => (float) $from['x'], 'y' => (float) $from['y']];
        $p1 = ['x' => (float) $to['x'], 'y' => (float) $to['y']];

        if (! Geometry::isCurve($to)) {
            $mid = Geometry::lerp($p0, $p1, $t);

            return [Geometry::point($mid['x'], $mid['y']), Geometry::point($p1['x'], $p1['y']), $mid];
        }

        $control = ['x' => (float) $to['cx'], 'y' => (float) $to['cy']];
        $q0 = Geometry::lerp($p0, $control, $t);
        $q1 = Geometry::lerp($control, $p1, $t);
        $mid = Geometry::lerp($q0, $q1, $t);

        return [
            Geometry::curve($mid['x'], $mid['y'], $q0['x'], $q0['y']),
            Geometry::curve($p1['x'], $p1['y'], $q1['x'], $q1['y']),
            $mid,
        ];
    }

    /**
     * چرخاندن مسیر حول یک نقطه (نقطه‌های کنترل هم می‌چرخند).
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>
     */
    protected function rotatePoints(array $points, float $angle, array $about): array
    {
        $cos = cos($angle);
        $sin = sin($angle);

        $spin = function (float $x, float $y) use ($cos, $sin, $about) {
            $dx = $x - $about['x'];
            $dy = $y - $about['y'];

            return [
                'x' => $about['x'] + ($dx * $cos) - ($dy * $sin),
                'y' => $about['y'] + ($dx * $sin) + ($dy * $cos),
            ];
        };

        return array_map(function (array $point) use ($spin) {
            $moved = $spin((float) $point['x'], (float) $point['y']);
            $point['x'] = round($moved['x'], 4);
            $point['y'] = round($moved['y'], 4);

            if (isset($point['cx'], $point['cy'])) {
                $control = $spin((float) $point['cx'], (float) $point['cy']);
                $point['cx'] = round($control['x'], 4);
                $point['cy'] = round($control['y'], 4);
            }

            return $point;
        }, array_values($points));
    }

    /**
     * وارونه‌کردن ترتیب یک زنجیره از نقطه‌ها (منحنی‌ها حفظ می‌شوند).
     *
     * زنجیره ورودی از $start شروع می‌شود و نقطه‌های بعدی به آن می‌رسند؛ خروجی از
     * آخرین نقطه شروع می‌شود و به $start می‌رسد.
     *
     * @param  array<int, array<string, mixed>>  $chain
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function reverseChain(array $start, array $chain): array
    {
        $chain = array_values($chain);
        $count = count($chain);

        if ($count === 0) {
            return [$start, []];
        }

        $newStart = $chain[$count - 1];
        $newStart = Geometry::point((float) $newStart['x'], (float) $newStart['y']);

        $out = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $target = $i === 0 ? $start : $chain[$i - 1];
            $current = $chain[$i];

            $out[] = Geometry::isCurve($current)
                ? Geometry::curve((float) $target['x'], (float) $target['y'], (float) $current['cx'], (float) $current['cy'])
                : Geometry::point((float) $target['x'], (float) $target['y']);
        }

        return [$newStart, $out];
    }

    /**
     * فیلدهای مشترک فرم همه آستین‌ها.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function commonSleeveSchema(float $capEase = 1.5, float $lengthPercent = 100, float $hemEase = 6): array
    {
        return [
            'armhole_length' => [
                'label' => 'طول حلقه آستین بالاتنه', 'min' => 0, 'max' => 90, 'step' => 0.5, 'default' => 0,
                'unit' => 'سانتی‌متر', 'hint' => 'صفر یعنی از دور حلقه آستین بدن حساب شود.',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 6, 'step' => 0.25, 'default' => $capEase,
                'unit' => 'سانتی‌متر', 'hint' => 'پارچه تخت ۱٫۵ تا ۴ سانتی‌متر؛ برای پارچه کشی صفر بگذارید.',
            ],
            'length_percent' => [
                'label' => 'بلندی آستین', 'min' => 10, 'max' => 115, 'step' => 1, 'default' => $lengthPercent,
                'unit' => 'درصد قد آستین',
                'hint' => '۱۵٪ کپ، ۳۰٪ کوتاه، ۵۵٪ تا آرنج، ۷۵٪ سه‌ربع و ۱۰۰٪ بلند.',
            ],
            'length_extra' => [
                'label' => 'تنظیم بلندی', 'min' => -20, 'max' => 15, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'hem_ease' => [
                'label' => 'آزادی دم آستین', 'min' => 0, 'max' => 25, 'step' => 0.5, 'default' => $hemEase,
                'unit' => 'سانتی‌متر',
            ],
        ];
    }
}
