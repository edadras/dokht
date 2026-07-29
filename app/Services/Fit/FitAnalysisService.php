<?php

namespace App\Services\Fit;

use App\Models\Fabric;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\Project;
use App\Models\Simulation;
use App\Services\Pattern\PatternInspector;
use App\Support\FabricProfile;
use App\Support\Jalali;
use App\Support\Measurements;

/**
 * تحلیل تناسب لباس با بدن.
 *
 * کار این سرویس ساده است: اندازه‌ی خودِ لباس را از الگو درمی‌آورد، با اندازه‌ی بدن
 * مقایسه می‌کند و اختلاف (آزادی) را به یک از چهار وضعیت «تنگ، چسبان، مناسب، گشاد»
 * تبدیل می‌کند. نکته‌ی مهم این است که تنگی منفی فقط به اندازه‌ای پذیرفته می‌شود که
 * پارچه کشسانی داشته باشد؛ یک پارچه‌ی جرسی با ۴۰٪ کشسانی می‌تواند از بدن کوچک‌تر
 * بریده شود ولی یک پارچه‌ی بی‌کشش هرگز.
 */
class FitAnalysisService
{
    public function __construct(protected PatternInspector $inspector = new PatternInspector) {}

    /**
     * نواحی سنجش تناسب.
     *
     * body: کلید اندازه‌ی بدن — kind: girth دور، width عرض، length قد
     * min: کمینه‌ی آزادی لازم برای راحتی — ideal: آزادی متعارف — loose: مرز گشادی
     * weight: وزن ناحیه در امتیاز نهایی
     * paired: ناحیه‌ای که روی یک اندام است (آستین)، پس یک قطعه یک دور کامل می‌سازد
     * pieces: آیا پهنای افقی قطعه در این ناحیه واقعاً همان دور لباس است
     */
    public const ZONES = [
        'bust' => ['label' => 'دور سینه', 'body' => 'bust', 'kind' => 'girth', 'min' => 2.0, 'ideal' => 6.0, 'loose' => 14.0, 'weight' => 20],
        'waist' => ['label' => 'دور کمر', 'body' => 'waist', 'kind' => 'girth', 'min' => 1.5, 'ideal' => 4.0, 'loose' => 12.0, 'weight' => 15],
        'hip' => ['label' => 'دور باسن', 'body' => 'hip', 'kind' => 'girth', 'min' => 2.0, 'ideal' => 6.0, 'loose' => 14.0, 'weight' => 18],
        'shoulder' => ['label' => 'عرض سرشانه', 'body' => 'shoulder_width', 'kind' => 'width', 'min' => -0.5, 'ideal' => 0.5, 'loose' => 3.0, 'weight' => 12],
        'armhole' => ['label' => 'حلقه آستین', 'body' => 'armhole', 'kind' => 'girth', 'min' => 2.0, 'ideal' => 4.0, 'loose' => 10.0, 'weight' => 12, 'pieces' => false],
        'bicep' => ['label' => 'دور بازو', 'body' => 'bicep', 'kind' => 'girth', 'min' => 2.5, 'ideal' => 5.0, 'loose' => 12.0, 'weight' => 8, 'paired' => true],
        'back' => ['label' => 'قد بالاتنه پشت', 'body' => 'back_length', 'kind' => 'length', 'min' => 0.0, 'ideal' => 1.5, 'loose' => 5.0, 'weight' => 8],
        'hem' => ['label' => 'دور پایین لباس', 'body' => 'hip', 'kind' => 'girth', 'min' => 2.0, 'ideal' => 8.0, 'loose' => 30.0, 'weight' => 7],
    ];

