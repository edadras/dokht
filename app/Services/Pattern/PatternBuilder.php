<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ساخت الگو از یک الگوی پایه.
 *
 * ورودی کاربر ساده است (مدل + مشتری) و همه چیز دیگر پیش‌فرض دارد: اندازه‌های خالی
 * تخمین زده می‌شود، آزادی از نوع لباس و جای دوخت از تنظیمات کارگاه می‌آید.
 *
 * قطعه‌های خروجی دقیقاً کلیدهای جدول pattern_pieces را دارند و مسیرشان با قرارداد
 * توضیح‌داده‌شده در App\Services\Pattern\Geometry ساخته می‌شود.
 */
class PatternBuilder
{
    public function __construct(
        protected SeamAllowanceService $seams = new SeamAllowanceService,
        protected PatternVersionService $versions = new PatternVersionService,
    ) {}

    /** جای دوخت پیش‌فرض اگر کارگاه چیزی تعیین نکرده باشد. */
    public const DEFAULT_SEAM_ALLOWANCES = [
        'default' => 1.0,
        'hem' => 3.0,
        'neck' => 0.7,
        'shoulder' => 1.0,
        'side' => 1.5,
        'armhole' => 1.0,
        'waist' => 1.0,
    ];

    /**
     * قطعه‌های آماده ذخیره از یک الگوی پایه.
     *
     * خروجی را می‌توان مستقیم به $pattern->pieces()->create($piece) داد.
     *
     * @param  array<string, float>  $measurements
     * @param  array<string, float>  $ease
     * @param  array<string, mixed>  $params
     * @param  array<string, float>  $seamAllowances
     * @return array<int, array<string, mixed>>
     */
    public function buildFromTemplate(
        PatternTemplate $template,
        array $measurements,
        array $ease = [],
        array $params = [],
        array $seamAllowances = [],
    ): array {
        $generator = GeneratorRegistry::make($template->generator);
        $measurements = Measurements::complete($measurements);
        $params = array_merge($generator->defaultParams(), $template->default_params ?? [], $params);
        $map = $seamAllowances === [] ? static::DEFAULT_SEAM_ALLOWANCES : $seamAllowances;

        $pieces = [];
        $sort = 0;

        foreach ($generator->generate($measurements, $ease, $params) as $piece) {
            $piece = $this->normalizePiece($piece, $sort);
            $piece['edge_allowances'] = $this->seams->allowancesFor($piece, $map);

            $pieces[] = $piece;
            $sort += 10;
        }

        return $pieces;
    }

    /**
     * ساخت الگو برای یک پروژه.
     *
     * پروژه دست‌نخورده می‌ماند؛ وصل‌کردن الگو به پروژه کار ماژول پروژه است.
     */
    public function createForProject(Project $project, PatternTemplate $template, array $overrides = []): Pattern
    {
        $measurements = $overrides['measurements']
            ?? $project->measurementSet?->completed()
            ?? Measurements::fromSize((string) ($project->size ?: '40'));

        $ease = array_merge($project->effectiveEase(), $overrides['ease'] ?? []);
        $seamAllowances = $overrides['seam_allowances']
            ?? $project->workshop?->defaultSeamAllowances()
            ?? static::DEFAULT_SEAM_ALLOWANCES;

        $name = $overrides['name'] ?? trim(($project->name ? $project->name.' — ' : '').$template->name_fa);

        return $this->createPattern($template, [
            'workshop_id' => $project->workshop_id,
            'garment_type_id' => $project->garment_type_id ?: $template->garment_type_id,
            'measurement_set_id' => $project->measurement_set_id,
            'name' => $name,
            'base_size' => (string) ($overrides['base_size'] ?? ($project->size ?: Measurements::guessSize($measurements))),
            'notes' => $overrides['notes'] ?? null,
        ], [
            'measurements' => $measurements,
            'ease' => $ease,
            'params' => $overrides['params'] ?? [],
            'seam_allowances' => $seamAllowances,
        ]);
    }

    /**
     * ساخت الگوی مستقل (بدون پروژه) — مسیر ساده «انتخاب مدل + انتخاب مشتری».
     *
     * @param  array<string, mixed>  $attributes  فیلدهای الگو (name، base_size، measurement_set_id و ...)
     * @param  array<string, mixed>  $options  measurements، ease، params، seam_allowances
     */
    public function createPattern(PatternTemplate $template, array $attributes, array $options = []): Pattern
    {
        $measurements = Measurements::complete($options['measurements'] ?? []);
        $ease = $options['ease'] ?? ($template->garmentType?->ease() ?? []);
        $params = $options['params'] ?? [];
        $seamAllowances = $options['seam_allowances'] ?? $this->workshopSeamAllowances();

        $pieces = $this->buildFromTemplate($template, $measurements, $ease, $params, $seamAllowances);

        return DB::transaction(function () use ($template, $attributes, $measurements, $ease, $params, $seamAllowances, $pieces) {
            $pattern = Pattern::create(array_merge([
                'garment_type_id' => $template->garment_type_id,
                'pattern_template_id' => $template->id,
                'name' => $template->name_fa,
                'base_size' => Measurements::guessSize($measurements),
            ], array_filter($attributes, fn ($value) => $value !== null), [
                'measurements' => $measurements,
                'ease' => $ease,
                'params' => array_merge(GeneratorRegistry::make($template->generator)->defaultParams(), $template->default_params ?? [], $params),
                'seam_allowances' => $seamAllowances,
                'version' => 1,
            ]));

            foreach ($pieces as $piece) {
                $pattern->pieces()->create($piece);
            }

            $pattern->load('pieces');
            $pattern->forceFill(['sewing_relations' => SewingRelationBuilder::suggest($pattern)])->save();

            $this->versions->snapshot($pattern, 'ساخت الگو از «'.$template->name_fa.'»');

            return $pattern->load('pieces');
        });
    }

