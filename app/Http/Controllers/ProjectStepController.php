<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Models\Simulation;
use App\Services\Fit\FitAnalysisService;
use App\Services\Simulation\ClothPreviewService;
use App\Support\Measurements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * مسیر گام‌به‌گام ساخت لباس.
 *
 * قرار ما این است: هر صفحه یک سؤال ساده می‌پرسد، یک تا سه ورودی با مقدار پیش‌فرض
 * درست دارد، یک دکمه‌ی «ادامه» و یک توضیح کوتاه که سامانه چه کاری برای کاربر کرد.
 * همه‌ی دکمه‌های حرفه‌ای داخل «تنظیمات پیشرفته» پنهان می‌شوند.
 */
class ProjectStepController extends Controller
{
    public function show(Request $request, Project $project, string $step): View
    {
        $number = Project::stepNumber($step);

        abort_if($number === null, 404);

        $project->loadMissing(['customer', 'garmentType', 'fabric', 'liningFabric', 'measurementSet', 'pattern']);

        return view("projects.steps.{$step}", array_merge([
            'project' => $project,
            'step' => $number,
            'stepKey' => $step,
            'stepTitle' => Project::STEPS[$number]['title'],
        ], $this->stepData($request, $project, $step)));
    }

    public function update(Request $request, Project $project, string $step)
    {
        $number = Project::stepNumber($step);

        abort_if($number === null, 404);

        $result = match ($step) {
            'design' => $this->saveDesign($request, $project),
            'body' => $this->saveBody($request, $project),
            'fabric' => $this->saveFabric($request, $project),
            'pattern' => $this->savePattern($request, $project),
            'seam' => $this->saveSeam($request, $project),
            'layout' => $this->saveLayout($request, $project),
            'simulation' => $this->saveSimulation($request, $project),
            'fit' => $this->saveFit($request, $project),
            default => ['message' => 'این مرحله ذخیره‌ی جداگانه ندارد؛ به مرحله‌ی بعد می‌رویم.'],
        };

        // بعضی کارها (افزودن درز یا اعمال پیشنهاد) کاربر را در همان مرحله نگه می‌دارند
        if ($result['stay'] ?? false) {
            return redirect()
                ->route('projects.step', [$project, $step])
                ->with('status', $result['message']);
        }

        if ($result['error'] ?? false) {
            return redirect()
                ->route('projects.step', [$project, $result['goto'] ?? $step])
                ->with('error', $result['message']);
        }

        $target = $this->advance($project, $number);

        return redirect()
            ->route('projects.step', [$project, Project::stepKey($target)])
            ->with('status', $result['message']);
    }