    /**
     * ضریب نیاز هر ناحیه در حالت‌های مختلف بدن.
     *
     * بدن در حرکت بزرگ‌تر می‌شود: با بالا بردن دست، حلقه‌ی آستین و بازو جای بیشتری
     * می‌خواهند؛ در نشستن باسن و پایین لباس؛ در خم شدن قد پشت. عددها ضریبی هستند که
     * روی «کمینه‌ی آزادی لازم» و «آزادی متعارف» همان ناحیه ضرب می‌شوند، پس مثلاً
     * ضریب ۱٫۸ برای حلقه‌ی آستین یعنی در حالت بالا بردن دست ۸۰٪ جای بیشتری لازم است.
     * ناحیه‌هایی که در جدول نیستند ضریب ۱ می‌گیرند.
     */
    public const POSE_FACTORS = [
        'stand' => [],
        'walk' => ['hip' => 1.2, 'hem' => 1.4, 'waist' => 1.1],
        'arm_raise' => ['armhole' => 1.8, 'bicep' => 1.5, 'back' => 1.4, 'shoulder' => 1.2, 'bust' => 1.15],
        'bend' => ['back' => 1.8, 'waist' => 1.3, 'hip' => 1.25, 'bust' => 1.1],
        'sit' => ['hip' => 1.5, 'hem' => 1.5, 'waist' => 1.35],
        'turn' => ['bust' => 1.05, 'armhole' => 1.1],
    ];

    /** واژه‌های شناسایی نقش هر قطعه از روی کد یا نام آن. */
    protected const ROLE_KEYWORDS = [
        'sleeve' => ['sleeve', 'آستین'],
        'skirt' => ['skirt', 'دامن'],
        'leg' => ['leg', 'pant', 'trouser', 'شلوار', 'پاچه'],
        'detail' => ['collar', 'cuff', 'pocket', 'facing', 'waistband', 'belt', 'placket', 'yoke', 'hood', 'lapel',
            'یقه', 'مچ', 'جیب', 'سجاف', 'کمربند', 'پاتلت', 'کلاه'],
    ];

    /** کلیدهای هم‌معنی نشانه‌های الگو برای هر ناحیه. */
    protected const MARKER_ALIASES = [
        'bust' => ['bust', 'bust_line', 'bustline', 'chest', 'chest_line', 'خط سینه'],
        'waist' => ['waist', 'waist_line', 'waistline', 'خط کمر'],
        'hip' => ['hip', 'hip_line', 'hipline', 'خط باسن'],
        'hem' => ['hem', 'hem_line', 'hemline', 'bottom', 'خط پایین'],
        'shoulder' => ['shoulder', 'shoulder_line', 'خط سرشانه'],
        'armhole' => ['armhole', 'armhole_line', 'حلقه آستین'],
        'bicep' => ['bicep', 'bicep_line', 'خط بازو'],
        'back' => ['back_length', 'back_line', 'قد پشت'],
    ];

