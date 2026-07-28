<?php

namespace App\Services\TechPack;

use App\Models\CuttingLayout;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\Project;
use App\Models\Simulation;
use App\Models\TechPack;
use App\Services\Cutting\NestingService;
use App\Services\Export\BillOfMaterialsBuilder;
use App\Services\Export\MeasurementSheetBuilder;
use App\Services\Export\SewingInstructionsBuilder;
use App\Support\FabricProfile;
use App\Support\Format;
use App\Support\Jalali;
use ReflectionMethod;
use Throwable;

/**
 * ساخت «کارت فنی» پروژه: تنها برگه‌ای که برای تولید لباس لازم است.
 *
 * همه بخش‌ها در برابر داده نداشته مقاوم‌اند؛ پروژه‌ای که هنوز پارچه یا الگو ندارد هم
 * کارت فنی ناقص ولی معتبر می‌گیرد و در فهرست `missing` به فارسی می‌بیند چه چیزی مانده.
 */
class TechPackBuilder
{
    /** برچسب فارسی جای دوخت‌ها. */
    public const ALLOWANCE_LABELS = [
        'default' => 'پیش‌فرض همه لبه‌ها',
        'hem' => 'تودوزی پایین',
        'neck' => 'حلقه گردن',
        'shoulder' => 'سرشانه',
        'side' => 'پهلو',
        'armhole' => 'حلقه آستین',
        'waist' => 'خط کمر',
        'sleeve' => 'آستین',
    ];

    public function __construct(
        protected NestingService $nesting = new NestingService,
        protected MeasurementSheetBuilder $measurements = new MeasurementSheetBuilder,
        protected SewingInstructionsBuilder $sewing = new SewingInstructionsBuilder,
        protected BillOfMaterialsBuilder $bom = new BillOfMaterialsBuilder,
    ) {}

    public function build(Project $project): array
    {
        $project->loadMissing([
            'customer', 'garmentType', 'measurementSet', 'fabric.fabricType',
            'liningFabric', 'pattern.pieces', 'latestSimulation', 'latestCuttingLayout',
        ]);

        $pattern = $project->pattern;
        $cutting = $this->cutting($project, $pattern);
        $instructions = $this->safe(fn () => $this->sewing->build($project), [
            'steps' => [], 'advice' => [], 'warnings' => [], 'source' => 'garment_parts',
        ]);
        $bom = $this->safe(fn () => $this->bom->build($project), ['rows' => [], 'total' => 0]);

        return [
            'garment' => $this->garment($project),
            'fabric' => $this->fabric($project, $cutting),
            'pattern' => $this->pattern($pattern),
            'measurements' => $this->safe(fn () => $this->measurements->build($project), ['rows' => []]),
            'construction' => $instructions['steps'],
            'construction_warnings' => $instructions['warnings'],
            'materials' => $this->materials($project, $instructions, $bom),
            'bom' => $bom,
            'cutting' => $cutting,
            'fit' => $this->fit($project),
            'quality' => $this->quality($project),
            'notes' => $this->notes($project),
            'missing' => $this->missing($project),
            'generated_at' => Jalali::dateTime(now()),
        ];
    }

    /** ذخیره کارت فنی به‌عنوان نسخه تازه. */
    public function store(Project $project): TechPack
    {
        return TechPack::create([
            'workshop_id' => $project->workshop_id,
            'project_id' => $project->id,
            'version' => TechPack::nextVersion($project),
            'data' => $this->build($project),
        ]);
    }

    /** شناسنامه لباس. */
    protected function garment(Project $project): array
    {
        return [
            'name' => $project->name,
            'type' => $project->garmentType?->name_fa,
            'category' => $project->garmentType?->categoryLabel(),
            'parts' => $project->garmentType?->partLabels() ?? [],
            'size' => $project->size ?: $project->measurementSet?->base_size,
            'customer' => $project->customer?->name,
            'customer_phone' => $project->customer?->phone,
            'workshop' => $project->workshop?->name,
            'status' => $project->statusLabel(),
            'date_jalali' => Jalali::date($project->created_at),
            'progress_percent' => $project->progressPercent(),
        ];
    }

