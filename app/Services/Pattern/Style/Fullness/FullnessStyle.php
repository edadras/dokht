<?php

namespace App\Services\Pattern\Style\Fullness;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Detail\DetailStyle;
use App\Services\Pattern\Transform\DartTool;
use App\Services\Pattern\Transform\FullnessRecorder;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * پایه مشترک سبک‌های «چین و گشادی».
 *
 * این گروه روی قطعه‌های آماده پایین‌تنه کار می‌کند — پنل دامن یا پای شلوار — و
 * پارچه را کم یا زیاد می‌کند: چین، پیلی، گودت، طبقه، کلوش، باریک کردن، های‌لو و
 * برگردان دم پا.
 *
 * سه قاعده در همه‌شان یکی است:
 *
 *   ۱. «اندازه تمام‌شده» و «پارچه» دو چیزند. هر سبک که پارچه را زیاد می‌کند باید
 *      همان‌قدر چین یا پیلی ثبت کند تا اندازه تمام‌شده دست‌نخورده بماند؛ برای همین
 *      همه‌جا از FullnessRecorder استفاده می‌شود و نه از دست‌کاری خودسر meta.
 *
 *   ۲. هر چیزی که روی درز پهلو اثر بگذارد روی جلو و پشت با هم اجرا می‌شود، وگرنه
 *      دو درز دیگر به هم نمی‌خورند.
 *
 *   ۳. هر سبک گزارش می‌دهد چقدر پارچه بیشتر (یا کمتر) خواسته است؛ این عدد در
 *      meta.added_fabric می‌نشیند و در یادداشت‌ها هم گفته می‌شود.
 */
abstract class FullnessStyle extends DetailStyle
{
    /** قطعه‌هایی که این گروه رویشان کار می‌کند. */
    public const LOWER_PARTS = [
        'skirt_front', 'skirt_back', 'skirt_panel', 'skirt_tier', 'front_leg', 'back_leg',
    ];

    public static function group(): string
    {
        return 'fullness';
    }

    /* ---------------------------------------------------------------------
     |  پیداکردن میزبان
     * ------------------------------------------------------------------- */

    /**
     * شماره پنل‌های پایین‌تنه‌ای که هم لبه دم دارند و هم لبه کمر یا درز پهلو.
     *
     * @return array<int, int>
     */
    protected function panelIndexes(array $pieces, string $needs = 'hem'): array
    {
        $out = [];

        foreach ($pieces as $index => $piece) {
            if (! in_array($this->partOf($piece), static::LOWER_PARTS, true)) {
                continue;
            }

            if ($this->edgeWithTag($piece, $needs) === null) {
                continue;
            }

            $out[] = (int) $index;
        }

        return $out;
    }

    /** پیام یکسان وقتی لباس هیچ پنل پایین‌تنه‌ای ندارد. */
    protected function noPanelMessage(): string
    {
        return 'این سبک روی دامن یا شلوار اجرا می‌شود؛ این لباس هیچ پنل پایین‌تنه‌ای ندارد.';
    }