    /**
     * تحلیل کامل تناسب.
     *
     * @param  array<string, float|int|string>  $measurements  اندازه‌های بدن
     * @return array{zones: array<int, array>, warnings: array<int, string>, suggestions: array<int, array>, fit_score: int, params: array}
     */
    public function analyze(Pattern $pattern, ?Fabric $fabric, array $measurements, string $pose = 'stand'): array
    {
        $body = Measurements::complete($measurements);
        $profile = $fabric?->profile() ?? FabricProfile::make();
        $physics = $profile->physics();
        $pose = isset(Simulation::POSES[$pose]) ? $pose : 'stand';
        $factors = static::POSE_FACTORS[$pose] ?? [];

        $pieces = $this->pieces($pattern);

        $zones = [];
        $warnings = [];
        $suggestions = [];

        foreach (static::ZONES as $key => $def) {
            $factor = (float) ($factors[$key] ?? 1.0);
            $bodyCm = round((float) ($body[$def['body']] ?? 0), 1);
            $garment = $this->garmentValue($pattern, $pieces, $key, $def, $body);
            $garmentCm = round($garment['cm'], 1);
            $ease = round($garmentCm - $bodyCm, 1);

            // نیاز این ناحیه با توجه به حالت بدن
            $required = $def['min'] >= 0 ? $def['min'] * $factor : $def['min'] * (2 - $factor);
            $ideal = $def['ideal'] * $factor;
            $looseAt = $def['loose'] * $factor;

            // چقدر از تنگی را کشسانی پارچه جبران می‌کند
            $tolerance = $this->stretchTolerance($physics, $def['kind'], $bodyCm);
            $credit = max(0.0, min($tolerance, $required - $ease));
            $effective = $ease + $credit;

            $level = match (true) {
                $effective < 0 => 'tight',
                $effective < $required => 'snug',
                $effective <= $looseAt => 'good',
                default => 'loose',
            };

            $span = max(1.0, $looseAt - $required);
            $normal = ($effective - $required) / $span;
            $pressure = round(max(0.0, min(1.0, 0.5 - $normal * 0.45)), 2);

            $zones[] = [
                'key' => $key,
                'label' => $def['label'],
                'garment_cm' => $garmentCm,
                'body_cm' => $bodyCm,
                'ease_cm' => $ease,
                'level' => $level,
                'color' => Simulation::levelColor($level),
                'pressure' => $pressure,
                'note' => $this->note($key, $def, $level, $ease, $credit, $garment['source'], $pose),
            ];

            if ($level === 'tight') {
                $warnings[] = sprintf(
                    'ناحیه «%s» تنگ است: لباس %s سانتی‌متر از بدن کوچک‌تر است و کشسانی پارچه این اختلاف را جبران نمی‌کند.',
                    $def['label'],
                    $this->fa(abs($ease))
                );
            }

            if (in_array($level, ['tight', 'snug'], true)) {
                $need = round(max(0.5, ($required - $effective) + 0.5) * 2) / 2;

                $suggestions[] = [
                    'text' => sprintf('%s سانتی‌متر به آزادی «%s» اضافه شود.', $this->fa($need), $def['label']),
                    'action' => ['type' => 'increase_ease', 'key' => $key, 'value' => $need],
                ];
            }

            if ($level === 'loose' && $ease > $looseAt * 1.6) {
                $suggestions[] = [
                    'text' => sprintf('«%s» بیش از حد گشاد است؛ می‌توانید آزادی را کم کنید.', $def['label']),
                    'action' => ['type' => 'increase_ease', 'key' => $key, 'value' => -round(($ease - $ideal) / 2, 1)],
                ];
            }
        }

        foreach ($this->fabricWarnings($profile, $pattern, $pose) as $warning) {
            $warnings[] = $warning;
        }

        return [
            'zones' => $zones,
            'warnings' => array_values(array_unique($warnings)),
            'suggestions' => array_values($suggestions),
            'fit_score' => $this->score($zones, $warnings),
            'params' => $physics,
        ];
    }

    /**
     * کارنامه‌ی کیفیت پروژه؛ همه‌ی عددها ۰ تا ۱۰۰.
     *
     * هر بخشی که هنوز انجام نشده امتیاز صفر می‌گیرد تا کاربر ببیند چه چیزی مانده.
     * سرویس‌های ماژول‌های دیگر (سازگاری پارچه و بازده برش) اگر در دسترس نباشند
     * نادیده گرفته می‌شوند و صفحه از کار نمی‌افتد.
     *
     * @return array{pattern_quality: int, fabric_compatibility: int, fit: int, cutting_efficiency: int, production_readiness: int, overall: int}
     */
    public function scorecard(Project $project): array
    {
        $card = [
            'pattern_quality' => $this->patternQuality($project->pattern),
            'fabric_compatibility' => $this->fabricCompatibility($project),
            'fit' => $this->fitScoreOf($project),
            'cutting_efficiency' => $this->cuttingEfficiency($project),
            'production_readiness' => $this->productionReadiness($project),
        ];

        $weights = [
            'pattern_quality' => 25,
            'fabric_compatibility' => 15,
            'fit' => 30,
            'cutting_efficiency' => 12,
            'production_readiness' => 18,
        ];

        $sum = 0;
        $total = 0;

        foreach ($weights as $key => $weight) {
            $sum += $card[$key] * $weight;
            $total += $weight;
        }

        $card['overall'] = (int) round($sum / max(1, $total));

        $project->update(['scorecard' => $card]);

        return $card;
    }