    /** پارچه و مصرف. */
    protected function fabric(Project $project, array $cutting): array
    {
        $fabric = $project->fabric;
        $profile = $fabric?->profile() ?? FabricProfile::make();
        $meters = (float) ($cutting['meters'] ?? 0);

        return [
            'name' => $fabric?->displayName(),
            'color' => $fabric?->color,
            'type' => $fabric?->typeName(),
            'family' => $fabric?->fabricType?->familyLabel(),
            'composition' => $fabric?->fabricType?->compositionLabel(),
            'surface_pattern' => $fabric?->surfacePatternLabel(),
            'pattern_repeat_cm' => $fabric?->pattern_repeat_cm,
            'width' => $fabric?->effectiveWidth(),
            'gsm' => $fabric?->gsm ?? (int) $profile->get('weight_gsm'),
            'weight_label' => $profile->weightLabel(),
            'drape_label' => $profile->drapeLabel(),
            'directional' => (bool) $fabric?->isDirectional(),
            'needs_pattern_matching' => (bool) $fabric?->needsPatternMatching(),
            'consumption_m' => $meters,
            'price_per_meter' => $fabric?->price_per_meter,
            'cost' => $fabric?->price_per_meter ? round($meters * $fabric->price_per_meter) : null,
            'cost_label' => $fabric?->price_per_meter ? Format::money(round($meters * $fabric->price_per_meter)) : null,
            'lining' => $project->liningFabric?->displayName(),
            'care' => $fabric?->fabricType?->care ?? [],
        ];
    }

    /** الگو و قطعه‌ها. */
    protected function pattern(?Pattern $pattern): array
    {
        if ($pattern === null) {
            return [
                'name' => null,
                'version' => null,
                'piece_count' => 0,
                'total_area' => 0.0,
                'seam_allowances' => [],
                'pieces' => [],
            ];
        }

        return [
            'id' => $pattern->id,
            'name' => $pattern->name,
            'version' => $pattern->version,
            'base_size' => $pattern->base_size,
            'piece_count' => $pattern->pieceCount(),
            'total_area' => $pattern->totalArea(),
            'total_area_label' => Jalali::digits(number_format($pattern->totalArea() / 10000, 2)).' متر مربع',
            'seam_allowances' => collect($pattern->seam_allowances ?? [])
                ->map(fn ($value, $key) => [
                    'key' => $key,
                    'label' => static::ALLOWANCE_LABELS[$key] ?? $key,
                    'value' => round((float) $value, 2),
                ])
                ->values()
                ->all(),
            'pieces' => $pattern->pieces
                ->map(fn (PatternPiece $piece) => [
                    'code' => $piece->code,
                    'name' => $piece->name,
                    'layer' => $piece->layer,
                    'layer_label' => $piece->layerLabel(),
                    'cut_quantity' => (int) $piece->cut_quantity,
                    'on_fold' => (bool) $piece->on_fold,
                    'mirror' => (bool) $piece->mirror,
                    'width' => $piece->width(),
                    'height' => $piece->height(),
                    'area' => $piece->area(),
                    'perimeter' => $piece->perimeter(),
                    'notches' => count((array) ($piece->notches ?? [])),
                    'darts' => count((array) ($piece->darts ?? [])),
                    'drills' => count((array) ($piece->drills ?? [])),
                ])
                ->values()
                ->all(),
        ];
    }

