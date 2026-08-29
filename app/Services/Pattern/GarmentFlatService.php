<?php

namespace App\Services\Pattern;

use App\Models\PatternPiece;
use App\Services\Pattern\Transform\DartTool;
use Illuminate\Support\Collection;
use Throwable;

/**
 * نمای دوبعدی لباسِ دوخته‌شده، از چهار طرف.
 *
 * الگو نقشهٔ قطعه‌هاست، نه شکل لباس. خیاط از روی نقشه می‌فهمد چه درمی‌آید، ولی
 * مشتری نمی‌فهمد؛ و حتی خیاط هم پیش از برش دوست دارد ببیند این مدل روی *این*
 * اندازه چه شکلی می‌شود. این سرویس همان را می‌سازد: جلو، پشت، و دو پهلو.
 *
 * روشش تخمین نیست. نمای جلو، خودِ قطعهٔ جلوست پس از بستنِ ساسون‌ها و قرینه‌شدن
 * حول خط مرکزی — یعنی دقیقاً همان چیزی که بعد از دوخت جلوی چشم می‌آید. نمای
 * پشت هم همان‌طور از قطعه‌های پشت. دامن یا شلوار روی بالاتنه، سرِ خط کمر، سوار
 * می‌شود، و آستین از سرشانه آویزان.
 *
 * نمای پهلو تنها جایی است که حساب لازم دارد: لباسِ دوخته‌شده در هر ارتفاع یک
 * لوله است که پهنای تختش را می‌دانیم و دورش را هم. آن لوله را بیضی می‌گیریم —
 * قطر بزرگش همان پهنای تخت — و از محیط بیضی، قطر کوچک یعنی «ضخامت» لباس در آن
 * ارتفاع درمی‌آید. همان می‌شود پهنای نمای پهلو.
 *
 * هیچ خطی این‌جا سلیقه‌ای کشیده نمی‌شود؛ هر عددی از خودِ الگو و اندازهٔ مشتری
 * می‌آید. اگر مدلی قطعهٔ جلو یا پشت نداشته باشد، همان را می‌گوید و نمایی
 * نمی‌سازد — بهتر از کشیدنِ چیزی که پشتش عددی نیست.
 */
class GarmentFlatService
{
    /** چهار نما، به ترتیبی که کنار هم نشان داده می‌شوند. */
    public const VIEWS = [
        'front' => 'از جلو',
        'back' => 'از پشت',
        'right' => 'پهلوی راست',
        'left' => 'پهلوی چپ',
    ];

    /** قطعه‌هایی که پوستهٔ جلوی لباس‌اند. */
    protected const FRONT_PARTS = [
        'front_bodice', 'front_panel', 'skirt_front', 'front_leg',
        'swim_bottom_front', 'panty_front', 'boxer_front', 'apron_bib',
    ];

    /** قطعه‌هایی که پوستهٔ پشت لباس‌اند. */
    protected const BACK_PARTS = [
        'back_bodice', 'back_panel', 'skirt_back', 'back_leg',
        'swim_bottom_back', 'panty_back', 'boxer_back',
    ];

    /** قطعه‌هایی که تکه‌ای از دورِ لباس‌اند ولی جلو و پشتشان جدا نیست. */
    protected const PANEL_PARTS = ['panel', 'gore', 'godet', 'skirt_panel', 'skirt_tier', 'apron_skirt'];

    /** قطعه‌هایی که پایین‌تنه‌اند و سرِ کمر به بالاتنه می‌نشینند. */
    protected const LOWER_PARTS = [
        'skirt_front', 'skirt_back', 'front_leg', 'back_leg',
        'swim_bottom_front', 'swim_bottom_back', 'panty_front', 'panty_back',
        'boxer_front', 'boxer_back', 'apron_skirt',
    ];

    /**
     * تنه چقدر پهن‌تر از ضخیم است.
     *
     * مقطعِ تنهٔ آدم دایره نیست؛ بیضی‌ای است که پهنایش حدود یک‌ونیم برابرِ
     * ضخامتش است. این تنها عددی است که از خودِ الگو درنمی‌آید — و باید هم
     * درنیاید، چون الگو کاغذِ تخت است و تختی ضخامت ندارد.
     */
    protected const TORSO_RATIO = 1.45;

    /** فاصلهٔ نمونه‌برداری از بلندی لباس (سانتی‌متر). */
    protected const STEP = 0.8;

    /** زاویهٔ آویزان‌شدن آستین از سرشانه (درجه). */
    protected const SLEEVE_ANGLE = 18.0;

    /**
     * چهار نمای لباس، به‌علاوهٔ اندازه‌های دوخته‌شده‌اش.
     *
     * @param  iterable<int, array<string, mixed>|PatternPiece>  $pieces
     * @param  array<string, float|int>  $body  اندازهٔ همان مشتری
     * @return array{
     *     views: array<string, string>,
     *     measures: array<string, float>,
     *     notes: array<int, string>,
     *     ok: bool
     * }
     */
    public function flats(iterable $pieces, array $body, array $options = []): array
    {
        $pieces = $this->normalize($pieces);
        $notes = [];

        $front = $this->profile($pieces, static::FRONT_PARTS, $notes);
        $back = $this->profile($pieces, static::BACK_PARTS, $notes);

        // مدل‌های چندپانلی (دامن ترک، لباس با پانل‌های عمودی) جلو و پشت جدا
        // ندارند؛ پانل‌ها را دور تا دور جمع می‌کنیم و همان را هر دو طرف می‌گیریم
        if ($front === null && $back === null) {
            $round = $this->panelProfile($pieces);

            if ($round !== null) {
                $front = $back = $round;
                $notes[] = 'این مدل جلو و پشتِ جدا ندارد؛ نما از مجموع پانل‌های دور لباس ساخته شد.';
            }
        }

        $front ??= $back;
        $back ??= $front;

        if ($front === null) {
            return [
                'views' => [],
                'measures' => [],
                'notes' => ['این مدل قطعهٔ پوستهٔ شناخته‌شده‌ای ندارد، پس نمای دوختش کشیده نشد.'],
                'ok' => false,
            ];
        }

        $sleeve = $this->sleeve($pieces);
        $side = $this->sideProfile($front, $back, $body);

        $views = [
            'front' => $this->draw($front, $sleeve, 'front', $body, $options),
            'back' => $this->draw($back, $sleeve, 'back', $body, $options),
            'right' => $this->draw($side, $sleeve, 'right', $body, $options),
            'left' => $this->draw($side, $sleeve, 'left', $body, $options),
        ];

        return [
            'views' => $views,
            'measures' => $this->measures($front, $back, $side, $sleeve),
            'notes' => $notes,
            'ok' => true,
        ];
    }