    /*
     * ------------------------------------------------------------------
     * اندازه‌گیری لباس از روی الگو
     * ------------------------------------------------------------------
     */

    /** @return array<int, PatternPiece> */
    protected function pieces(Pattern $pattern): array
    {
        return $pattern->relationLoaded('pieces')
            ? $pattern->pieces->all()
            : $pattern->pieces()->get()->all();
    }

    /**
     * اندازه‌ی لباس در یک ناحیه.
     *
     * اول تلاش می‌کند از خطوط نشانه‌ی خودِ قطعه‌ها (markers) پهنای افقی را جمع بزند —
     * این صادقانه‌ترین راه است چون از هندسه‌ی واقعی الگو می‌آید. اگر نشانه‌ای نبود به
     * اندازه‌های ثبت‌شده‌ی الگو به‌اضافه‌ی آزادی آن برمی‌گردد و اگر آن هم نبود آزادی
     * متعارف را فرض می‌کند.
     *
     * @param  array<int, PatternPiece>  $pieces
     * @return array{cm: float, source: string}
     */
    protected function garmentValue(Pattern $pattern, array $pieces, string $zone, array $def, array $body): array
    {
        if (($def['pieces'] ?? true) === false) {
            $fromPieces = null;   // خط افقی قطعه در این ناحیه معنی «دور» ندارد (مثل قوس حلقه آستین)
        } elseif ($def['kind'] === 'length') {
            $fromPieces = $this->lengthFromPieces($pieces, $zone);
        } elseif ($def['kind'] === 'width') {
            $fromPieces = $this->widthFromPieces($pieces, $zone);
        } else {
            $fromPieces = $this->girthFromPieces($pieces, $zone, (bool) ($def['paired'] ?? false));
        }

        if ($fromPieces !== null && $this->plausible($fromPieces, (float) ($body[$def['body']] ?? 0))) {
            return ['cm' => $fromPieces, 'source' => 'pieces'];
        }

        $base = (float) ($pattern->measurements[$def['body']] ?? $body[$def['body']] ?? 0);
        $ease = $this->patternEase($pattern, $zone, $def['body']);

        if ($ease === null) {
            return ['cm' => $base + $def['ideal'], 'source' => 'assumed'];
        }

        return ['cm' => $base + $ease, 'source' => 'pattern'];
    }

    /**
     * آیا عددِ درآمده از هندسه معقول است؟
     *
     * اگر نشانه‌های الگو معنای دیگری داشته باشند ممکن است عدد پرت دربیاید؛ در آن حالت
     * به‌جای نمایش یک نتیجه‌ی نادرست، به اندازه‌های ثبت‌شده‌ی الگو برمی‌گردیم.
     */
    protected function plausible(float $value, float $bodyCm): bool
    {
        if ($value <= 0) {
            return false;
        }

        return $bodyCm <= 0 || ($value >= $bodyCm * 0.45 && $value <= $bodyCm * 3);
    }

    /** آزادی ثبت‌شده‌ی الگو برای یک ناحیه. */
    protected function patternEase(Pattern $pattern, string $zone, string $bodyKey): ?float
    {
        $ease = $pattern->ease ?? [];

        foreach ([$zone, $bodyKey] as $key) {
            if (isset($ease[$key]) && $ease[$key] !== '') {
                return (float) $ease[$key];
            }
        }

        return null;
    }