    /** نقشه برش: چیدمان ثبت‌شده یا برآورد لحظه‌ای. */
    protected function cutting(Project $project, ?Pattern $pattern): array
    {
        $layout = $project->latestCuttingLayout;

        if ($layout instanceof CuttingLayout) {
            return [
                'layout_id' => $layout->id,
                'estimated' => false,
                'width' => (float) $layout->fabric_width_cm,
                'usable_width' => $layout->usableWidthCm(),
                'length' => (float) $layout->required_length_cm,
                'meters' => $layout->requiredMeters(),
                'waste' => (float) $layout->waste_percent,
                'efficiency' => (float) $layout->efficiency,
                'folded' => (bool) $layout->folded,
                'respect_nap' => (bool) $layout->respect_nap,
                'match_stripes' => (bool) $layout->match_stripes,
                'placement_count' => $layout->placementCount(),
                'cost' => $layout->estimatedCost(),
                'warnings' => (array) ($layout->warnings ?? []),
                'summary' => $layout->summaryText(),
            ];
        }

        if ($pattern === null) {
            return [
                'layout_id' => null,
                'estimated' => true,
                'width' => null,
                'usable_width' => null,
                'length' => 0.0,
                'meters' => 0.0,
                'waste' => 0.0,
                'efficiency' => 0.0,
                'folded' => true,
                'placement_count' => 0,
                'cost' => null,
                'warnings' => ['هنوز الگویی برای این پروژه ساخته نشده است؛ مصرف پارچه محاسبه نشد.'],
                'summary' => null,
            ];
        }

        $result = $this->safe(
            fn () => $this->nesting->nest($pattern, $project->fabric),
            null,
        );

        if ($result === null) {
            return [
                'layout_id' => null,
                'estimated' => true,
                'width' => null,
                'usable_width' => null,
                'length' => 0.0,
                'meters' => 0.0,
                'waste' => 0.0,
                'efficiency' => 0.0,
                'folded' => true,
                'placement_count' => 0,
                'cost' => null,
                'warnings' => ['محاسبه چیدمان برش ممکن نشد.'],
                'summary' => null,
            ];
        }

        $price = $project->fabric?->price_per_meter;

        return [
            'layout_id' => null,
            'estimated' => true,
            'width' => $result['fabric_width_cm'],
            'usable_width' => $result['usable_width_cm'],
            'length' => $result['required_length_cm'],
            'meters' => $result['required_meters'],
            'waste' => $result['waste_percent'],
            'efficiency' => $result['efficiency'],
            'folded' => (bool) $result['options']['folded'],
            'respect_nap' => (bool) $result['options']['respect_nap'],
            'match_stripes' => (bool) $result['options']['match_stripes'],
            'placement_count' => count($result['placements']),
            'cost' => $price ? round($result['required_meters'] * $price) : null,
            'warnings' => $result['warnings'],
            'summary' => sprintf(
                '%s متر پارچه لازم است؛ دورریز %s',
                Jalali::digits(number_format($result['required_meters'], 2)),
                Format::percent($result['waste_percent'], 1),
            ),
        ];
    }