    /**
     * ساخت دوباره قطعه‌ها از الگوی پایه و پارامترهای ذخیره‌شده.
     *
     * پیش از هر تغییری یک نسخه ثبت می‌شود تا برگشت‌پذیر باشد.
     *
     * @param  array<string, mixed>  $overrides  measurements، ease، params، seam_allowances، base_size، name
     */
    public function regenerate(Pattern $pattern, array $overrides = []): Pattern
    {
        $template = $pattern->template;

        if ($template === null) {
            throw new RuntimeException('این الگو به هیچ الگوی پایه‌ای وصل نیست، پس نمی‌توان آن را بازتولید کرد.');
        }

        $this->versions->snapshot($pattern, $overrides['note'] ?? 'پیش از بازتولید الگو', bump: true);

        $measurements = Measurements::complete($overrides['measurements'] ?? $pattern->measurements ?? []);
        $ease = $overrides['ease'] ?? $pattern->ease ?? [];
        $params = array_merge($pattern->params ?? [], $overrides['params'] ?? []);
        $seamAllowances = $overrides['seam_allowances'] ?? $pattern->seam_allowances ?? $this->workshopSeamAllowances();

        $pieces = $this->buildFromTemplate($template, $measurements, $ease, $params, $seamAllowances);

        return DB::transaction(function () use ($pattern, $overrides, $measurements, $ease, $params, $seamAllowances, $pieces) {
            $pattern->fill(array_filter([
                'name' => $overrides['name'] ?? null,
                'base_size' => $overrides['base_size'] ?? null,
                'notes' => $overrides['notes'] ?? null,
            ], fn ($value) => $value !== null));

            $pattern->forceFill([
                'measurements' => $measurements,
                'ease' => $ease,
                'params' => $params,
                'seam_allowances' => $seamAllowances,
            ])->save();

            $pattern->pieces()->delete();

            foreach ($pieces as $piece) {
                $pattern->pieces()->create($piece);
            }

            $pattern->load('pieces');
            $pattern->forceFill(['sewing_relations' => SewingRelationBuilder::suggest($pattern)])->save();

            return $pattern->load('pieces');
        });
    }

    /** رونوشت کامل یک الگو با همه قطعه‌ها. */
    public function duplicate(Pattern $pattern, ?string $name = null): Pattern
    {
        return DB::transaction(function () use ($pattern, $name) {
            $copy = Pattern::create([
                'workshop_id' => $pattern->workshop_id,
                'garment_type_id' => $pattern->garment_type_id,
                'pattern_template_id' => $pattern->pattern_template_id,
                'measurement_set_id' => $pattern->measurement_set_id,
                'name' => $name ?: $pattern->name.' (رونوشت)',
                'base_size' => $pattern->base_size,
                'measurements' => $pattern->measurements,
                'ease' => $pattern->ease,
                'seam_allowances' => $pattern->seam_allowances,
                'params' => $pattern->params,
                'sewing_relations' => $pattern->sewing_relations,
                'version' => 1,
                'notes' => $pattern->notes,
            ]);

            foreach ($pattern->pieces as $piece) {
                $copy->pieces()->create($piece->only(PatternVersionService::PIECE_KEYS));
            }

            $copy->load('pieces');
            $this->versions->snapshot($copy, 'رونوشت از «'.$pattern->name.'»');

            return $copy;
        });
    }

    /**
     * پر کردن کلیدهای قطعه و مرتب‌سازی هندسه آن.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function normalizePiece(array $piece, int $sort): array
    {
        $piece = array_merge([
            'code' => 'piece-'.($sort / 10 + 1),
            'name' => 'قطعه',
            'layer' => 'outer',
            'cut_quantity' => 1,
            'on_fold' => false,
            'mirror' => false,
            'outline' => [],
            'grainline' => null,
            'darts' => [],
            'notches' => [],
            'drills' => [],
            'pleats' => [],
            'markers' => [],
            'edge_allowances' => [],
            'meta' => [],
            'sort' => $sort,
        ], $piece);

        $piece['sort'] = $piece['sort'] ?: $sort;
        $piece = Geometry::normalizePiece($piece);
        $piece['outline'] = Geometry::round($piece['outline']);

        return $piece;
    }

    /** @return array<string, float> */
    protected function workshopSeamAllowances(): array
    {
        return app(WorkshopContext::class)->get()?->defaultSeamAllowances() ?? static::DEFAULT_SEAM_ALLOWANCES;
    }
}