    /**
     * دور لباس در یک خط: جمع پهنای افقی قطعه‌های مربوط ضربدر تعداد برش.
     *
     * @param  array<int, PatternPiece>  $pieces
     */
    protected function girthFromPieces(array $pieces, string $zone, bool $paired = false): ?float
    {
        $relevant = $this->relevantPieces($pieces, $zone);
        $total = 0.0;
        $covered = 0;

        foreach ($relevant as $piece) {
            $marker = $this->markerFor($piece, $zone);

            // پایین دامن معمولاً نشانه ندارد؛ همان لبه‌ی پایینی قطعه‌ی دامن را می‌گیریم
            $width = $marker === null
                ? ($zone === 'hem' && $this->role($piece) === 'skirt' ? $this->bottomWidth($piece) : null)
                : ($marker['width'] ?? $this->chordWidth($piece, $marker['y'] ?? null));

            if ($width === null || $width <= 0) {
                continue;
            }

            $covered++;
            $total += $width * $this->pieceFactor($piece, $paired);
        }

        // دور لباس فقط وقتی درست است که همه‌ی قطعه‌های آن ناحیه نشانه داشته باشند؛
        // اگر مثلاً فقط قطعه‌ی پشت خط باسن داشته باشد، دور نصفه درمی‌آید.
        if ($covered === 0 || $covered < count($relevant)) {
            return null;
        }

        return round($total, 1);
    }

    /** @param  array<int, PatternPiece>  $pieces */
    protected function widthFromPieces(array $pieces, string $zone): ?float
    {
        foreach ($this->relevantPieces($pieces, $zone) as $piece) {
            $marker = $this->markerFor($piece, $zone);

            if ($marker === null) {
                continue;
            }

            $width = $marker['width'] ?? $this->chordWidth($piece, $marker['y'] ?? null);

            if ($width !== null && $width > 0) {
                return round($width * ($piece->on_fold ? 2 : 1), 1);
            }
        }

        return null;
    }

    /** @param  array<int, PatternPiece>  $pieces */
    protected function lengthFromPieces(array $pieces, string $zone): ?float
    {
        foreach ($this->relevantPieces($pieces, $zone) as $piece) {
            $marker = $this->markerFor($piece, $zone);

            if ($marker !== null && isset($marker['length'])) {
                return round((float) $marker['length'], 1);
            }
        }

        return null;
    }

    /**
     * قطعه‌های مربوط به یک ناحیه.
     *
     * @param  array<int, PatternPiece>  $pieces
     * @return array<int, PatternPiece>
     */
    protected function relevantPieces(array $pieces, string $zone): array
    {
        $outer = array_filter($pieces, fn (PatternPiece $p) => in_array($p->layer, ['outer', ''], true) || $p->layer === null);
        $byRole = [];

        foreach ($outer as $piece) {
            $byRole[$this->role($piece)][] = $piece;
        }

        $order = match ($zone) {
            'bicep' => ['sleeve'],
            'hip' => ['leg', 'skirt', 'torso'],
            'hem' => ['leg', 'skirt', 'torso'],
            'waist' => ['torso', 'skirt', 'leg'],
            default => ['torso'],
        };

        foreach ($order as $role) {
            if (! empty($byRole[$role])) {
                return $byRole[$role];
            }
        }

        return [];
    }