    /**
     * پیشنهاد مواد و ابزار دوخت.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function materials(Project $project, array $instructions, array $bom): array
    {
        $advice = $instructions['advice'] ?? [];
        $rows = [];

        foreach ([
            'needle' => 'سوزن',
            'thread' => 'نخ',
            'stitch' => 'کوک',
            'seam_finish' => 'پاک‌دوزی درز',
            'pressing' => 'اتو',
        ] as $key => $label) {
            if (! empty($advice[$key])) {
                $rows[] = ['kind' => $key, 'label' => $label, 'value' => $advice[$key], 'source' => 'fabric_profile'];
            }
        }

        foreach ((array) ($bom['rows'] ?? []) as $row) {
            if (in_array($row['kind'] ?? '', ['fabric', 'thread'], true)) {
                continue;
            }

            $rows[] = [
                'kind' => $row['kind'] ?? 'other',
                'label' => $row['kind_label'] ?? 'مواد',
                'value' => trim(sprintf(
                    '%s — %s %s',
                    $row['label'] ?? '',
                    Jalali::digits((string) ($row['quantity'] ?? '')),
                    $row['unit'] ?? '',
                )),
                'source' => $row['source'] ?? 'estimate',
            ];
        }

        if ($project->liningFabric) {
            $rows[] = [
                'kind' => 'lining',
                'label' => 'آستر',
                'value' => $project->liningFabric->displayName(),
                'source' => 'project',
            ];
        }

        return $rows;
    }

    /** خلاصه آخرین شبیه‌سازی تناسب. */
    protected function fit(Project $project): ?array
    {
        $simulation = $project->latestSimulation;

        if (! $simulation instanceof Simulation) {
            return null;
        }

        return [
            'simulation_id' => $simulation->id,
            'pose' => $simulation->poseLabel(),
            'score' => $simulation->fit_score,
            'zones' => collect((array) ($simulation->zones ?? []))
                ->map(function ($zone, $key) {
                    $level = is_array($zone) ? ($zone['level'] ?? null) : null;

                    return [
                        'key' => is_string($key) ? $key : ($zone['key'] ?? ''),
                        'label' => is_array($zone) ? ($zone['label'] ?? (is_string($key) ? $key : '')) : (string) $key,
                        'level' => $level,
                        'level_label' => $level ? Simulation::levelLabel($level) : null,
                        'color' => $level ? Simulation::levelColor($level) : null,
                        'value' => is_array($zone) ? ($zone['value'] ?? $zone['ease'] ?? null) : $zone,
                    ];
                })
                ->values()
                ->all(),
            'warnings' => (array) ($simulation->warnings ?? []),
            'suggestions' => (array) ($simulation->suggestions ?? []),
            'date_jalali' => Jalali::date($simulation->created_at),
        ];
    }

    /** کارنامه کیفیت طراحی. */
    protected function quality(Project $project): ?array
    {
        $service = 'App\Services\Fit\FitAnalysisService';

        if (class_exists($service)) {
            try {
                $method = new ReflectionMethod($service, 'scorecard');
                $card = $method->invoke($method->isStatic() ? null : app($service), $project);

                if (is_array($card) && $card !== []) {
                    return $card;
                }
            } catch (Throwable) {
                // سرویس تحلیل تناسب در دسترس نبود
            }
        }

        return empty($project->scorecard) ? null : $project->scorecard;
    }

    /** @return array<int, string> */
    protected function notes(Project $project): array
    {
        return array_values(array_filter([
            $project->notes,
            $project->pattern?->notes,
            $project->fabric?->notes,
        ]));
    }

    /**
     * چیزهایی که برای کامل شدن کارت فنی لازم است، به فارسی.
     *
     * @return array<int, string>
     */
    protected function missing(Project $project): array
    {
        $missing = [];

        if ($project->customer_id === null) {
            $missing[] = 'مشتری پروژه انتخاب نشده است.';
        }

        if ($project->garment_type_id === null) {
            $missing[] = 'نوع لباس مشخص نشده است.';
        }

        if ($project->measurement_set_id === null) {
            $missing[] = 'اندازه‌های بدن ثبت نشده است؛ برگه اندازه با جدول سایز پر شده.';
        }

        if ($project->fabric_id === null) {
            $missing[] = 'پارچه انتخاب نشده است؛ مصرف و هزینه دقیق نیست.';
        }

        if ($project->pattern_id === null) {
            $missing[] = 'الگو ساخته نشده است؛ فهرست قطعه‌ها و نقشه برش خالی است.';
        } elseif (($project->pattern?->pieces->count() ?? 0) === 0) {
            $missing[] = 'الگو هیچ قطعه‌ای ندارد.';
        }

        if ($project->latestCuttingLayout === null) {
            $missing[] = 'نقشه برش ساخته نشده است؛ مصرف پارچه برآوردی است.';
        }

        if ($project->latestSimulation === null) {
            $missing[] = 'تست تناسب روی مانکن انجام نشده است.';
        }

        return $missing;
    }

    /** اجرای امن یک بخش؛ خرابی یک بخش کل کارت فنی را از بین نمی‌برد. */
    protected function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