    /**
     * تولید الگو از یک الگوی پایه.
     *
     * ساخت هندسه کار ماژول الگو است؛ اینجا فقط الگوی پایه انتخاب و نتیجه به پروژه
     * وصل می‌شود و دوخت‌های پیشنهادی هم ذخیره می‌شوند.
     */
    public function generatePattern(Request $request, Project $project)
    {
        $data = $request->validate([
            'pattern_template_id' => ['required', 'integer', 'exists:pattern_templates,id'],
        ], [
            'pattern_template_id.required' => 'یک الگوی پایه انتخاب کنید.',
        ]);

        $template = PatternTemplate::query()
            ->availableTo($project->workshop_id)
            ->findOr($data['pattern_template_id'], fn () => null);

        if ($template === null) {
            return back()->with('error', 'این الگوی پایه در دسترس کارگاه شما نیست.');
        }

        $builder = '\App\Services\Pattern\PatternBuilder';

        if (! class_exists($builder)) {
            return back()->with('error', 'موتور تولید الگو هنوز آماده نیست؛ کمی بعد دوباره امتحان کنید.');
        }

        try {
            $pattern = app($builder)->createForProject($project, $template, [
                'measurements' => $project->resolvedMeasurements(),
                'ease' => $project->effectiveEase(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'تولید الگو انجام نشد. اندازه‌ها و نوع لباس را بررسی کنید.');
        }

        $project->update(['pattern_id' => $pattern->id]);
        $this->seedSewingRelations($pattern);

        return redirect()
            ->route('projects.step', [$project, 'seam'])
            ->with('status', 'الگو ساخته شد: '.$pattern->name.'. حالا جای دوخت را تأیید کنید.');
    }

    /*
     * ------------------------------------------------------------------
     * داده‌ی هر صفحه
     * ------------------------------------------------------------------
     */

    protected function stepData(Request $request, Project $project, string $step): array
    {
        return match ($step) {
            'design' => $this->designData(),
            'body' => $this->bodyData($request, $project),
            'fabric' => $this->fabricData($project),
            'pattern' => $this->patternData($project),
            'seam' => $this->seamData($project),
            'layout' => $this->layoutData($project),
            'simulation' => $this->simulationData($project),
            'fit' => $this->fitData($project),
            'cutting' => ['layout' => $project->latestCuttingLayout],
            'techpack' => ['techPack' => $project->techPacks()->first(), 'techPacks' => $project->techPacks],
            'export' => [
                'techPack' => $project->techPacks()->first(),
                'layout' => $project->latestCuttingLayout,
            ],
            default => [],
        };
    }

    protected function designData(): array
    {
        return [
            'grouped' => GarmentType::query()
                ->active()
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->groupBy('category'),
        ];
    }

    protected function bodyData(Request $request, Project $project): array
    {
        $customerId = (int) $request->integer('customer_id', (int) $project->customer_id);

        return [
            'customers' => Customer::query()->latest('id')->limit(200)->get(['id', 'name']),
            'sets' => $customerId
                ? MeasurementSet::query()->where('customer_id', $customerId)->latest('id')->get()
                : collect(),
            'selectedCustomerId' => $customerId ?: null,
            'essential' => Measurements::essential(),
            'sizes' => Measurements::sizes(),
            'measurements' => $project->resolvedMeasurements(),
            'guessedSize' => Measurements::guessSize($project->resolvedMeasurements()),
        ];
    }

    protected function fabricData(Project $project): array
    {
        $fabrics = Fabric::query()->active()->with('fabricType')->latest('id')->limit(60)->get();

        return [
            'fabrics' => $fabrics,
            'scores' => $this->compatibilityScores($fabrics, $project),
            'ruleFindings' => $this->ruleFindings($project),
        ];
    }

    protected function patternData(Project $project): array
    {
        return [
            'templates' => PatternTemplate::query()
                ->availableTo($project->workshop_id)
                ->when($project->garment_type_id, fn ($q) => $q->where('garment_type_id', $project->garment_type_id))
                ->orderBy('sort')
                ->orderBy('id')
                ->get(),
            'allTemplates' => PatternTemplate::query()
                ->availableTo($project->workshop_id)
                ->orderBy('sort')
                ->limit(60)
                ->get(),
            'svg' => $this->patternSvg($project->pattern),
        ];
    }

    protected function seamData(Project $project): array
    {
        $workshopDefaults = $project->workshop?->defaultSeamAllowances() ?? [
            'default' => 1.0, 'hem' => 3.0, 'neck' => 0.7,
            'shoulder' => 1.0, 'side' => 1.5, 'armhole' => 1.0, 'waist' => 1.0,
        ];

        return [
            'edges' => [
                'default' => 'همه لبه‌ها (پیش‌فرض)',
                'hem' => 'پایین لباس',
                'neck' => 'یقه',
                'shoulder' => 'سرشانه',
                'side' => 'پهلو',
                'armhole' => 'حلقه آستین',
                'waist' => 'خط کمر',
            ],
            'allowances' => array_merge($workshopDefaults, $project->pattern?->seam_allowances ?? []),
            'defaults' => $workshopDefaults,
            'ruleFindings' => $this->ruleFindings($project),
        ];
    }

    protected function layoutData(Project $project): array
    {
        $pattern = $project->pattern;
        $pieces = $pattern ? $pattern->pieces()->get() : collect();

        return [
            'svg' => $this->patternSvg($pattern),
            'pieces' => $pieces,
            'relations' => $this->relations($pattern),
            'suggested' => $this->suggestedRelations($pattern),
            'edgeOptions' => $this->edgeOptions($pieces),
        ];
    }

    protected function simulationData(Project $project): array
    {
        $analysis = $this->analysis($project, $project->settings['pose'] ?? 'stand');

        return [
            'poses' => Simulation::POSES,
            'pose' => $project->settings['pose'] ?? 'stand',
            'analysis' => $analysis,
            'payload' => $this->preview($project, $analysis),
            'simulation' => $project->latestSimulation,
        ];
    }

    protected function fitData(Project $project): array
    {
        $simulation = $project->latestSimulation;

        $analysis = $simulation
            ? [
                'zones' => $simulation->zones ?? [],
                'warnings' => $simulation->warnings ?? [],
                'suggestions' => $simulation->suggestions ?? [],
                'fit_score' => $simulation->fit_score,
                'params' => $simulation->params ?? [],
                'pose' => $simulation->pose,
            ]
            : $this->analysis($project);

        return [
            'simulation' => $simulation,
            'analysis' => $analysis,
            'levels' => Simulation::LEVELS,
            'scorecard' => $this->scorecard($project),
        ];
    }

    /*
     * ------------------------------------------------------------------
     * ذخیره‌ی هر مرحله
     * ------------------------------------------------------------------
     */

    /** مرحله ۱ — کدام لباس را می‌دوزیم؟ */
    protected function saveDesign(Request $request, Project $project): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'garment_type_id' => ['required', 'integer', 'exists:garment_types,id'],
            'ease' => ['nullable', 'array'],
            'ease.*' => ['nullable', 'numeric', 'between:-15,40'],
        ], [
            'garment_type_id.required' => 'یکی از مدل‌ها را انتخاب کنید.',
        ]);