    /* ------------------------------------------------------------------ *
     |  اندازه‌گیری: از قطعه‌ها به نیم‌رخِ لباس
     * ------------------------------------------------------------------ */

    /**
     * قطعه‌ها را به آرایهٔ ساده برمی‌گرداند، چه از درفت آمده باشند چه از جدول.
     *
     * @param  iterable<int, array<string, mixed>|PatternPiece>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function normalize(iterable $pieces): array
    {
        $out = [];

        foreach ($pieces as $piece) {
            if ($piece instanceof PatternPiece) {
                $piece = [
                    'code' => (string) $piece->code,
                    'name' => (string) $piece->name,
                    'cut_quantity' => (int) $piece->cut_quantity,
                    'on_fold' => (bool) $piece->on_fold,
                    'outline' => $piece->outline ?? [],
                    'darts' => $piece->darts ?? [],
                    'markers' => $piece->markers ?? [],
                    'meta' => $piece->meta ?? [],
                ];
            }

            if (is_array($piece) && ($piece['outline'] ?? []) !== []) {
                $out[] = $piece;
            }
        }

        return $out;
    }

    /**
     * نیم‌رخِ یک طرفِ لباس: در هر ارتفاع، نیم‌پهنای لباسِ دوخته‌شده.
     *
     * بالاتنه از سرشانه شروع می‌شود و پایین‌تنه سرِ خط کمر رویش می‌نشیند، چون
     * لباسِ دوخته‌شده همین است: یک تکه از شانه تا پایین. اگر مدل فقط پایین‌تنه
     * باشد (دامن، شلوار)، همان از کمر شروع می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $parts
     * @param  array<int, string>  $notes
     * @return array{heights: array<int, float>, halves: array<int, float>, neck: array{width: float, depth: float}, shoulder: float, top: float, bottom: float, legs: bool}|null
     */
    protected function profile(array $pieces, array $parts, array &$notes): ?array
    {
        $upper = [];
        $lower = [];

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (! in_array($part, $parts, true)) {
                continue;
            }

            in_array($part, static::LOWER_PARTS, true) ? $lower[] = $piece : $upper[] = $piece;
        }

        /*
         * لباسی که بالاتنه‌اش جلو و پشت دارد ولی دامنش از ترک و طبقه ساخته شده
         * (لباس شب، لباس کودکِ طبقه‌ای) پایین‌تنه‌اش را از این راه پیدا نمی‌کند و
         * فقط بالاتنه‌اش کشیده می‌شود — یعنی یک لباس صدوچهل سانتی، سی سانتی
         * نشان داده می‌شود. پس اگر بالاتنه هست و پایین‌تنه نیست، پانل‌ها را
         * پایین‌تنه می‌گیریم.
         */
        if ($upper !== [] && $lower === []) {
            foreach ($pieces as $piece) {
                if (in_array((string) ($piece['meta']['part'] ?? ''), static::PANEL_PARTS, true)) {
                    $lower[] = $piece;
                }
            }
        }

        if ($upper === [] && $lower === []) {
            return null;
        }

        // بالاتنه‌ای که خط کمر دارد، بلندترینشان: پایین‌تنه سرِ همان می‌نشیند
        $torso = $this->tallest($upper);
        $skirt = $this->tallest($lower);

        $samples = [];
        $neck = ['width' => 0.0, 'depth' => 0.0];
        $shoulder = 0.0;
        $offset = 0.0;

        // ارتفاعِ خط سینه، کمر و باسن روی خودِ لباس. این‌ها را نگه می‌داریم تا
        // بعداً اندازه‌ها را همان‌جایی بخوانیم که واقعاً هستند، نه در کسری
        // حدسی از بلندی لباس: «دور سینهٔ دامن» عددِ بی‌معنایی است.
        $levels = [];

        if ($torso !== null) {
            $shape = $this->sampleOne($torso);
            $samples = $shape['samples'];
            $neck = $shape['neck'];
            $shoulder = $shape['shoulder'];

            foreach (['bust', 'waist'] as $level) {
                $at = $this->levelY($torso, $level);

                if ($at !== null) {
                    $levels[$level] = round($at, 2);
                }
            }

            // پایین‌تنه از خط کمرِ بالاتنه شروع می‌شود، نه از صفر
            $waistY = $levels['waist'] ?? null;
            $offset = $waistY ?? $shape['bottom'];

            if ($skirt !== null && $waistY === null) {
                $notes[] = 'خط کمرِ بالاتنه پیدا نشد؛ پایین‌تنه از لبهٔ پایینِ بالاتنه سوار شد.';
            }
        }