    public function supports(array $pieces, array $context): true|string
    {
        return $this->panelIndexes($pieces) === [] ? $this->noPanelMessage() : true;
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری
     * ------------------------------------------------------------------- */

    /**
     * مقداری از یک لبه که در دوخت خورده می‌شود.
     *
     * روی نسخه DetailStyle این را کامل می‌کند: پیلی و چینی که با FullnessRecorder
     * در meta ثبت شده و «کم‌شدنی» که پنل‌های دامن در meta.fullness نوشته‌اند هم
     * حساب می‌شوند، وگرنه اندازه تمام‌شده یک پنل چین‌دار غلط درمی‌آید.
     */
    protected function consumedOn(array $piece, int $edge): float
    {
        $total = FullnessRecorder::consumedOn($piece, $edge);
        $legacy = 0.0;

        foreach ($piece['meta']['fullness'] ?? [] as $entry) {
            if ((int) ($entry['edge'] ?? -1) === $edge) {
                $legacy += (float) ($entry['takeup'] ?? 0);
            }
        }

        return round(max($total, $legacy, parent::consumedOn($piece, $edge)), 3);
    }

    /** طول خام یک درز (بدون کم کردن ساسون و چین). */
    protected function rawLength(array $piece, string $tag): float
    {
        $total = 0.0;

        foreach ($this->edgesWithTag($piece, $tag) as $edge) {
            $total += $this->edgeLength($piece, $edge);
        }

        return round($total, 3);
    }

    /** دور خام یک برچسب لبه در همه قطعه‌ها (پارچه، نه اندازه تمام‌شده). */
    protected function rawGirth(array $pieces, string $tag, array $only): float
    {
        $total = 0.0;

        foreach ($only as $index) {
            $total += $this->repeats($pieces[$index]) * $this->rawLength($pieces[$index], $tag);
        }

        return round($total, 2);
    }

    /* ---------------------------------------------------------------------
     |  عملیات
     * ------------------------------------------------------------------- */

    /**
     * لبه‌های «درز پهلو» یک پنل.
     *
     * روی پای شلوار برچسب side هم روی درز پهلو است و هم روی درز داخل پا، پس اول
     * به meta.side_edges نگاه می‌شود که خود درفت شلوار نوشته است.
     *
     * @return array<int, int>
     */
    protected function sideEdges(array $piece): array
    {
        $known = $piece['meta']['side_edges'] ?? null;

        if (is_array($known) && $known !== []) {
            return array_values(array_map('intval', $known));
        }

        return $this->edgesWithTag($piece, 'side');
    }

    /**
     * پهن کردن پنل به اندازه ثابت در همه ترازها (جای پیلی).
     *
     * پیلی تای موازی راستای پارچه است، پس جایش باید در همه بلندی پنل یک‌اندازه
     * اضافه شود نه به شکل گوه؛ برای همین کل نیم‌رخ درز پهلو افقی جابه‌جا می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function widenPanel(array $piece, float $amount): array
    {
        if ($amount <= 0.001) {
            return $piece;
        }

        $edges = $this->sideEdges($piece);

        if ($edges === []) {
            return $piece;
        }

        return PieceOps::extend($piece, 'side', $amount, [
            'edges' => $edges,
            'direction' => ['x' => 1.0, 'y' => 0.0],
        ]);
    }

    /**
     * برش و باز کردن (slash & spread): پارچه از یک لبه باز می‌شود و لبه روبه‌رو
     * سر جایش می‌ماند.
     *
     * $from برچسب لبه‌ای است که نقطه پرگار (نوک) رویش می‌نشیند و $to لبه‌ای که باز
     * می‌شود. هر بار برش، لبه‌ها را دوباره شماره‌گذاری می‌کند، پس هر گام لبه‌ها را
     * از روی برچسب تازه پیدا می‌کنیم.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function slashSpread(
        array $piece,
        int $count,
        float $each,
        string $from,
        string $to,
    ): array {
        for ($i = 0; $i < max(0, $count); $i++) {
            $pivots = Geometry::edgesWithTag($piece, $from);
            $targets = Geometry::edgesWithTag($piece, $to);

            if ($pivots === [] || $targets === []) {
                break;
            }

            $pivot = $pivots[count($pivots) - 1];
            $target = $targets[count($targets) - 1];

            if ($pivot === $target) {
                break;
            }

            // بدون ثبت: شماره لبه‌ها بعد از هر برش عوض می‌شود، پس چین را در پایان
            // و یک‌جا روی همه لبه‌های همان درز پخش می‌کنیم (recordAcross).
            $piece = DartTool::open($piece, [
                'apex' => Geometry::pointOnEdge($piece['outline'], $pivot, 0.5),
                'edge' => $target,
                't' => 0.5,
                'intake' => $each,
                'record' => 'none',
                'tag' => $to,
            ]);
        }

        return $piece;
    }

    /**
     * ثبت چین یا پیلی روی «یک درز» که ممکن است چند لبه شده باشد.
     *
     * مقدار به نسبت طول هر لبه پخش می‌شود، وگرنه اگر همه‌اش روی یک لبه کوتاه
     * بنشیند، طول دوخته‌شده آن لبه منفی می‌شود و اندازه تمام‌شده غلط درمی‌آید.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function recordAcross(array $piece, string $tag, float $total, string $kind, array $options = []): array
    {
        $edges = Geometry::edgesWithTag($piece, $tag);

        if ($edges === [] || $total <= 0.001) {
            return $piece;
        }

        $lengths = [];
        $sum = 0.0;

        foreach ($edges as $edge) {
            $lengths[$edge] = Geometry::edgeLength($piece['outline'], $edge);
            $sum += $lengths[$edge];
        }

        if ($sum <= 0.01) {
            return $piece;
        }

        foreach ($edges as $edge) {
            $piece = FullnessRecorder::clear($piece, $edge, $kind);
        }

        foreach ($edges as $edge) {
            $share = $total * ($lengths[$edge] / $sum);

            if ($share > 0.001) {
                $piece = FullnessRecorder::record($piece, $kind, $edge, $share, array_merge([
                    'source' => static::key(),
                ], $options));
            }
        }

        return $piece;
    }

    /**
     * جابه‌جا کردن سر «پهلو»ی یک لبه دم روی خودش (باریک یا پهن کردن دم).
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function moveHemCorner(array $piece, int $edge, float $amount): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $a = $edge % $count;
        $b = ($edge + 1) % $count;

        // سرِ پهلو همان سرِ دورتر از خط مرکز (بیشترین x) است
        $side = ((float) $outline[$a]['x']) >= ((float) $outline[$b]['x']) ? $a : $b;
        $inner = $side === $a ? $b : $a;

        $from = ['x' => (float) $outline[$side]['x'], 'y' => (float) $outline[$side]['y']];
        $to = ['x' => (float) $outline[$inner]['x'], 'y' => (float) $outline[$inner]['y']];
        $span = Geometry::distance($from, $to);

        if ($span < 0.05) {
            return $piece;
        }

        $moved = Geometry::lerp($from, $to, max(-0.9, min(0.9, $amount / $span)));
        $outline[$side]['x'] = round($moved['x'], 3);
        $outline[$side]['y'] = round($moved['y'], 3);

        if (isset($outline[$side]['cx'], $outline[$side]['cy'])) {
            $outline[$side]['cx'] = round(((float) $outline[$side]['cx']) + (($moved['x'] - $from['x']) * 0.5), 3);
            $outline[$side]['cy'] = round(((float) $outline[$side]['cy']) + (($moved['y'] - $from['y']) * 0.5), 3);
        }

        $piece['outline'] = $outline;

        return $this->reindexAnchors(Geometry::normalizePiece($piece));
    }

    /**
     * بالا و پایین بردن دو سر لبه دم و کشیدن منحنی بینشان.
     *
     * مقدار مثبت یعنی بلندتر (پایین‌تر). سرِ «مرکز» همان سرِ نزدیک به خط مرکز
     * (کمترین x) و سرِ «پهلو» سرِ دیگر است.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function shapeHemEnds(array $piece, int $edge, float $centerDelta, float $sideDelta, float $curve = 0.0): array
    {
        $outline = array_values($piece['outline']);
        $count = count($outline);
        $a = $edge % $count;
        $b = ($edge + 1) % $count;
        $center = ((float) $outline[$a]['x']) <= ((float) $outline[$b]['x']) ? $a : $b;
        $side = $center === $a ? $b : $a;

        foreach ([$center => $centerDelta, $side => $sideDelta] as $index => $delta) {
            if (abs($delta) < 0.01) {
                continue;
            }

            $outline[$index]['y'] = round(((float) $outline[$index]['y']) + $delta, 3);

            if (isset($outline[$index]['cx'], $outline[$index]['cy'])) {
                $outline[$index]['cy'] = round(((float) $outline[$index]['cy']) + ($delta * 0.5), 3);
            }
        }

        if (abs($curve) > 0.01) {
            $middle = Geometry::lerp(
                ['x' => (float) $outline[$a]['x'], 'y' => (float) $outline[$a]['y']],
                ['x' => (float) $outline[$b]['x'], 'y' => (float) $outline[$b]['y']],
                0.5,
            );
            $outline[$b]['curve'] = true;
            $outline[$b]['cx'] = round($middle['x'], 3);
            $outline[$b]['cy'] = round($middle['y'] + $curve, 3);
        }

        $piece['outline'] = $outline;

        return $this->reindexAnchors(Geometry::normalizePiece($piece));
    }

    /**
     * کمان دایره حول مبدأ، برای گودت. زاویه صفر رو به پایین است.
     *
     * کمان به تکه‌های حداکثر ۱۵ درجه شکسته و هر تکه یک منحنی درجه‌دو می‌شود؛ خطای
     * طول این تقریب کمتر از ۰٫۰۱٪ است، پس طول کمان دم گودت همان پهنای خواسته‌شده
     * درمی‌آید.
     *
     * @return array<int, array<string, float|bool>>
     */
    protected function arcPoints(float $radius, float $from, float $to, bool $includeFirst = true): array
    {
        $sweep = $to - $from;
        $chunks = $this->arcEdgeCount($sweep);
        $step = $sweep / $chunks;
        $at = fn (float $angle, float $r) => [$r * sin($angle), $r * cos($angle)];

        $points = [];

        if ($includeFirst) {
            [$x, $y] = $at($from, $radius);
            $points[] = Geometry::point($x, $y);
        }

        $control = $radius / cos(abs($step) / 2);

        for ($i = 1; $i <= $chunks; $i++) {
            $end = $from + ($step * $i);
            [$cx, $cy] = $at($end - ($step / 2), $control);
            [$x, $y] = $at($end, $radius);
            $points[] = Geometry::curve($x, $y, $cx, $cy);
        }

        return $points;
    }

    protected function arcEdgeCount(float $sweep): int
    {
        return max(1, (int) ceil(abs($sweep) / deg2rad(15)));
    }

    /* ---------------------------------------------------------------------
     |  گزارش
     * ------------------------------------------------------------------- */

    /**
     * خروجی استاندارد این گروه، با گزارش پارچه.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, array<string, string>>  $notes
     */
    protected function report(array $pieces, array $notes, float $addedFabric, array $meta = []): array
    {
        if ($addedFabric > 0.05) {
            $notes[] = $this->fabricNote($addedFabric, $this->label());
        } elseif ($addedFabric < -0.05) {
            $notes[] = $this->note('info', $this->label().' حدود '.Format::cm(abs($addedFabric))
                .' پارچه کمتر می‌خواهد؛ در چیدمان به حساب بیاورید.');
        }

        return $this->result($pieces, $notes, array_merge([
            'added_fabric' => round($addedFabric, 2),
        ], $meta));
    }

    /** عدد فارسی برای یادداشت‌ها. */
    protected function fa(float|int|string $value): string
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }
}