        $project->update([
            'name' => $data['name'],
            'garment_type_id' => $data['garment_type_id'],
            'ease' => $this->cleanEase($data['ease'] ?? []),
            'status' => $project->status === 'draft' ? 'in_progress' : $project->status,
        ]);

        return ['message' => 'مدل لباس ثبت شد. آزادی پیش‌فرض همین مدل برای الگو استفاده می‌شود.'];
    }

    /** مرحله ۲ — اندازه‌ی بدن از کجا بیاید؟ */
    protected function saveBody(Request $request, Project $project): array
    {
        $mode = $request->input('mode', 'inline');

        if ($mode === 'existing') {
            $data = $request->validate([
                'customer_id' => ['required', 'integer', 'exists:customers,id'],
                'measurement_set_id' => ['required', 'integer', 'exists:measurement_sets,id'],
            ], [
                'measurement_set_id.required' => 'یکی از دفترچه‌های اندازه را انتخاب کنید.',
            ]);

            $set = MeasurementSet::findOrFail($data['measurement_set_id']);

            $project->update([
                'customer_id' => $data['customer_id'],
                'measurement_set_id' => $set->id,
                'size' => $set->base_size ?: Measurements::guessSize($set->values ?? []),
            ]);

            return ['message' => 'اندازه‌های «'.$set->displayTitle().'» به پروژه وصل شد.'];
        }

        if ($mode === 'size') {
            $data = $request->validate([
                'size' => ['required', 'string', 'in:'.implode(',', Measurements::sizes())],
            ]);

            $set = MeasurementSet::create([
                'customer_id' => $project->customer_id,
                'title' => 'سایز استاندارد '.$data['size'],
                'base_size' => $data['size'],
                'values' => Measurements::SIZE_TABLE[$data['size']],
                'is_default' => false,
            ]);

            $project->update(['measurement_set_id' => $set->id, 'size' => $data['size']]);

            return ['message' => 'سایز استاندارد '.$data['size'].' انتخاب شد و بقیه‌ی اندازه‌ها خودکار تخمین زده شد.'];
        }

        // شش عدد ضروری، همان‌جا
        $rules = ['customer_name' => ['nullable', 'string', 'max:120']];

        foreach (Measurements::essential() as $key => $field) {
            $rules[$key] = ['required', 'numeric', 'between:'.$field['min'].','.$field['max']];
        }

        foreach (Measurements::optional() as $key => $field) {
            $rules[$key] = ['nullable', 'numeric', 'between:'.$field['min'].','.$field['max']];
        }

        $data = Validator::make($request->all(), $rules, [], $this->measurementNames())->validate();

        $values = Measurements::clean($data);
        $customerId = $project->customer_id;

        if (($name = trim((string) ($data['customer_name'] ?? ''))) !== '') {
            $customerId = Customer::create(['name' => $name])->id;
        }

        $set = MeasurementSet::create([
            'customer_id' => $customerId,
            'title' => 'اندازه‌های امروز',
            'base_size' => Measurements::guessSize($values),
            'values' => $values,
            'is_default' => true,
        ]);

        $project->update([
            'customer_id' => $customerId,
            'measurement_set_id' => $set->id,
            'size' => $set->base_size,
        ]);

        return ['message' => 'اندازه‌ها ثبت شد؛ نزدیک‌ترین سایز '.$set->base_size.' است و بقیه‌ی اندازه‌ها تخمین زده شد.'];
    }

    /** مرحله ۳ — با چه پارچه‌ای؟ */
    protected function saveFabric(Request $request, Project $project): array
    {
        $data = $request->validate([
            'fabric_id' => ['required', 'integer', 'exists:fabrics,id'],
            'lining_fabric_id' => ['nullable', 'integer', 'exists:fabrics,id'],
        ], [
            'fabric_id.required' => 'یکی از پارچه‌ها را انتخاب کنید.',
        ]);

        $project->update([
            'fabric_id' => $data['fabric_id'],
            'lining_fabric_id' => $data['lining_fabric_id'] ?? null,
        ]);

        return ['message' => 'پارچه انتخاب شد. رفتار همین پارچه در نمای سه‌بعدی و تست تناسب استفاده می‌شود.'];
    }

    /** مرحله ۴ — الگو؛ اتصال یک الگوی آماده. */
    protected function savePattern(Request $request, Project $project): array
    {
        $data = $request->validate([
            'pattern_id' => ['nullable', 'integer', 'exists:patterns,id'],
        ]);

        if (! empty($data['pattern_id'])) {
            $project->update(['pattern_id' => $data['pattern_id']]);

            return ['message' => 'الگو به پروژه وصل شد.'];
        }

        if (! $project->pattern_id) {
            return ['error' => true, 'message' => 'ابتدا یک الگو بسازید یا انتخاب کنید.', 'goto' => 'pattern'];
        }

        return ['message' => 'الگوی پروژه تأیید شد.'];
    }

    /** مرحله ۵ — جای دوخت. */
    protected function saveSeam(Request $request, Project $project): array
    {
        if (! $pattern = $project->pattern) {
            return ['error' => true, 'message' => 'برای تعیین جای دوخت اول باید الگو ساخته شود.', 'goto' => 'pattern'];
        }

        $data = $request->validate([
            'allowances' => ['required', 'array'],
            'allowances.*' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        $allowed = ['default', 'hem', 'neck', 'shoulder', 'side', 'armhole', 'waist'];
        $allowances = [];

        foreach ($data['allowances'] as $edge => $value) {
            if (in_array($edge, $allowed, true) && $value !== null && $value !== '') {
                $allowances[$edge] = round((float) $value, 2);
            }
        }

        $allowances['default'] ??= 1.0;
        $pattern->update(['seam_allowances' => $allowances]);

        return ['message' => 'جای دوخت ذخیره شد؛ از این پس در چاپ و برش همین مقدارها اضافه می‌شود.'];
    }

    /** مرحله ۶ — چیدمان و دوخت مجازی. */
    protected function saveLayout(Request $request, Project $project): array
    {
        if (! $pattern = $project->pattern) {
            return ['error' => true, 'message' => 'برای دیدن چیدمان اول باید الگو ساخته شود.', 'goto' => 'pattern'];
        }

        $relations = $this->relations($pattern);

        if ($request->filled('remove')) {
            $index = (int) $request->input('remove');
            unset($relations[$index]);
            $pattern->update(['sewing_relations' => array_values($relations)]);

            return ['stay' => true, 'message' => 'این درز حذف شد.'];
        }

        if ($request->filled(['add_from', 'add_to'])) {
            $data = $request->validate([
                'add_from' => ['required', 'string', 'max:80'],
                'add_to' => ['required', 'string', 'max:80', 'different:add_from'],
            ], [
                'add_to.different' => 'یک لبه را نمی‌توان به خودش دوخت.',
            ]);

            [$fromPiece, $fromEdge] = $this->parseEdge($data['add_from']);
            [$toPiece, $toEdge] = $this->parseEdge($data['add_to']);

            $relations[] = [
                'from_piece' => $fromPiece,
                'from_edge' => $fromEdge,
                'to_piece' => $toPiece,
                'to_edge' => $toEdge,
                'type' => 'seam',
            ];

            $pattern->update(['sewing_relations' => array_values($relations)]);

            return ['stay' => true, 'message' => 'درز تازه اضافه شد.'];
        }

        if ($request->boolean('accept_suggestions')) {
            $suggested = $this->suggestedRelations($pattern);

            if ($suggested !== []) {
                $pattern->update(['sewing_relations' => array_values(array_merge($relations, $suggested))]);

                return ['stay' => true, 'message' => 'درزهای پیشنهادی سامانه اضافه شد.'];
            }
        }

        return ['message' => 'چیدمان و درزها تأیید شد.'];
    }

    /** مرحله ۷ — حالت بدن برای نمای سه‌بعدی. */
    protected function saveSimulation(Request $request, Project $project): array
    {
        $data = $request->validate([
            'pose' => ['nullable', 'string', 'in:'.implode(',', array_keys(Simulation::POSES))],
        ]);

        $project->update([
            'settings' => array_merge($project->settings ?? [], ['pose' => $data['pose'] ?? 'stand']),
        ]);

        if (! $project->simulations()->exists()) {
            return ['error' => true, 'message' => 'اول دکمه‌ی «شبیه‌سازی کن» را بزنید تا لباس روی مانکن بیفتد.', 'goto' => 'simulation'];
        }

        return ['message' => 'نمای سه‌بعدی ثبت شد.'];
    }

    /** مرحله ۸ — تست تناسب و اعمال پیشنهادها. */
    protected function saveFit(Request $request, Project $project): array
    {
        if ($request->input('action_type') === 'increase_ease') {
            $data = $request->validate([
                'action_key' => ['required', 'string', 'max:32'],
                'action_value' => ['required', 'numeric', 'between:-20,20'],
            ]);

            if (! $pattern = $project->pattern) {
                return ['error' => true, 'message' => 'برای اصلاح آزادی اول باید الگو ساخته شود.', 'goto' => 'pattern'];
            }

            $key = $data['action_key'];
            $ease = $pattern->ease ?? [];
            $current = (float) ($ease[$key] ?? 0);
            $ease[$key] = round($current + (float) $data['action_value'], 1);
            $pattern->update(['ease' => $ease]);

            $project->update(['ease' => array_merge($project->ease ?? [], [$key => $ease[$key]])]);

            $this->rerun($project);

            return [
                'stay' => true,
                'message' => 'آزادی ناحیه‌ی «'.$key.'» به '.$ease[$key].' سانتی‌متر رسید و تناسب دوباره حساب شد.',
            ];
        }

        if (! $project->simulations()->whereNotNull('fit_score')->exists()) {
            $this->rerun($project);
        }

        return ['message' => 'تست تناسب تأیید شد.'];
    }

    /*
     * ------------------------------------------------------------------
     * کمک‌کننده‌ها
     * ------------------------------------------------------------------
     */

    /** مرحله‌ی پروژه را جلو می‌برد و شماره‌ی مرحله‌ی بعد را برمی‌گرداند. */
    protected function advance(Project $project, int $current): int
    {
        $project->refresh();
        $target = $project->nextStep();

        if ($target <= $current) {
            $target = min(count(Project::STEPS), $current + 1);
        }

        $project->update(['step' => $target]);

        return $target;
    }

    /** تحلیل تناسب تازه؛ اگر الگو نباشد آرایه‌ی خالی برمی‌گردد. */
    protected function analysis(Project $project, string $pose = 'stand'): array
    {
        if (! $project->pattern) {
            return ['zones' => [], 'warnings' => [], 'suggestions' => [], 'fit_score' => null, 'params' => [], 'pose' => $pose];
        }

        try {
            $analysis = app(FitAnalysisService::class)->analyze(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
                $pose,
            );
        } catch (\Throwable $e) {
            report($e);

            return ['zones' => [], 'warnings' => [], 'suggestions' => [], 'fit_score' => null, 'params' => [], 'pose' => $pose];
        }

        return $analysis + ['pose' => $pose];
    }

    /** بسته‌ی نمای سه‌بعدی. */
    protected function preview(Project $project, array $analysis): ?array
    {
        if (! $project->pattern) {
            return null;
        }

        try {
            return app(ClothPreviewService::class)->payload(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
                $analysis,
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** شبیه‌سازی تازه ذخیره می‌کند (پس از اعمال پیشنهاد). */
    protected function rerun(Project $project): void
    {
        if (! $project->pattern) {
            return;
        }

        $pose = $project->settings['pose'] ?? 'stand';
        $analysis = $this->analysis($project->refresh(), $pose);

        if ($analysis['zones'] === []) {
            return;
        }

        $project->simulations()->create([
            'pattern_id' => $project->pattern_id,
            'fabric_id' => $project->fabric_id,
            'measurement_set_id' => $project->measurement_set_id,
            'pose' => $pose,
            'params' => $analysis['params'],
            'zones' => $analysis['zones'],
            'warnings' => $analysis['warnings'],
            'suggestions' => $analysis['suggestions'],
            'fit_score' => $analysis['fit_score'],
        ]);

        $this->scorecard($project);
    }

    /** کارنامه‌ی کیفیت با تحمل خطا. */
    protected function scorecard(Project $project): ?array
    {
        try {
            return app(FitAnalysisService::class)->scorecard($project);
        } catch (\Throwable) {
            return $project->scorecard;
        }
    }

    /** نمره‌ی سازگاری هر پارچه با نوع لباس پروژه. */
    protected function compatibilityScores($fabrics, Project $project): array
    {
        $service = '\App\Services\Fabric\FabricCompatibilityService';

        if (! $project->garmentType || ! class_exists($service)) {
            return [];
        }

        $scores = [];

        foreach ($fabrics as $fabric) {
            try {
                $scores[$fabric->id] = app($service)->score($fabric, $project->garmentType);
            } catch (\Throwable) {
                // این پارچه بدون نمره نمایش داده می‌شود.
            }
        }

        return $scores;
    }

    /** یافته‌های موتور قواعد؛ در صورت نبود ماژول، فهرست خالی. */
    protected function ruleFindings(Project $project): array
    {
        $service = '\App\Services\Rules\RuleEngine';

        if (! class_exists($service)) {
            return [];
        }

        try {
            $findings = app($service)->forProject($project);
        } catch (\Throwable) {
            return [];
        }

        return is_array($findings) ? array_values($findings) : [];
    }

    /** درزهای ذخیره‌شده‌ی الگو. */
    protected function relations(?Pattern $pattern): array
    {
        return array_values(array_filter((array) ($pattern?->sewing_relations ?? []), 'is_array'));
    }

    /** درزهای پیشنهادی ماژول الگو. */
    protected function suggestedRelations(?Pattern $pattern): array
    {
        $service = '\App\Services\Pattern\SewingRelationBuilder';

        if (! $pattern || ! class_exists($service)) {
            return [];
        }

        try {
            $suggested = app($service)->suggest($pattern);
        } catch (\Throwable) {
            return [];
        }

        return is_array($suggested) ? array_values(array_filter($suggested, 'is_array')) : [];
    }

    /** درزهای پیشنهادی را روی الگوی تازه‌ساخته ذخیره می‌کند. */
    protected function seedSewingRelations(Pattern $pattern): void
    {
        if (! empty($pattern->sewing_relations)) {
            return;
        }

        $suggested = $this->suggestedRelations($pattern);

        if ($suggested !== []) {
            $pattern->update(['sewing_relations' => $suggested]);
        }
    }

    /** گزینه‌های «لبه‌ی قطعه» برای ساختن درز. */
    protected function edgeOptions($pieces): array
    {
        $options = [];

        foreach ($pieces as $piece) {
            $count = max(1, count($piece->points()));

            for ($edge = 0; $edge < min($count, 12); $edge++) {
                $options[$piece->code.':'.$edge] = $piece->name.' — لبه '.\App\Support\Jalali::digits($edge + 1);
            }
        }

        return $options;
    }

    /** «کد:شماره لبه» را جدا می‌کند. */
    protected function parseEdge(string $value): array
    {
        $parts = explode(':', $value, 2);

        return [$parts[0], (int) ($parts[1] ?? 0)];
    }

    /** پیش‌نمای SVG الگو؛ در نبود ماژول الگو یک نمای ساده‌ی جانشین. */
    protected function patternSvg(?Pattern $pattern): ?string
    {
        if (! $pattern) {
            return null;
        }

        $renderer = '\App\Services\Pattern\SvgRenderer';

        if (class_exists($renderer)) {
            try {
                $svg = app($renderer)->renderPattern($pattern);

                if (is_string($svg) && trim($svg) !== '') {
                    return $svg;
                }
            } catch (\Throwable) {
                // به نمای جانشین برمی‌گردیم.
            }
        }

        return $this->fallbackSvg($pattern);
    }

    /** نمای ساده‌ی قطعه‌ها، فقط برای اینکه صفحه هرگز خالی نماند. */
    protected function fallbackSvg(Pattern $pattern): ?string
    {
        $pieces = $pattern->relationLoaded('pieces') ? $pattern->pieces : $pattern->pieces()->get();

        if ($pieces->isEmpty()) {
            return null;
        }

        $offset = 0;
        $height = 0;
        $shapes = '';

        foreach ($pieces as $piece) {
            $points = $piece->points();

            if (count($points) < 3) {
                continue;
            }

            [$minX, $minY] = $piece->bounds();
            $coords = [];

            foreach ($points as $point) {
                $coords[] = round($point['x'] - $minX + $offset, 1).','.round($point['y'] - $minY, 1);
            }

            $shapes .= '<polygon points="'.implode(' ', $coords).'" fill="#fdf8f3" stroke="#78716c" stroke-width="0.5" />';
            $shapes .= '<text x="'.round($offset + 2, 1).'" y="6" font-size="4" fill="#57534e">'.e($piece->name).'</text>';

            $offset += $piece->width() + 6;
            $height = max($height, $piece->height());
        }

        if ($shapes === '') {
            return null;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 '.round($offset + 8, 1).' '.round($height + 8, 1).'"'
            .' class="h-auto w-full">'.$shapes.'</svg>';
    }

    /** آزادی‌های دستی: فقط عددهای معنادار. */
    protected function cleanEase(array $ease): ?array
    {
        $out = [];

        foreach ($ease as $key => $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $out[(string) $key] = round((float) $value, 1);
            }
        }

        return $out === [] ? null : $out;
    }

    /** نام فارسی فیلدهای اندازه برای پیام‌های خطا. */
    protected function measurementNames(): array
    {
        $names = ['customer_name' => 'نام مشتری'];

        foreach (Measurements::FIELDS as $key => $field) {
            $names[$key] = $field['label'];
        }

        return $names;
    }
}
