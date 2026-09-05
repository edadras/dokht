<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Transform\PieceOps;
use App\Support\Format;

/**
 * بازرسی یک الگو — هر الگویی، نه فقط الگوهای کاتالوگ.
 *
 * الگوی ساخته‌شده در استودیوی طراحی، الگوی درآمده از عکس، الگویی که کاربر در
 * ویرایشگر با دست جابه‌جا کرده و الگوی خریداری‌شده از بازارچه، همه به یک اندازه
 * باید «بریدنی و دوختنی» باشند. این کلاس همان بررسی‌هایی را که تا امروز فقط در
 * آزمون کاتالوگ اجرا می‌شد، روی هر الگوی زنده اجرا می‌کند:
 *
 *   الف) درستی هندسی — مسیر بسته و بدون خودقطعی، مساحت معقول، رأس‌های تکراری،
 *        برچسب لبه‌ها، راستای پارچه داخل قطعه، جای نشانه و ساسون روی لبه‌ای که
 *        ادعا می‌کند.
 *
 *   ب) درستی خیاطی — دو لبه‌ای که به هم دوخته می‌شوند هم‌اندازه باشند، درز پهلوی
 *      جلو و پشت روی هم پیاده شوند، و اندازه تمام‌شده لباس در محدوده معقولی از
 *      اندازه بدنِ خودِ همین الگو بماند.
 *
 * خروجی فهرستی از یافته‌هاست، هرکدام با یک سطح:
 *   error   → این‌طور بریده نمی‌شود
 *   warning → بریده می‌شود ولی چیزی سر جایش نیست
 *   info    → نکته‌ای که بهتر است اضافه شود
 */