        if ($skirt !== null) {
            $shape = $this->sampleOne($skirt);
            $skirtWaist = $this->levelY($skirt, 'waist') ?? $shape['top'];

            foreach ($shape['samples'] as $y => $half) {
                $at = round($offset + ($y - $skirtWaist), 2);

                if ($at < 0) {
                    continue;
                }

                $samples[(string) $at] = max($samples[(string) $at] ?? 0.0, $half);
            }

            $hipY = $this->levelY($skirt, 'hip');

            if ($hipY !== null) {
                $levels['hip'] = round($offset + ($hipY - $skirtWaist), 2);
            }

            $levels['waist'] ??= round($offset, 2);

            if ($torso === null) {
                $neck = $shape['neck'];
                $shoulder = $shape['shoulder'];
            }
        }

        if ($torso !== null && $skirt === null) {
            $hipY = $this->levelY($torso, 'hip');

            if ($hipY !== null) {
                $levels['hip'] = round($hipY, 2);
            }
        }

        if ($samples === []) {
            return null;
        }

        $heights = array_map('floatval', array_keys($samples));
        sort($heights);
        $halves = [];

        foreach ($heights as $h) {
            $halves[] = (float) $samples[(string) $h];
        }

        return [
            'heights' => $heights,
            'halves' => $halves,
            'neck' => $neck,
            'shoulder' => $shoulder > 0 ? $shoulder : ($halves[0] ?? 0.0),
            'top' => $heights[0],
            'bottom' => $heights[count($heights) - 1],
            'legs' => $this->hasLegs($lower),
            'levels' => $levels,
            'has_torso' => $torso !== null,
        ];
    }

    /**
     * نیم‌رخِ مدل‌های چندپانلی.
     *
     * دامن ترک جلو و پشت ندارد؛ هر ترک تکه‌ای از دور است. پس دورِ لباس در هر
     * ارتفاع را از جمعِ پانل‌ها می‌گیریم و چهارتقسیم می‌کنیم تا نیم‌پهنا دربیاید.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{heights: array<int, float>, halves: array<int, float>, neck: array{width: float, depth: float}, shoulder: float, top: float, bottom: float, legs: bool}|null
     */
    protected function panelProfile(array $pieces): ?array
    {
        $panels = [];

        foreach ($pieces as $piece) {
            if (in_array((string) ($piece['meta']['part'] ?? ''), static::PANEL_PARTS, true)) {
                $panels[] = $piece;
            }
        }

        if ($panels === []) {
            return null;
        }

        $totals = [];

        foreach ($panels as $panel) {
            $shape = $this->sampleOne($panel);
            $copies = max(1, (int) ($panel['cut_quantity'] ?? 1)) * (empty($panel['on_fold']) ? 1 : 2);

            foreach ($shape['samples'] as $y => $half) {
                $at = round((float) $y - $shape['top'], 2);
                // sampleOne نیم‌پهنا می‌دهد؛ پهنای خودِ ترک دو برابر آن است
                $totals[(string) $at] = ($totals[(string) $at] ?? 0.0) + ($half * 2 * $copies);
            }
        }

        if ($totals === []) {
            return null;
        }

        $heights = array_map('floatval', array_keys($totals));
        sort($heights);
        $halves = [];

        foreach ($heights as $h) {
            $halves[] = $totals[(string) $h] / 4.0;   // دور ← نیم‌پهنای تخت
        }

        return [
            'heights' => $heights,
            'halves' => $halves,
            'neck' => ['width' => 0.0, 'depth' => 0.0],
            'shoulder' => $halves[0] ?? 0.0,
            'top' => $heights[0],
            'bottom' => $heights[count($heights) - 1],
            'legs' => false,
            'levels' => [],
            'has_torso' => false,
        ];
    }

    /**
     * یک قطعه: نیم‌پهنا در هر ارتفاع، پس از بستنِ ساسون‌ها.
     *
     * ساسون بسته می‌شود چون لباسِ دوخته‌شده ساسونِ باز ندارد؛ اگر بازش بگذاریم،
     * قطعه پهن‌تر از لباس دیده می‌شود و نما دروغ می‌گوید.
     *
     * @param  array<string, mixed>  $piece
     * @return array{samples: array<string, float>, neck: array{width: float, depth: float}, shoulder: float, top: float, bottom: float}
     */
    protected function sampleOne(array $piece): array
    {
        $piece = $this->closeDarts($piece);
        $outline = Geometry::flatten($piece['outline']);
        $centre = $this->centreX($piece, $outline);
        $half = $this->isHalf($piece);

        [, $minY, , $maxY] = Geometry::bounds($piece['outline']);
        $top = (float) $minY;
        $bottom = (float) $maxY;
        $samples = [];

        // درست روی بالاترین و پایین‌ترین نقطهٔ قطعه، خطِ افقی فقط یک رأس را
        // می‌بُرد و پهنا صفر درمی‌آید. پس یک مو آن‌طرف‌تر نمونه می‌گیریم و همان
        // را به‌نامِ همان ارتفاع ثبت می‌کنیم.
        $inset = min(0.15, max(0.01, ($bottom - $top) * 0.02));

        for ($y = $top; $y <= $bottom + 0.001; $y += static::STEP) {
            $span = $this->spanAt($outline, min($bottom - $inset, max($top + $inset, $y)));

            if ($span === null) {
                continue;
            }

            // قطعهٔ نیمه از مرکز تا پهلو است؛ قطعهٔ کامل از پهلو تا پهلو
            $width = $half
                ? max(abs($span[1] - $centre), abs($centre - $span[0]))
                : ($span[1] - $span[0]) / 2.0;

            $samples[(string) round($y - $top, 2)] = round($width, 3);
        }

        /*
         * لبهٔ کمر و لبهٔ دمِ لباس روی کاغذ منحنی‌اند — باید باشند، وگرنه دوخته
         * که شدند کج می‌نشینند. ولی وقتی لباس تن شد، همان دو لبه روی یک تراز
         * می‌ایستند. اگر منحنی‌شان را همان‌طور نمونه بگیریم، بالای دامن نوک‌تیز
         * درمی‌آید و نما چیزی نشان می‌دهد که هیچ‌وقت دیده نمی‌شود. پس این دو
         * لبه را تراز می‌کنیم و پهنای هر تراز را از خودِ لبه می‌گیریم.
         */
        foreach (['waist', 'hem'] as $tag) {
            $band = $this->edgeBand($piece, $centre, $tag);

            if ($band === null) {
                continue;
            }

            [$from, $to, $reach] = $band;

            // چین و پیلی روی همین لبه، پارچه را جمع می‌کند: عرضِ بریده‌شده
            // بزرگ است ولی عرضِ دوخته‌شده به همان اندازه کم می‌شود. دامنِ
            // چین‌دارِ یک لباس بچگانه بدون این، دورِ باسنِ صدوشصت‌سانتی
            // گزارش می‌شد.
            $take = $this->fullnessOn($piece, $tag);
            $reach = max(0.0, $reach - ($half ? $take : $take / 2));

            foreach (array_keys($samples) as $at) {
                $y = (float) $at + $top;

                // این‌جا مقدار *جایگزین* می‌شود، نه بیشینه‌گیری: پهنای واقعیِ
                // لباس روی این تراز همان پهنای لبه است — چه از نمونهٔ خام
                // بزرگ‌تر باشد (لبهٔ منحنی) چه کوچک‌تر (لبهٔ چین‌خورده).
                if ($y >= $from - 0.01 && $y <= $to + 0.01) {
                    $samples[$at] = round($reach, 3);
                }
            }
        }

        /*
         * چینِ خط کمر ناگهان باز نمی‌شود؛ پارچه چند سانتی پایین‌تر کم‌کم وا
         * می‌رود. اگر فقط روی خودِ درز کمش کنیم، نمای لباس سرِ کمر یک پله
         * می‌خورد — چیزی که روی هیچ لباسی دیده نمی‌شود.
         */
        $take = $this->fullnessOn($piece, 'waist');
        $waistBand = $take > 0.01 ? $this->edgeBand($piece, $centre, 'waist') : null;

        if ($waistBand !== null) {
            $reduce = $half ? $take : $take / 2;
            $decay = max(2.0, min(10.0, ($bottom - $top) * 0.35));

            foreach ($samples as $at => $value) {
                $below = ((float) $at + $top) - $waistBand[1];

                if ($below > 0.0 && $below < $decay) {
                    $samples[$at] = round(max(0.0, $value - ($reduce * (1 - ($below / $decay)))), 3);
                }
            }
        }

        return [
            'samples' => $samples,
            'neck' => $this->neckOf($piece, $outline, $centre),
            'shoulder' => $this->shoulderOf($piece, $outline, $centre),
            'top' => 0.0,
            'bottom' => round($bottom - $top, 2),
        ];
    }

    /**
     * بازهٔ ارتفاعِ یک لبهٔ برچسب‌دار و بیشترین نیم‌پهنایش.
     *
     * @param  array<string, mixed>  $piece
     * @return array{0: float, 1: float, 2: float}|null
     */
    protected function edgeBand(array $piece, float $centre, string $tag): ?array
    {
        $indexes = Geometry::edgesWithTag($piece, $tag);

        if ($indexes === []) {
            return null;
        }

        $ys = [];
        $reach = 0.0;

        foreach ($indexes as $index) {
            foreach ([0.0, 0.2, 0.4, 0.6, 0.8, 1.0] as $t) {
                $point = Geometry::pointOnEdge($piece['outline'], $index, $t);
                $ys[] = (float) $point['y'];
                $reach = max($reach, abs((float) $point['x'] - $centre));
            }
        }

        return [min($ys), max($ys), $reach];
    }

    /**
     * چین و پیلیِ ثبت‌شده روی لبه‌های یک برچسب (سانتی‌متر).
     *
     * @param  array<string, mixed>  $piece
     */
    protected function fullnessOn(array $piece, string $tag): float
    {
        $indexes = Geometry::edgesWithTag($piece, $tag);

        if ($indexes === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach (['pleats', 'gathers'] as $kind) {
            foreach ($piece[$kind] ?? [] as $row) {
                $edge = $row['edge'] ?? null;

                // چینی که شمارهٔ لبه ندارد، مالِ خط کمر است: پارچه آن‌جا جمع
                // می‌شود تا به بالاتنه بخورد، نه روی دمِ آزادِ لباس
                $mine = $edge === null ? $tag === 'waist' : in_array((int) $edge, $indexes, true);

                if ($mine) {
                    $total += (float) ($row['intake'] ?? $row['amount'] ?? 0);
                }
            }
        }

        return max(0.0, $total);
    }

    /**
     * ساسون‌های قطعه را می‌بندد و اگر نشد، قطعه را دست‌نخورده برمی‌گرداند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function closeDarts(array $piece): array
    {
        $count = count($piece['darts'] ?? []);

        for ($i = $count - 1; $i >= 0; $i--) {
            try {
                $piece = DartTool::close($piece, $i);
            } catch (Throwable) {
                // ساسونی که بسته نمی‌شود، باز می‌ماند؛ نما کمی پهن‌تر می‌افتد
                // ولی کشیده می‌شود — بهتر از هیچ نما
            }
        }

        return $piece;
    }

    /**
     * برخوردِ یک خط افقی با مسیر قطعه: کم‌ترین و بیشترین x.
     *
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{0: float, 1: float}|null
     */
    protected function spanAt(array $outline, float $y): ?array
    {
        $xs = [];
        $n = count($outline);

        for ($i = 0; $i < $n; $i++) {
            $a = $outline[$i];
            $b = $outline[($i + 1) % $n];
            $ay = (float) $a['y'];
            $by = (float) $b['y'];

            if (($ay <= $y && $by >= $y) || ($by <= $y && $ay >= $y)) {
                if (abs($by - $ay) < 1e-9) {
                    $xs[] = (float) $a['x'];
                    $xs[] = (float) $b['x'];

                    continue;
                }

                $t = ($y - $ay) / ($by - $ay);
                $xs[] = (float) $a['x'] + $t * ((float) $b['x'] - (float) $a['x']);
            }
        }

        return $xs === [] ? null : [min($xs), max($xs)];
    }

    /**
     * خط مرکزی قطعه: از نشانهٔ مرکز جلو/پشت، وگرنه از لبهٔ تا، وگرنه وسط.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function centreX(array $piece, array $outline): float
    {
        foreach ($piece['markers'] ?? [] as $marker) {
            if (in_array((string) ($marker['key'] ?? ''), ['cf', 'cb'], true) && isset($marker['from']['x'])) {
                return (float) $marker['from']['x'];
            }
        }

        $folds = $piece['meta']['fold_edges'] ?? [];

        if ($folds !== [] && isset($outline[(int) $folds[0]])) {
            return (float) $outline[(int) $folds[0]]['x'];
        }

        [$minX, , $maxX] = Geometry::bounds($piece['outline']);

        return $this->isHalf($piece)
            ? (float) $minX
            : ((float) $minX + (float) $maxX) / 2.0;
    }

    /** آیا این قطعه نیمهٔ لباس است (از مرکز تا پهلو)؟ */
    protected function isHalf(array $piece): bool
    {
        return ! empty($piece['on_fold']) || max(1, (int) ($piece['cut_quantity'] ?? 1)) >= 2;
    }

    /**
     * پهنا و گودیِ خط یقه.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, array<string, mixed>>  $outline
     * @return array{width: float, depth: float}
     */
    protected function neckOf(array $piece, array $outline, float $centre): array
    {
        $indexes = Geometry::edgesWithTag($piece, 'neck');

        if ($indexes === []) {
            return ['width' => 0.0, 'depth' => 0.0];
        }

        $top = (float) Geometry::bounds($piece['outline'])[1];
        $xs = [];
        $ys = [];

        foreach ($indexes as $index) {
            foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $t) {
                $point = Geometry::pointOnEdge($piece['outline'], $index, $t);
                $xs[] = abs((float) $point['x'] - $centre);
                $ys[] = (float) $point['y'] - $top;
            }
        }

        return ['width' => round(max($xs), 2), 'depth' => round(max($ys), 2)];
    }

    /**
     * نیم‌پهنای سرشانه: دورترین نقطهٔ لبهٔ سرشانه از خط مرکز.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<int, array<string, mixed>>  $outline
     */
    protected function shoulderOf(array $piece, array $outline, float $centre): float
    {
        $indexes = Geometry::edgesWithTag($piece, 'shoulder');
        $best = 0.0;

        foreach ($indexes as $index) {
            foreach ([0.0, 0.5, 1.0] as $t) {
                $point = Geometry::pointOnEdge($piece['outline'], $index, $t);
                $best = max($best, abs((float) $point['x'] - $centre));
            }
        }

        return round($best, 2);
    }

    /** آیا پایین‌تنه دو پاچه دارد؟ */
    protected function hasLegs(array $lower): bool
    {
        foreach ($lower as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if (str_contains($part, '_leg') || str_contains($part, 'boxer')) {
                return true;
            }
        }

        return false;
    }

    /** بلندترین قطعه از یک دسته؛ همان که شکل کلی را می‌دهد. */
    protected function tallest(array $pieces): ?array
    {
        $best = null;
        $height = 0.0;

        foreach ($pieces as $piece) {
            $h = Geometry::height($piece['outline']);

            if ($h > $height) {
                $height = $h;
                $best = $piece;
            }
        }

        return $best;
    }

    /** ارتفاع یک خط نشانه (کمر، سینه، باسن) نسبت به بالای قطعه. */
    protected function levelY(array $piece, string $key): ?float
    {
        $top = (float) Geometry::bounds($piece['outline'])[1];
        $meta = $piece['meta'][$key.'_y'] ?? null;

        if (is_numeric($meta)) {
            return round((float) $meta - $top, 2);
        }

        foreach ($piece['markers'] ?? [] as $marker) {
            if ((string) ($marker['key'] ?? '') === $key && isset($marker['from']['y'])) {
                return round((float) $marker['from']['y'] - $top, 2);
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ *
     |  نمای پهلو: از پهنای تخت به ضخامت
     * ------------------------------------------------------------------ */

    /**
     * نیم‌رخِ پهلو: از دورِ لباس به ضخامتش.
     *
     * این‌جا باید صریح بود. الگو کاغذِ تخت است و تختی ضخامت ندارد؛ اگر لولهٔ
     * لباس را کاملاً بخوابانیم، ضخامتش صفر می‌شود. پس ضخامت را نمی‌شود از خودِ
     * الگو درآورد — از این‌که لباس دورِ *بدن* می‌ایستد درمی‌آید.
     *
     * مقطع را بیضی می‌گیریم: محیطش همان دورِ دوخته‌شدهٔ لباس است (که از الگو
     * می‌آید و دقیق است) و نسبت پهنا به ضخامتش از تنهٔ آدم (که تقریب است و
     * TORSO_RATIO نامش گذاشته شده). لباسی که خیلی گشادتر از بدن باشد آویزان
     * می‌ماند و مقطعش به دایره نزدیک می‌شود، پس نسبت را به یک می‌بریم.
     *
     * یعنی: بلندی و شکل و افتِ دامن در این نما از الگو می‌آید و درست است؛
     * ضخامت، مدل است نه اندازه‌گیری.
     *
     * @param  array<string, float|int>  $body
     */
    protected function sideProfile(array $front, array $back, array $body): array
    {
        $heights = $front['heights'];
        $halves = [];
        $span = max(0.1, $front['bottom'] - $front['top']);

        foreach ($heights as $i => $h) {
            $f = $front['halves'][$i] ?? 0.0;
            $b = $this->halfAt($back, $h);

            // دورِ دوخته‌شدهٔ لباس در این ارتفاع: پهنای تختِ جلو + پهنای تختِ پشت
            $girth = ($f + $b) * 2;
            $ratio = $this->crossRatio($girth, $this->bodyGirthAt($body, ($h - $front['top']) / $span));

            $halves[] = round($this->minorAxis($girth, $ratio), 3);
        }

        return [
            'heights' => $heights,
            'halves' => $halves,
            'neck' => ['width' => 0.0, 'depth' => max($front['neck']['depth'], $back['neck']['depth'])],
            'shoulder' => round($this->minorAxis(
                (max($front['shoulder'], 0.1) + max($back['shoulder'], 0.1)) * 2,
                static::TORSO_RATIO,
            ), 3),
            'top' => $front['top'],
            'bottom' => $front['bottom'],
            'legs' => $front['legs'] || $back['legs'],
            'levels' => $front['levels'],
            'has_torso' => $front['has_torso'],
        ];
    }

    /**
     * دورِ بدن در ارتفاعی از لباس، به کسری از بلندی لباس.
     *
     * لباسِ کامل از شانه تا پایین می‌رود: بالایش سینه است، وسطش کمر، و پایین‌تر
     * باسن. این تقسیم درشت است ولی برای پیدا کردنِ «لباس این‌جا چقدر از بدن
     * گشادتر است» بس است.
     *
     * @param  array<string, float|int>  $body
     */
    protected function bodyGirthAt(array $body, float $fraction): float
    {
        $bust = (float) ($body['bust'] ?? 92);
        $waist = (float) ($body['waist'] ?? 74);
        $hip = (float) ($body['hip'] ?? 98);

        return match (true) {
            $fraction <= 0.30 => $bust,
            $fraction <= 0.48 => $waist + (($bust - $waist) * ((0.48 - $fraction) / 0.18)),
            $fraction <= 0.70 => $waist + (($hip - $waist) * (($fraction - 0.48) / 0.22)),
            default => $hip,
        };
    }

    /**
     * نسبت پهنا به ضخامتِ مقطع.
     *
     * لباسی که به بدن می‌چسبد شکلِ تنه را می‌گیرد؛ لباسی که خیلی گشادتر است از
     * شانه آویزان می‌ماند و مقطعش گرد می‌شود.
     */
    protected function crossRatio(float $garmentGirth, float $bodyGirth): float
    {
        if ($garmentGirth <= 0.1 || $bodyGirth <= 0.1) {
            return static::TORSO_RATIO;
        }

        $closeness = min(1.0, $bodyGirth / $garmentGirth);

        return 1.0 + ((static::TORSO_RATIO - 1.0) * $closeness * $closeness);
    }

    /** نیم‌پهنای یک نیم‌رخ در ارتفاع دلخواه (نزدیک‌ترین نمونه). */
    protected function halfAt(array $profile, float $height): float
    {
        $best = 0.0;
        $gap = INF;

        foreach ($profile['heights'] as $i => $h) {
            $d = abs($h - $height);

            if ($d < $gap) {
                $gap = $d;
                $best = $profile['halves'][$i] ?? 0.0;
            }
        }

        return $best;
    }

    /**
     * نیم‌ضخامتِ بیضی‌ای با محیط و نسبتِ داده‌شده.
     *
     * محیط بیضی فرمول بسته ندارد؛ تقریب رامانوجان را می‌گیریم. با a = ratio×b
     * محیط خطیِ b می‌شود، پس یک تقسیم کافی است و حلقه لازم نیست.
     */
    protected function minorAxis(float $girth, float $ratio): float
    {
        if ($girth <= 0 || $ratio < 1.0) {
            return 0.0;
        }

        // محیط برای b = ۱، که بقیه‌اش خطی با آن بالا و پایین می‌رود
        $unit = M_PI * (3 * ($ratio + 1) - sqrt((3 * $ratio + 1) * ($ratio + 3)));

        return $unit <= 0 ? 0.0 : $girth / $unit;
    }

    /* ------------------------------------------------------------------ *
     |  آستین
     * ------------------------------------------------------------------ */

    /**
     * آستین: بلندی، پهنای بازو و پهنای دم‌آستین.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{length: float, bicep: float, cuff: float}|null
     */
    protected function sleeve(array $pieces): ?array
    {
        foreach ($pieces as $piece) {
            if ((string) ($piece['meta']['part'] ?? '') !== 'sleeve') {
                continue;
            }

            $outline = Geometry::flatten($piece['outline']);
            [, $minY, , $maxY] = Geometry::bounds($piece['outline']);
            $top = (float) $minY;
            $bottom = (float) $maxY;

            $bicep = 0.0;

            for ($y = $top; $y <= $bottom; $y += static::STEP) {
                $span = $this->spanAt($outline, $y);

                if ($span !== null) {
                    $bicep = max($bicep, $span[1] - $span[0]);
                }
            }

            $hem = $this->spanAt($outline, $bottom - 0.05);
            $cuff = $hem === null ? $bicep * 0.7 : $hem[1] - $hem[0];

            // آستینِ دوخته‌شده لوله است: پهنای دیده‌شده نصفِ پهنای تختِ قطعه
            return [
                'length' => round($bottom - $top, 2),
                'bicep' => round($bicep / 2, 2),
                'cuff' => round(max($cuff / 2, 1.5), 2),
            ];
        }

        return null;
    }

    /* ------------------------------------------------------------------ *
     |  کشیدن
     * ------------------------------------------------------------------ */

    /**
     * یک نما به SVG.
     *
     * @param  array{heights: array<int, float>, halves: array<int, float>, neck: array{width: float, depth: float}, shoulder: float, top: float, bottom: float, legs: bool}  $profile
     * @param  array{length: float, bicep: float, cuff: float}|null  $sleeve
     * @param  array<string, float|int>  $body
     */
    protected function draw(array $profile, ?array $sleeve, string $view, array $body, array $options = []): string
    {
        $width = (int) ($options['width'] ?? 240);
        $colour = (string) ($options['colour'] ?? '#c8b8a6');
        $line = (string) ($options['line'] ?? '#57534e');

        $span = max(0.1, $profile['bottom'] - $profile['top']);
        $widest = max(0.1, max($profile['halves']) * 2);
        $sleeveReach = $sleeve === null ? 0.0 : $sleeve['length'] * 0.95;

        // قاب به سانتی‌متر، با کمی حاشیه؛ آستین از پهلو بیرون می‌زند
        $halfFrame = ($widest / 2) + $sleeveReach + 3;
        $tallFrame = $span + 6;
        $scale = min($width / (2 * $halfFrame), ($width * 1.45) / $tallFrame);

        $x = fn (float $cm): string => (string) round(($halfFrame + $cm) * $scale, 2);
        $y = fn (float $cm): string => (string) round(($cm + 3) * $scale, 2);

        $w = (int) ceil(2 * $halfFrame * $scale);
        $h = (int) ceil($tallFrame * $scale);

        $bodyPath = $this->shellPath($profile, $x, $y);
        $sleeves = $sleeve === null ? '' : $this->sleevePath($profile, $sleeve, $view, $x, $y);
        $detail = $this->detailPath($profile, $view, $x, $y);

        $mirror = $view === 'left' ? ' transform="scale(-1,1) translate(-'.$w.',0)"' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'"'
            .' role="img" aria-label="نمای '.htmlspecialchars(static::VIEWS[$view] ?? $view, ENT_QUOTES).' لباس">'
            .'<g'.$mirror.' fill="'.$colour.'" stroke="'.$line.'" stroke-width="1.1" stroke-linejoin="round">'
            .$sleeves
            .'<path d="'.$bodyPath.'"/>'
            .$detail
            .'</g></svg>';
    }

    /**
     * خطِ دورِ تنهٔ لباس: راست از بالا به پایین، چپ برعکس، و یقه در بالا.
     */
    protected function shellPath(array $profile, callable $x, callable $y): string
    {
        $right = [];
        $left = [];

        foreach ($profile['heights'] as $i => $height) {
            $half = $profile['halves'][$i] ?? 0.0;
            $at = $height - $profile['top'];

            $right[] = $x($half).' '.$y($at);
            $left[] = $x(-$half).' '.$y($at);
        }

        if ($right === []) {
            return '';
        }

        $neck = $profile['neck'];
        $shoulder = max($profile['shoulder'], $profile['halves'][0] ?? 0.0);

        // بالای لباس: از سرشانهٔ راست، دور خط یقه، تا سرشانهٔ چپ
        $top = $neck['width'] > 0.05
            ? 'M '.$x($shoulder).' '.$y(0)
                .' L '.$x($neck['width']).' '.$y(0)
                .' Q '.$x(0).' '.$y($neck['depth'] * 1.25).' '.$x(-$neck['width']).' '.$y(0)
                .' L '.$x(-$shoulder).' '.$y(0)
            : 'M '.$x($shoulder).' '.$y(0).' L '.$x(-$shoulder).' '.$y(0);

        /*
         * ترتیب مهم است: از سرشانهٔ راست، روی خط یقه تا سرشانهٔ چپ، بعد *پایین*
         * آمدن از پهلوی چپ، عبور از دم لباس، و بالا رفتن از پهلوی راست. اگر یکی
         * از این دو ستون برعکس بیاید، مسیر خودش را قطع می‌کند و لباس به شکل
         * پاپیون درمی‌آید.
         */
        return $top
            .' L '.implode(' L ', $left)
            .' L '.implode(' L ', array_reverse($right))
            .' Z';
    }

    /**
     * دو آستین، از سرشانه تا دم‌آستین و برگشت به زیر بغل.
     *
     * چهار نقطه دارد و هر چهار از الگو می‌آید: نوکِ سرشانه (پهنای سرشانه)،
     * زیرِ بغل (پهنای لباس روی خط سینه، در ارتفاع همان خط)، و دو سرِ دم‌آستین
     * که به فاصلهٔ قدِ آستین از سرشانه، با پهنای دم‌آستین.
     */
    protected function sleevePath(array $profile, array $sleeve, string $view, callable $x, callable $y): string
    {
        $angle = deg2rad(static::SLEEVE_ANGLE);
        $shoulder = max($profile['shoulder'], $profile['halves'][0] ?? 0.0);
        $span = max(0.1, $profile['bottom'] - $profile['top']);

        // زیرِ بغل: روی خط سینه اگر الگو آن را دارد، وگرنه یک‌پنجمِ بالای لباس
        $armY = min($span * 0.9, (float) ($profile['levels']['bust'] ?? ($span * 0.2)));
        $armX = $this->halfAt($profile, $profile['top'] + $armY);

        // از پهلو، آستین رو به بیننده می‌آید و کوتاه‌تر دیده می‌شود
        $reach = $sleeve['length'] * ($view === 'front' || $view === 'back' ? 1.0 : 0.45);
        $cuff = max(1.0, $sleeve['cuff'] * ($view === 'front' || $view === 'back' ? 1.0 : 0.7));

        // نوک آستین روی امتداد شانه، و دم‌آستین عمود بر همان امتداد
        $tipX = $shoulder + ($reach * sin($angle));
        $tipY = 0.6 + ($reach * cos($angle));
        $outX = $tipX + ($cuff * cos($angle));
        $outY = $tipY - ($cuff * sin($angle));

        $out = '';

        foreach ([1, -1] as $sign) {
            $sx = fn (float $cm): string => $x($sign * $cm);

            $out .= '<path d="'
                .'M '.$sx($shoulder).' '.$y(0.6)
                .' L '.$sx($outX).' '.$y($outY)
                .' L '.$sx($tipX).' '.$y($tipY)
                .' L '.$sx($armX).' '.$y($armY)
                .' Z"/>';
        }

        return $out;
    }

    /** نشانه‌های هر نما: خط یقهٔ پشت، درزِ پهلو، فاق. */
    protected function detailPath(array $profile, string $view, callable $x, callable $y): string
    {
        $out = '';
        $bottom = $profile['bottom'] - $profile['top'];

        if ($view === 'right' || $view === 'left') {
            // در نمای پهلو، درزِ پهلو خودِ لبه است؛ چیزی روی لباس کشیده نمی‌شود
            return '';
        }

        if ($profile['legs']) {
            // فاق: دو پاچه از جایی که باسن تمام می‌شود جدا می‌شوند
            $crotch = $bottom * 0.45;
            $out .= '<path d="M '.$x(0).' '.$y($crotch).' L '.$x(0).' '.$y($bottom)
                .'" fill="none" stroke-width="1"/>';
        }

        if ($view === 'back') {
            // درزِ مرکزِ پشت، همان‌جا که زیپ می‌خورد
            $out .= '<path d="M '.$x(0).' '.$y(0).' L '.$x(0).' '.$y($bottom * 0.6)
                .'" fill="none" stroke-width="0.8" stroke-dasharray="3 2"/>';
        }

        return $out;
    }

    /* ------------------------------------------------------------------ *
     |  اندازه‌های لباسِ دوخته‌شده
     * ------------------------------------------------------------------ */

    /**
     * چند عددی که خیاط و مشتری می‌خواهند بدانند، به سانتی‌متر.
     *
     * هر اندازه از خطِ واقعیِ خودش روی الگو خوانده می‌شود، نه از کسری حدسی از
     * بلندی لباس. اگر لباس آن خط را نداشته باشد، عددش هم نمی‌آید: «دور سینهٔ
     * دامن» عددی است که اندازه‌گیری نشده و نباید نوشته شود.
     *
     * @return array<string, float>
     */
    protected function measures(array $front, array $back, array $side, ?array $sleeve): array
    {
        $span = $front['bottom'] - $front['top'];
        $levels = $front['levels'] ?? [];

        $girth = fn (float $at): float => round(
            ($this->halfAt($front, $front['top'] + $at) + $this->halfAt($back, $front['top'] + $at)) * 2,
            1,
        );

        $out = ['قد لباس' => round($span, 1)];

        foreach (['bust' => 'دور سینهٔ دوخته‌شده', 'waist' => 'دور کمرِ دوخته‌شده', 'hip' => 'دور باسنِ دوخته‌شده'] as $key => $label) {
            if (isset($levels[$key]) && $levels[$key] <= $span + 0.5) {
                $out[$label] = $girth((float) $levels[$key]);
            }
        }

        $out['پهنای پایینِ لباس'] = round(max((float) ($front['halves'][count($front['halves']) - 1] ?? 0), 0.0) * 2, 1);

        if (($front['has_torso'] ?? false) && max($front['shoulder'], $back['shoulder']) > 1.0) {
            $out['پهنای سرشانه'] = round(max($front['shoulder'], $back['shoulder']) * 2, 1);
        }

        // ضخامت را همان‌جایی می‌دهیم که لباس پهن‌ترین جایش را دارد
        $widest = $front['top'];
        $best = 0.0;

        foreach ($front['heights'] as $i => $h) {
            if (($front['halves'][$i] ?? 0.0) > $best) {
                $best = $front['halves'][$i];
                $widest = $h;
            }
        }

        $out['ضخامتِ لباس از پهلو'] = round($this->halfAt($side, $widest) * 2, 1);

        if ($sleeve !== null) {
            $out['قد آستین'] = $sleeve['length'];
            $out['دور بازوی آستین'] = round($sleeve['bicep'] * 2, 1);
            $out['دور دم‌آستین'] = round($sleeve['cuff'] * 2, 1);
        }

        return array_map(fn (float $v): float => max(0.0, $v), $out);
    }

    /**
     * همان چهار نما، مستقیم از یک الگوی ذخیره‌شده.
     *
     * @param  Collection<int, PatternPiece>|iterable<int, PatternPiece>  $pieces
     * @param  array<string, float|int>  $body
     * @return array{views: array<string, string>, measures: array<string, float>, notes: array<int, string>, ok: bool}
     */
    public function forPieces(iterable $pieces, array $body, array $options = []): array
    {
        return $this->flats($pieces, $body, $options);
    }
}