    /** نقش قطعه از روی کد و نامش. */
    protected function role(PatternPiece $piece): string
    {
        $haystack = mb_strtolower($piece->code.' '.$piece->name);

        foreach (static::ROLE_KEYWORDS as $role => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $role;
                }
            }
        }

        return 'torso';
    }

    /**
     * ضریب تعداد قطعه.
     *
     * قطعه‌ی روی دو لا دو برابر عرضِ رسم‌شده است و قطعه‌ی قرینه دو عدد بریده می‌شود.
     * در ناحیه‌های اندامی (آستین) تعداد نصف می‌شود، چون آستین چپ و راست هر کدام دور
     * بازوی خودشان را می‌سازند و جمع‌کردنشان دور را دو برابر نشان می‌دهد.
     */
    protected function pieceFactor(PatternPiece $piece, bool $paired = false): float
    {
        $quantity = max(1, (int) $piece->cut_quantity);

        if ($piece->mirror && $quantity === 1) {
            $quantity = 2;
        }

        if ($paired) {
            $quantity = max(1, $quantity / 2);
        }

        return $quantity * ($piece->on_fold ? 2 : 1);
    }

    /**
     * خط نشانه‌ی یک ناحیه روی قطعه.
     *
     * شکل‌های رایج پذیرفته می‌شوند: عدد (همان y)، آرایه‌ی {y|width|length}،
     * آرایه‌ی {from:{x,y}, to:{x,y}} و فهرستی از آرایه‌ها با کلید key/type.
     *
     * @return array{y?: float, width?: float, length?: float}|null
     */
    protected function markerFor(PatternPiece $piece, string $zone): ?array
    {
        $markers = $piece->markers ?? [];

        if (! is_array($markers) || $markers === []) {
            return null;
        }

        $aliases = static::MARKER_ALIASES[$zone] ?? [$zone];

        foreach ($markers as $key => $value) {
            $name = is_string($key) ? $key : (string) ($value['key'] ?? $value['type'] ?? $value['name'] ?? '');
            $name = mb_strtolower(trim($name));

            if (! in_array($name, $aliases, true)) {
                continue;
            }

            if (is_numeric($value)) {
                return ['y' => (float) $value];
            }

            if (! is_array($value)) {
                continue;
            }

            $out = [];

            if (isset($value['from']['y'], $value['to']['y'])) {
                $out['y'] = ((float) $value['from']['y'] + (float) $value['to']['y']) / 2;

                if (isset($value['from']['x'], $value['to']['x'])) {
                    $out['width'] = abs((float) $value['to']['x'] - (float) $value['from']['x']);
                }
            }

            foreach (['y', 'width', 'length'] as $field) {
                if (isset($value[$field]) && is_numeric($value[$field])) {
                    $out[$field] = (float) $value[$field];
                }
            }

            if ($out !== []) {
                return $out;
            }
        }

        return null;
    }

    /** پهنای لبه‌ی پایینی قطعه (کمی بالاتر از پایین‌ترین نقطه تا گوشه‌ها حساب نشوند). */
    protected function bottomWidth(PatternPiece $piece): ?float
    {
        [, , , $maxY] = $piece->bounds();
        $height = $piece->height();

        if ($height <= 0) {
            return null;
        }

        return $this->chordWidth($piece, $maxY - min(1.0, $height * 0.03));
    }

    /** پهنای افقی قطعه در ارتفاع y (برخورد خط افقی با چندضلعی). */
    protected function chordWidth(PatternPiece $piece, ?float $y): ?float
    {
        if ($y === null) {
            return null;
        }

        $points = $piece->points();
        $count = count($points);

        if ($count < 3) {
            return null;
        }

        $crossings = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];

            if (($a['y'] <= $y && $b['y'] >= $y) || ($b['y'] <= $y && $a['y'] >= $y)) {
                if (abs($b['y'] - $a['y']) < 0.0001) {
                    $crossings[] = $a['x'];
                    $crossings[] = $b['x'];

                    continue;
                }

                $t = ($y - $a['y']) / ($b['y'] - $a['y']);
                $crossings[] = $a['x'] + $t * ($b['x'] - $a['x']);
            }
        }

        if (count($crossings) < 2) {
            return null;
        }

        return round(max($crossings) - min($crossings), 2);
    }

    /*
     * ------------------------------------------------------------------
     * کشسانی، توضیح و امتیاز
     * ------------------------------------------------------------------
     */

    /**
     * چند سانتی‌متر تنگی را کشسانی پارچه راحت جبران می‌کند.
     *
     * فقط نیمی از توان کشسانی حساب می‌شود چون کشیدن پارچه تا آخرین حد راحت نیست.
     * در دورها کشسانی پود نقش اصلی را دارد، در قدها کشسانی تار.
     */
    protected function stretchTolerance(array $physics, string $kind, float $bodyCm): float
    {
        $weft = (float) ($physics['stretch_weft'] ?? 0);
        $warp = (float) ($physics['stretch_warp'] ?? 0);
        $bias = (float) ($physics['stretch_bias'] ?? 0);

        $ratio = match ($kind) {
            'length' => $warp + $bias * 0.25,
            'width' => ($weft + $bias * 0.25) * 0.3,
            default => $weft + $bias * 0.25,
        };

        return round(max(0.0, $bodyCm * $ratio * 0.5), 2);
    }

    /** توضیح فارسی ساده برای هر ناحیه. */
    protected function note(string $zone, array $def, string $level, float $ease, float $credit, string $source, string $pose): string
    {
        $poseHint = ($pose !== 'stand' && isset(static::POSE_FACTORS[$pose][$zone]))
            ? ' در حالت «'.Simulation::POSES[$pose].'» این ناحیه جای بیشتری می‌خواهد.'
            : '';

        $sourceHint = $source === 'assumed'
            ? ' این عدد از هندسه‌ی الگو درنیامد و با آزادی متعارف تخمین زده شده است.'
            : '';

        $stretchHint = $credit > 0.2
            ? ' کشسانی پارچه حدود '.$this->fa($credit).' سانتی‌متر از این اختلاف را جبران می‌کند.'
            : '';

        $base = match ($level) {
            'tight' => 'لباس در این ناحیه از بدن کوچک‌تر است و کشیده می‌شود.',
            'snug' => 'به بدن می‌چسبد؛ برای لباس راحت کمی آزادی بیشتر لازم است.',
            'loose' => 'آزادی زیاد است؛ لباس در این ناحیه گشاد می‌افتد.',
            default => 'آزادی این ناحیه در محدوده‌ی مناسب است.',
        };

        return $base.$stretchHint.$poseHint.$sourceHint;
    }

    /** هشدارهای مربوط به خودِ پارچه. */
    protected function fabricWarnings(FabricProfile $profile, Pattern $pattern, string $pose): array
    {
        $warnings = [];

        if ($profile->isSheer()) {
            $warnings[] = 'این پارچه نازک و نیمه‌شفاف است؛ بدون آستر بدن‌نما می‌شود.';
        }

        if ($profile->get('drape') >= 0.8 && $profile->get('stiffness') <= 0.15) {
            $warnings[] = 'پارچه بسیار لخت است؛ خطوط برش روی بدن می‌افتد و درزها ممکن است بکشد.';
        }

        if ($profile->maxStretch() >= 25 && empty($pattern->seam_allowances)) {
            $warnings[] = 'برای پارچه‌ی کشی جای دوخت و نوع درز باید مشخص شود تا درزها موج نخورد.';
        }

        if ($profile->get('shrinkage') >= 5) {
            $warnings[] = 'آب‌رفت این پارچه بالاست؛ پیش از برش پارچه را بشویید یا بخار بدهید.';
        }

        if ($pose === 'sit' && $profile->get('wrinkle_resistance') <= 0.3) {
            $warnings[] = 'این پارچه زود چین می‌خورد؛ در حالت نشسته چین‌های آن دیده می‌شود.';
        }

        return $warnings;
    }

    /** امتیاز تناسب ۰ تا ۱۰۰ با وزن نواحی و جریمه‌ی هشدارها. */
    protected function score(array $zones, array $warnings): int
    {
        $sum = 0.0;
        $total = 0.0;

        foreach ($zones as $zone) {
            $def = static::ZONES[$zone['key']];
            $weight = (float) $def['weight'];
            $deviation = abs($zone['ease_cm'] - $def['ideal']);

            $base = match ($zone['level']) {
                'good' => 100 - min(15, $deviation * 2),
                'loose' => 78 - min(20, $deviation),
                'snug' => 62 - min(20, $deviation),
                default => max(10, 34 - $deviation * 2),
            };

            $sum += $base * $weight;
            $total += $weight;
        }

        $score = $total > 0 ? $sum / $total : 0;
        $score -= count($warnings) * 3;

        return (int) round(max(0, min(100, $score)));
    }

    /*
     * ------------------------------------------------------------------
     * بخش‌های کارنامه
     * ------------------------------------------------------------------
     */

    /** کیفیت الگو: بسته بودن قطعه‌ها، راستای پارچه، جای دوخت و نشانه‌ها. */
    protected function patternQuality(?Pattern $pattern): int
    {
        if ($pattern === null) {
            return 0;
        }

        if ($pattern->pieces()->count() === 0) {
            return 15;
        }

        // امتیاز از بازرسی واقعی الگو می‌آید، نه از یک سیاهه‌ی سطحی: الگویی که
        // مسیرش خودش را قطع کند یا درزهایش روی هم پیاده نشوند، هرچقدر هم راستای
        // پارچه و نشانه داشته باشد، الگوی خوبی نیست.
        $report = $this->inspector->inspect($pattern);
        $score = $report['score'];

        if (! empty($pattern->seam_allowances)) {
            $score = min(100, $score + 2);
        }

        return (int) max(0, min(100, $score));
    }

    /** سازگاری پارچه با نوع لباس؛ از ماژول پارچه اگر در دسترس باشد. */
    protected function fabricCompatibility(Project $project): int
    {
        if (! $project->fabric || ! $project->garmentType) {
            return 0;
        }

        $service = '\App\Services\Fabric\FabricCompatibilityService';

        if (class_exists($service)) {
            try {
                $result = app($service)->score($project->fabric, $project->garmentType);
                $overall = is_array($result) ? ($result['overall'] ?? null) : null;

                if (is_numeric($overall)) {
                    return (int) round(max(0, min(100, (float) $overall)));
                }
            } catch (\Throwable) {
                // ماژول پارچه آماده نیست؛ با تخمین ساده ادامه می‌دهیم.
            }
        }

        // تخمین جانشین: پارچه‌ی سخت‌کار امتیاز کمتری می‌گیرد.
        return (int) round(100 - $project->fabric->profile()->difficulty() * 45);
    }

    /** امتیاز تناسب: آخرین شبیه‌سازی یا محاسبه‌ی تازه. */
    protected function fitScoreOf(Project $project): int
    {
        $latest = $project->latestSimulation;

        if ($latest?->fit_score !== null) {
            return (int) $latest->fit_score;
        }

        if (! $project->pattern) {
            return 0;
        }

        try {
            return (int) $this->analyze(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
            )['fit_score'];
        } catch (\Throwable) {
            return 0;
        }
    }

    /** بازده برش: آخرین چیدمان ذخیره‌شده یا محاسبه‌ی ماژول برش. */
    protected function cuttingEfficiency(Project $project): int
    {
        $layout = $project->latestCuttingLayout;

        if ($layout && $layout->efficiency > 0) {
            return (int) round(max(0, min(100, $layout->efficiency)));
        }

        $service = '\App\Services\Cutting\NestingService';

        if ($project->pattern && class_exists($service)) {
            try {
                $result = app($service)->nest($project->pattern, $project->fabric);
                $efficiency = is_array($result) ? ($result['efficiency'] ?? null) : null;

                if (is_numeric($efficiency)) {
                    return (int) round(max(0, min(100, (float) $efficiency)));
                }
            } catch (\Throwable) {
                // ماژول برش آماده نیست.
            }
        }

        return 0;
    }

    /** آمادگی تولید: چند مرحله از کار تمام شده و آیا کارت فنی ساخته شده. */
    protected function productionReadiness(Project $project): int
    {
        $score = $project->progressPercent() * 0.8;

        if ($project->techPacks()->exists()) {
            $score += 20;
        }

        return (int) round(max(0, min(100, $score)));
    }

    /** عدد فارسی با حداکثر یک رقم اعشار. */
    protected function fa(float $value): string
    {
        return Jalali::digits(rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'));
    }
}