class PatternInspector
{
    /** برچسب‌های مجاز لبه. */
    public const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'crotch', 'default'];

    /** «روی لبه بودن» یک نقطه: سه میلی‌متر، همان پهنای خط مداد. */
    protected const ON_EDGE = 0.3;

    /** رواداری هم‌اندازه بودن دو درزی که به هم دوخته می‌شوند. */
    protected const SEAM_MATCH = 0.5;

    /** کمترین و بیشترین مساحت پذیرفتنی برای هر قطعه (سانتی‌متر مربع). */
    protected const MIN_AREA = 4.0;

    protected const MAX_AREA = 40000.0;

    /** بازه آزادی پذیرفتنی نسبت به اندازه بدن (سانتی‌متر). */
    protected const GIRTH_BAND = [
        'bust' => [-12.0, 60.0],
        'waist' => [-8.0, 90.0],
        'hip' => [-12.0, 60.0],
    ];

    /**
     * بازرسی کامل یک الگو.
     *
     * @return array{score: int, errors: int, warnings: int, findings: array<int, array{level: string, key: string, piece: string|null, message: string}>}
     */
    public function inspect(Pattern $pattern): array
    {
        $pieces = $pattern->relationLoaded('pieces') ? $pattern->pieces : $pattern->pieces()->get();
        $findings = [];

        if ($pieces->isEmpty()) {
            return $this->result([[
                'level' => 'error',
                'key' => 'empty',
                'piece' => null,
                'message' => 'این الگو هیچ قطعه‌ای ندارد.',
            ]]);
        }

        foreach ($pieces as $piece) {
            $findings = array_merge($findings, $this->inspectPiece($piece));
        }

        $findings = array_merge(
            $findings,
            $this->inspectSewingRelations($pattern, $pieces),
            $this->inspectSideSeams($pieces),
            $this->inspectGirths($pattern, $pieces),
        );

        return $this->result($findings);
    }

    /**
     * فقط سطح خطاها؛ برای جاهایی که تصمیم می‌گیرند نه گزارش.
     */
    public function hasErrors(Pattern $pattern): bool
    {
        return $this->inspect($pattern)['errors'] > 0;
    }

    /* ---------------------------------------------------------------------
     |  الف) هندسه هر قطعه
     * ------------------------------------------------------------------- */

    /** @return array<int, array<string, mixed>> */
    protected function inspectPiece(PatternPiece $piece): array
    {
        $found = [];
        $code = (string) $piece->code;
        $name = (string) ($piece->name ?: $piece->code);
        $outline = $piece->outline ?? [];

        if (count($outline) < 3) {
            return [$this->finding('error', 'outline', $code, "قطعه «{$name}» کمتر از سه نقطه دارد و مسیر بسته‌ای نمی‌سازد.")];
        }

        foreach ($outline as $index => $point) {
            if (! is_finite((float) ($point['x'] ?? NAN)) || ! is_finite((float) ($point['y'] ?? NAN))) {
                return [$this->finding('error', 'number', $code, "نقطه شماره {$index} از «{$name}» عدد معتبری ندارد.")];
            }
        }

        $count = count($outline);

        for ($i = 0; $i < $count; $i++) {
            $next = ($i + 1) % $count;
            $gap = Geometry::distance(
                ['x' => (float) $outline[$i]['x'], 'y' => (float) $outline[$i]['y']],
                ['x' => (float) $outline[$next]['x'], 'y' => (float) $outline[$next]['y']],
            );

            if ($gap < 0.02) {
                $found[] = $this->finding('warning', 'duplicate_point', $code,
                    "در «{$name}» نقطه {$i} و نقطه بعدی روی هم افتاده‌اند؛ لبه‌ای با طول صفر می‌ماند.");
                break;
            }
        }

        if (Geometry::selfIntersects($outline)) {
            $found[] = $this->finding('error', 'self_intersect', $code,
                "مسیر «{$name}» خودش را قطع می‌کند؛ این قطعه روی کاغذ چاپ می‌شود ولی بریده نمی‌شود.");
        }

        $area = Geometry::area($outline);

        if ($area < static::MIN_AREA) {
            $found[] = $this->finding('error', 'area', $code,
                sprintf('مساحت «%s» تنها %s سانتی‌متر مربع است.', $name, Format::number($area, 1)));
        } elseif ($area > static::MAX_AREA) {
            $found[] = $this->finding('warning', 'area', $code,
                sprintf('مساحت «%s» %s سانتی‌متر مربع است و باورکردنی نیست.', $name, Format::number($area, 1)));
        }

        $tags = $piece->meta['edges'] ?? null;

        if (is_array($tags)) {
            if (count($tags) !== $count) {
                $found[] = $this->finding('warning', 'edge_tags', $code,
                    sprintf('«%s» %s برچسب لبه دارد ولی %s لبه.', $name, Format::number(count($tags)), Format::number($count)));
            }

            foreach ($tags as $tag) {
                if (! in_array($tag, static::EDGE_TAGS, true)) {
                    $found[] = $this->finding('warning', 'edge_tags', $code,
                        "برچسب لبه «{$tag}» در «{$name}» شناخته‌شده نیست.");
                    break;
                }
            }
        } else {
            $found[] = $this->finding('info', 'edge_tags', $code,
                "«{$name}» برچسب لبه ندارد؛ بدون آن جای دوخت و پیشنهاد دوخت دقیق درنمی‌آید.");
        }

        [$minX, $minY, $maxX, $maxY] = Geometry::bounds($outline);

        if (empty($piece->grainline)) {
            $found[] = $this->finding('info', 'grainline', $code,
                "«{$name}» راستای پارچه ندارد؛ در چیدمان برش نمی‌شود جهت آن را نگه داشت.");
        } else {
            foreach (['from', 'to'] as $end) {
                $at = $piece->grainline[$end] ?? null;

                if (! is_array($at)) {
                    continue;
                }

                if ($at['x'] < $minX - 0.5 || $at['x'] > $maxX + 0.5 || $at['y'] < $minY - 0.5 || $at['y'] > $maxY + 0.5) {
                    $found[] = $this->finding('warning', 'grainline', $code,
                        "راستای پارچه «{$name}» بیرون خود قطعه افتاده است.");
                    break;
                }
            }
        }

        $quantity = $piece->cut_quantity;

        if (! is_int($quantity) || $quantity < 1) {
            $found[] = $this->finding('warning', 'cut_quantity', $code,
                "تعداد برش «{$name}» درست نیست.");
        }

        $folds = $piece->meta['fold_edges'] ?? [];

        if ($piece->on_fold && $folds === []) {
            $found[] = $this->finding('warning', 'fold', $code,
                "«{$name}» روی تای پارچه بریده می‌شود ولی هیچ لبه‌ای را تا معرفی نکرده است.");
        }

        if (! $piece->on_fold && $folds !== []) {
            $found[] = $this->finding('warning', 'fold', $code,
                "«{$name}» لبه تا دارد ولی روی تای پارچه بریده نمی‌شود.");
        }

        foreach ($this->anchors($piece) as [$kind, $label, $at, $edge]) {
            if ($edge === null) {
                continue;
            }

            if ($edge < 0 || $edge >= $count) {
                $found[] = $this->finding('warning', $kind, $code,
                    "{$label} در «{$name}» لبه‌ای را ادعا می‌کند که وجود ندارد.");

                continue;
            }

            $t = Geometry::edgeParameterOf($outline, $edge, $at, 48);
            $distance = Geometry::distance(Geometry::pointOnEdge($outline, $edge, $t), $at);

            if ($distance > static::ON_EDGE) {
                $found[] = $this->finding('warning', $kind, $code, sprintf(
                    '%s در «%s» %s سانتی‌متر از لبه‌ای که ادعا می‌کند فاصله دارد.',
                    $label, $name, Format::number($distance, 1),
                ));
            }
        }

        return $found;
    }

    /**
     * نشانه‌ها و ساسون‌هایی که یک لبه را ادعا می‌کنند.
     *
     * @return array<int, array{0: string, 1: string, 2: array{x: float, y: float}, 3: int|null}>
     */
    protected function anchors(PatternPiece $piece): array
    {
        $out = [];

        foreach ($piece->notches ?? [] as $notch) {
            if (! isset($notch['x'], $notch['y'])) {
                continue;
            }

            $out[] = [
                'notch',
                'نشانه «'.($notch['label'] ?? 'بی‌نام').'»',
                ['x' => (float) $notch['x'], 'y' => (float) $notch['y']],
                isset($notch['edge']) ? (int) $notch['edge'] : null,
            ];
        }

        foreach ($piece->darts ?? [] as $dart) {
            if (! isset($dart['x'], $dart['y'], $dart['edge']) || $dart['edge'] === null) {
                continue;
            }

            $out[] = [
                'dart',
                'ساسون «'.($dart['label'] ?? 'بی‌نام').'»',
                ['x' => (float) $dart['x'], 'y' => (float) $dart['y']],
                (int) $dart['edge'],
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------------------
     |  ب) خیاطی
     * ------------------------------------------------------------------- */

    /**
     * هر جفت لبه‌ای که در «دوخت مجازی» به هم وصل شده باید واقعاً وجود داشته باشد.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function inspectSewingRelations(Pattern $pattern, $pieces): array
    {
        $found = [];
        $byCode = $pieces->keyBy('code');

        foreach ($pattern->sewing_relations ?? [] as $relation) {
            foreach (['from', 'to'] as $end) {
                $code = $relation[$end]['piece'] ?? null;
                $edge = $relation[$end]['edge'] ?? null;
                $piece = $code === null ? null : $byCode->get($code);

                if ($piece === null) {
                    $found[] = $this->finding('warning', 'sewing', null,
                        "در فهرست دوخت، قطعه «{$code}» آمده ولی در این الگو نیست.");

                    continue 2;
                }

                if (! is_int($edge) || $edge < 0 || $edge >= count($piece->outline ?? [])) {
                    $found[] = $this->finding('warning', 'sewing', (string) $code,
                        "در فهرست دوخت، لبه‌ای از «{$piece->name}» خواسته شده که وجود ندارد.");

                    continue 2;
                }
            }
        }

        return $found;
    }

    /**
     * درز پهلوی جلو و پشت باید روی هم پیاده شوند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function inspectSideSeams($pieces): array
    {
        foreach ([['front_bodice', 'back_bodice'], ['skirt_front', 'skirt_back']] as [$frontPart, $backPart]) {
            $fronts = $pieces->filter(fn (PatternPiece $p) => ($p->meta['part'] ?? null) === $frontPart)->values();
            $backs = $pieces->filter(fn (PatternPiece $p) => ($p->meta['part'] ?? null) === $backPart)->values();

            if ($fronts->count() !== 1 || $backs->count() !== 1) {
                continue;
            }

            $front = $this->toArray($fronts[0]);
            $back = $this->toArray($backs[0]);

            if (Geometry::edgesWithTag($front, 'side') === [] || Geometry::edgesWithTag($back, 'side') === []) {
                continue;
            }

            $walk = PieceOps::walk($front, 'side', $back, 'side', ['tolerance' => static::SEAM_MATCH]);
            $longer = max($walk['a']['seam'], $walk['b']['seam']);

            // اختلاف بزرگ یعنی این دو لبه اصلاً یک جفت دوخت نیستند: دامن پاکتی و
            // گلبرگ لاله هم لبه رویهم‌آمدن دارند و آن هم برچسب side می‌گیرد. آنجا
            // سکوت می‌کنیم، چون درباره ساختی حرف می‌زنیم که نمی‌شناسیم. درزی که
            // واقعاً جفت است، اختلافش همیشه کوچک است.
            if ($longer > 0 && abs($walk['difference']) > $longer * 0.25) {
                continue;
            }

            if (! $walk['matched']) {
                return [$this->finding('warning', 'side_seam', (string) $fronts[0]->code, sprintf(
                    'درز پهلوی «%s» و «%s» هم‌اندازه نیستند؛ اختلاف %s سانتی‌متر است.',
                    $fronts[0]->name, $backs[0]->name, Format::number(abs($walk['difference']), 2),
                ))];
            }
        }

        return [];
    }

    /**
     * اندازه تمام‌شده لباس در برابر اندازه بدنی که خودِ الگو ثبت کرده.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function inspectGirths(Pattern $pattern, $pieces): array
    {
        $body = $pattern->measurements ?? [];

        if ($body === []) {
            return [];
        }

        $found = [];
        $drop = [
            'bust' => 0.0,
            'waist' => max(0.0, (float) ($body['bust'] ?? 0) - (float) ($body['waist'] ?? 0)),
            'hip' => max(0.0, (float) ($body['bust'] ?? 0) - (float) ($body['hip'] ?? 0)),
        ];

        foreach ($this->markerGirths($pieces) as $area => $girth) {
            if (! isset($body[$area]) || ! isset(static::GIRTH_BAND[$area])) {
                continue;
            }

            $ease = $girth - (float) $body[$area];
            [$low, $high] = static::GIRTH_BAND[$area];
            $high += $drop[$area];

            if ($ease < $low || $ease > $high) {
                $found[] = $this->finding('warning', 'girth', null, sprintf(
                    'اندازه تمام‌شده %s برابر %s سانتی‌متر است در برابر بدنِ %s؛ آزادی %s سانتی‌متر معقول نیست.',
                    $this->areaLabel($area),
                    Format::number($girth, 1),
                    Format::number((float) $body[$area], 1),
                    Format::number($ease, 1),
                ));
            }
        }

        return $found;
    }

    /**
     * دور تمام‌شده در هر تراز، از روی خط‌های نشانه‌ای که خود قطعه‌ها ثبت کرده‌اند.
     *
     * @return array<string, float>
     */
    protected function markerGirths($pieces): array
    {
        $totals = [];

        foreach ($pieces as $piece) {
            if (($piece->layer ?? 'outer') !== 'outer') {
                continue;
            }

            $factor = $piece->meta['girth_factor'] ?? null;

            if ($factor === null || (float) $factor <= 0) {
                continue;
            }

            foreach ($piece->meta['girth'] ?? [] as $line => $value) {
                if (! is_numeric($value)) {
                    continue;
                }

                $totals[$line] = ($totals[$line] ?? 0.0) + ((float) $value * (float) $factor);
            }
        }

        return array_map(fn (float $value) => round($value, 1), $totals);
    }

    protected function areaLabel(string $area): string
    {
        return [
            'bust' => 'دور سینه',
            'waist' => 'دور کمر',
            'hip' => 'دور باسن',
        ][$area] ?? $area;
    }

    /* ---------------------------------------------------------------------
     |  کمک‌کارها
     * ------------------------------------------------------------------- */

    /** @return array<string, mixed> */
    protected function toArray(PatternPiece $piece): array
    {
        return [
            'code' => $piece->code,
            'name' => $piece->name,
            'outline' => $piece->outline ?? [],
            'darts' => $piece->darts ?? [],
            'notches' => $piece->notches ?? [],
            'pleats' => $piece->pleats ?? [],
            'meta' => $piece->meta ?? [],
        ];
    }

    /** @return array<string, mixed> */
    protected function finding(string $level, string $key, ?string $piece, string $message): array
    {
        return ['level' => $level, 'key' => $key, 'piece' => $piece, 'message' => $message];
    }

    /**
     * جمع‌بندی: امتیاز صد از صد، با کسر بابت هر ایراد.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<string, mixed>
     */
    protected function result(array $findings): array
    {
        $errors = count(array_filter($findings, fn (array $f) => $f['level'] === 'error'));
        $warnings = count(array_filter($findings, fn (array $f) => $f['level'] === 'warning'));
        $infos = count(array_filter($findings, fn (array $f) => $f['level'] === 'info'));

        $score = 100 - ($errors * 25) - ($warnings * 8) - ($infos * 2);

        // ترتیب نمایش: اول آنچه جلوی برش را می‌گیرد
        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($findings, fn (array $a, array $b) => [$order[$a['level']], $a['key']] <=> [$order[$b['level']], $b['key']]);

        return [
            'score' => (int) max(0, min(100, $score)),
            'errors' => $errors,
            'warnings' => $warnings,
            'findings' => array_values($findings),
        ];
    }
}
