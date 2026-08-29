<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\PatternTemplate;
use App\Services\Pattern\GarmentFlatService;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\GradingService;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternInspector;
use App\Services\Pattern\PatternVersionService;
use App\Services\Pattern\PieceSplitter;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SewingRelationBuilder;
use App\Services\Pattern\SvgRenderer;
use App\Services\Simulation\DrapePayloadService;
use App\Support\Measurements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * الگوها.
 *
 * مسیر ساده ساخت الگو فقط دو انتخاب دارد: «کدام مدل» و «برای چه کسی». باقی
 * تنظیم‌ها (آزادی، جای دوخت و پارامترهای مدل) پیش‌فرض دارند و در بخش پیشرفته پنهانند.
 */
class PatternController extends Controller
{
    /** نشانِ «تصویری نیست» — هم‌اندازهٔ پیش‌نمایش‌های واقعی تا چیدمان نپرد. */
    protected const MISSING_PREVIEW = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 200" '
        .'width="260" height="200" role="img" aria-label="بدون تصویر"><rect width="260" height="200" fill="#fafaf9"/>'
        .'<path d="M116 96h28v4h-28zM130 84v28" stroke="#d6d3d1" stroke-width="4" fill="none"/></svg>';

    public function __construct(
        protected PatternBuilder $builder,
        protected SvgRenderer $renderer,
        protected SeamAllowanceService $seams,
        protected GradingService $grading,
        protected PatternVersionService $versions,
        protected PatternInspector $inspector = new PatternInspector,
        protected PieceSplitter $splitter = new PieceSplitter,
        protected GarmentFlatService $flats = new GarmentFlatService,
    ) {}

    public function index(Request $request): View
    {
        $patterns = Pattern::query()
            ->with(['garmentType', 'pieces'])
            ->search($request->query('q'))
            ->when($request->filled('garment_type'), fn ($query) => $query
                ->where('garment_type_id', $request->integer('garment_type')))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('patterns.index', [
            'patterns' => $patterns,
            'renderer' => $this->renderer,
            'garmentTypes' => GarmentType::active()->orderBy('sort')->pluck('name_fa', 'id'),
            'term' => (string) $request->query('q'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('patterns.create', [
            'templateCards' => $this->templateCards(),
            'selectedCard' => $this->templateCard($request->integer('template') ?: null),
            'templatePreviewUrl' => route('patterns.templates.preview', ['template' => '__ID__']),
            'templateParamsUrl' => route('patterns.templates.params', ['template' => '__ID__']),
            'templateFlatsUrl' => route('patterns.templates.flats', ['template' => '__ID__']),
            'templateSearchUrl' => route('patterns.templates.search'),
            'customers' => Customer::query()->with('defaultMeasurementSet')->orderBy('name')->get(),
            'sizes' => Measurements::sizes(),
            'selectedTemplate' => $request->integer('template') ?: null,
            'defaultSeamAllowances' => $this->workshopSeamAllowances(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pattern_template_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'measurement_set_id' => ['nullable', 'integer'],
            'base_size' => ['nullable', Rule::in(Measurements::sizes())],
            'name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ease' => ['nullable', 'array'],
            'ease.*' => ['nullable', 'numeric', 'between:-20,50'],
            'seam_allowances' => ['nullable', 'array'],
            'seam_allowances.*' => ['nullable', 'numeric', 'between:0,10'],
            'params' => ['nullable', 'array'],
        ], [], [
            'pattern_template_id' => 'مدل الگو',
            'base_size' => 'سایز',
            'name' => 'نام الگو',
        ]);

        $data = array_merge([
            'customer_id' => null,
            'measurement_set_id' => null,
            'base_size' => null,
            'name' => null,
            'notes' => null,
        ], $data);

        $template = $this->availableTemplates()->findOrFail($data['pattern_template_id']);
        [$measurements, $measurementSetId, $size] = $this->resolveMeasurements($data);

        $pattern = $this->builder->createPattern($template, [
            'name' => $data['name'] ?: $template->name_fa,
            'base_size' => $size,
            'measurement_set_id' => $measurementSetId,
            'notes' => $data['notes'] ?? null,
        ], [
            'measurements' => $measurements,
            'ease' => $this->cleanNumbers($data['ease'] ?? []) ?: ($template->garmentType?->ease() ?? []),
            'params' => $data['params'] ?? [],
            'seam_allowances' => $this->cleanNumbers($data['seam_allowances'] ?? []) ?: $this->workshopSeamAllowances(),
        ]);

        return redirect()
            ->route('patterns.show', $pattern)
            ->with('status', 'الگو ساخته شد: '.$pattern->pieces->count().' قطعه آماده است.');
    }

    public function show(Pattern $pattern): View
    {
        $pattern->load(['pieces', 'garmentType', 'template', 'measurementSet.customer']);

        return view('patterns.show', [
            'pattern' => $pattern,
            'svg' => $this->renderer->renderPattern($pattern, [
                'scale' => 4,
                'seam_allowance' => true,
                'labels' => true,
            ]),
            'seamSummary' => $this->seams->summary($pattern),
            'inspection' => $this->inspector->inspect($pattern),
            'relations' => $pattern->sewing_relations ?: SewingRelationBuilder::suggest($pattern),
            'versionCount' => $pattern->versions()->count(),
            'sizes' => Measurements::sizes(),
            // شکلِ لباس روی همین اندازه‌ها؛ اگر ساختنش بگیرد، صفحه نباید بشکند
            'flats' => $this->garmentFlats($pattern),
            'solid' => $this->garmentSolid($pattern),
        ]);
    }

    /**
     * چهار نمای لباسِ دوخته‌شده، با تور ایمنی.
     *
     * این بخش تزیینیِ صفحه است، نه کارِ اصلی‌اش. اگر مدلی قطعه‌بندیِ نامنتظری
     * داشته باشد و نما ساخته نشود، صفحهٔ الگو نباید بشکند — پیغامش را نشان
     * می‌دهد و بقیهٔ صفحه سر جایش می‌ماند.
     *
     * @return array{views: array<string, string>, measures: array<string, float>, notes: array<int, string>, ok: bool}
     */
    protected function garmentFlats(Pattern $pattern): array
    {
        try {
            return $this->flats->flats(
                $pattern->pieces,
                Measurements::complete($pattern->measurements ?? []),
            );
        } catch (Throwable $error) {
            report($error);

            return [
                'views' => [],
                'measures' => [],
                'notes' => ['نمای دوختِ این مدل ساخته نشد: '.$error->getMessage()],
                'ok' => false,
            ];
        }
    }

    /**
     * همان لباس، این بار روی مانکن — با تور ایمنی.
     *
     * پوسته از همان اعدادِ نمای دوبعدی می‌آید، پس اگر آن ساخته شده باشد این هم
     * می‌شود. رنگ و جنس از پارچهٔ الگو، و اگر پارچه‌ای انتخاب نشده باشد از یک
     * خاکیِ ملایم.
     *
     * @return array<string, mixed>
     */
    protected function garmentSolid(Pattern $pattern): array
    {
        try {
            $shell = $this->flats->shell(
                $pattern->pieces,
                Measurements::complete($pattern->measurements ?? []),
            );

            /*
             * الگو به پارچه گره نخورده — یک الگو با هر پارچه‌ای دوخته می‌شود.
             * پس پارچه‌های کارگاه را می‌فرستیم و خودِ صفحه عوض می‌کند؛ رنگ و
             * براقی و شفافیت از پروفایل همان پارچه می‌آید، نه از حدس.
             */
            $shell['fabric'] = ['color' => '#b9a48c', 'sheen' => 0.15, 'transparency' => 0.0];
            $shell['fabrics'] = $this->fabricSwatches();

            /*
             * و بستهٔ خودِ قطعه‌ها، تا مرورگر لباس را *واقعاً* بدوزد.
             *
             * پوستهٔ بالا از چرخاندنِ نیم‌رخِ الگو می‌آید و یک جا را هیچ‌وقت درست
             * نشان نمی‌دهد: سرشانه. پهنای الگو روی خط سرشانه پهنای حلقهٔ آستین
             * است نه پهنای تنه، و چرخاندنش یک تختهٔ پهن می‌سازد که دست را هم
             * می‌بلعد. دوختِ واقعی این را ندارد، چون درزِ سرشانه و حلقهٔ آستین را
             * از خودِ قطعه‌ها می‌گیرد.
             *
             * جدا از پوسته می‌آید و جدا هم شکست می‌خورد: اگر ساخته نشود، همان
             * نمای چرخشی می‌ماند و صفحه سفید نمی‌شود.
             */
            $shell['drape'] = $this->drapePackage($pattern);

            return $shell;
        } catch (Throwable $error) {
            report($error);

            return ['ok' => false, 'notes' => ['نمای مانکن ساخته نشد: '.$error->getMessage()]];
        }
    }

    /**
     * قطعه‌های الگو، آمادهٔ دوخت در مرورگر.
     *
     * برشی از همان چیزی است که «دوخت مجازی» می‌گیرد. اگر ساخته نشود null
     * برمی‌گردد و نما بی سروصدا روی نمای چرخشی می‌ماند — این یکی تزئین است، نه
     * ستون.
     *
     * @return array<string, mixed>|null
     */
    protected function drapePackage(Pattern $pattern): ?array
    {
        try {
            $package = app(DrapePayloadService::class)->payload($pattern);

            return ($package['pieces'] ?? []) === [] ? null : $package;
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    /**
     * پارچه‌های کارگاه، فقط با آنچه برای دیدنِ لباس لازم است.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fabricSwatches(): array
    {
        return Fabric::query()
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->map(function (Fabric $fabric): array {
                $profile = $fabric->profile();
                $hex = trim((string) $fabric->color_hex);

                return [
                    'id' => $fabric->id,
                    'name' => $fabric->displayName(),
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#b9a48c',
                    'sheen' => round((float) $profile->get('sheen'), 3),
                    'transparency' => round((float) $profile->get('transparency'), 3),
                ];
            })
            ->values()
            ->all();
    }

    public function edit(Pattern $pattern): View
    {
        $pattern->load(['pieces', 'template']);

        return view('patterns.edit', [
            'pattern' => $pattern,
            'sizes' => Measurements::sizes(),
            'schema' => $pattern->template?->params_schema ?? [],
            'seamTags' => SeamAllowanceService::TAGS,
        ]);
    }

    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'base_size' => ['nullable', Rule::in(Measurements::sizes())],
            'ease' => ['nullable', 'array'],
            'ease.*' => ['nullable', 'numeric', 'between:-20,50'],
            'seam_allowances' => ['nullable', 'array'],
            'seam_allowances.*' => ['nullable', 'numeric', 'between:0,10'],
            'params' => ['nullable', 'array'],
            'regenerate' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'نام الگو',
            'base_size' => 'سایز',
        ]);

        $ease = $this->cleanNumbers($data['ease'] ?? []);
        $seamAllowances = $this->cleanNumbers($data['seam_allowances'] ?? []);
        $params = $data['params'] ?? [];
        $size = $data['base_size'] ?? $pattern->base_size;

        $measurements = $size !== $pattern->base_size && $pattern->measurement_set_id === null
            ? Measurements::fromSize($size)
            : ($pattern->measurements ?? []);

        if ($pattern->template && ($data['regenerate'] ?? true)) {
            $this->builder->regenerate($pattern, [
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'base_size' => $size,
                'measurements' => $measurements,
                'ease' => $ease ?: ($pattern->ease ?? []),
                'params' => $params,
                'seam_allowances' => $seamAllowances ?: ($pattern->seam_allowances ?? []),
                'note' => 'پیش از تغییر تنظیمات الگو',
            ]);

            return redirect()->route('patterns.show', $pattern)
                ->with('status', 'الگو با تنظیم‌های تازه دوباره ساخته شد.');
        }

        $pattern->fill([
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'base_size' => $size,
        ]);

        $pattern->forceFill([
            'ease' => $ease ?: ($pattern->ease ?? []),
            'seam_allowances' => $seamAllowances ?: ($pattern->seam_allowances ?? []),
            'params' => array_merge($pattern->params ?? [], $params),
        ])->save();

        $pattern->load('pieces');
        $this->seams->apply($pattern);

        return redirect()->route('patterns.show', $pattern)->with('status', 'تنظیم‌های الگو ذخیره شد.');
    }

    public function destroy(Pattern $pattern): RedirectResponse
    {
        $pattern->delete();

        return redirect()->route('patterns.index')->with('status', 'الگو حذف شد.');
    }

    /** ویرایشگر دوبعدی الگو. */
    public function editor(Pattern $pattern): View
    {
        $pattern->load('pieces');

        return view('patterns.editor', [
            'pattern' => $pattern,
            'config' => [
                'saveUrl' => route('patterns.geometry', $pattern),
                // شناسه قطعه در سمت جاوااسکریپت جای «__piece__» می‌نشیند
                'splitUrl' => route('patterns.pieces.split', [$pattern, '__piece__']),
                'version' => (int) $pattern->version,
                'defaultAllowance' => (float) ($pattern->seam_allowances['default'] ?? 1),
                'pieces' => $pattern->pieces->map(fn ($piece) => [
                    'id' => $piece->id,
                    'code' => $piece->code,
                    'name' => $piece->name,
                    'cut_quantity' => $piece->cut_quantity,
                    'on_fold' => (bool) $piece->on_fold,
                    'outline' => $piece->outline ?? [],
                    'grainline' => $piece->grainline,
                    'darts' => $piece->darts ?? [],
                    'notches' => $piece->notches ?? [],
                    'markers' => $piece->markers ?? [],
                    'edge_allowances' => $piece->edge_allowances ?? [],
                    'edges' => $this->seams->edgeTags($piece),
                    'fold_edges' => $this->seams->foldEdges($piece),
                ])->values()->all(),
                'tags' => SeamAllowanceService::TAGS,
            ],
        ]);
    }

    /**
     * ذخیره هندسه از ویرایشگر (درخواست JSON).
     *
     * اعتبارسنجی دستی انجام می‌شود تا پاسخ خطا هم JSON باشد؛ سامانه به‌طور کلی
     * خطاها را به شکل صفحه وب برمی‌گرداند و این مسیر با fetch صدا زده می‌شود.
     */
    public function updateGeometry(Request $request, Pattern $pattern): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pieces' => ['required', 'array', 'min:1'],
            'pieces.*.id' => ['required', 'integer'],
            'pieces.*.outline' => ['required', 'array', 'min:3'],
            'pieces.*.outline.*.x' => ['required', 'numeric', 'between:-600,600'],
            'pieces.*.outline.*.y' => ['required', 'numeric', 'between:-600,600'],
            'pieces.*.outline.*.curve' => ['nullable', 'boolean'],
            'pieces.*.outline.*.cx' => ['nullable', 'numeric', 'between:-600,600'],
            'pieces.*.outline.*.cy' => ['nullable', 'numeric', 'between:-600,600'],
            'pieces.*.darts' => ['nullable', 'array'],
            'pieces.*.notches' => ['nullable', 'array'],
            'pieces.*.grainline' => ['nullable', 'array'],
            'pieces.*.edge_allowances' => ['nullable', 'array'],
            'pieces.*.edge_allowances.*' => ['nullable', 'numeric', 'between:0,10'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'مقادیر فرستاده‌شده درست نیست.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        $pattern->load('pieces');
        $pieces = $pattern->pieces->keyBy('id');

        $this->versions->snapshot($pattern, $data['note'] ?? 'پیش از ویرایش هندسه در ویرایشگر', bump: true);

        $saved = 0;

        foreach ($data['pieces'] as $incoming) {
            $piece = $pieces->get((int) $incoming['id']);

            if ($piece === null) {
                continue;
            }

            $piece->update(array_filter([
                'outline' => $this->cleanOutline($incoming['outline']),
                'darts' => $incoming['darts'] ?? null,
                'notches' => $incoming['notches'] ?? null,
                'grainline' => $incoming['grainline'] ?? null,
                'edge_allowances' => isset($incoming['edge_allowances'])
                    ? array_map(fn ($value) => round((float) $value, 2), $incoming['edge_allowances'])
                    : null,
            ], fn ($value) => $value !== null));

            $saved++;
        }

        // ویرایش دستی می‌تواند مسیر قطعه را خراب کند؛ نتیجه بازرسی همان‌جا
        // برمی‌گردد تا کاربر پیش از رفتن سراغ برش بداند چه چیزی به هم ریخته.
        $pattern->load('pieces');

        return response()->json([
            'status' => 'ok',
            'version' => (int) $pattern->fresh()->version,
            'pieces' => $saved,
            'inspection' => $this->inspector->inspect($pattern),
        ]);
    }

    /**
     * برش دلخواه: دو تکه کردن یک قطعه در امتداد خطی که کاربر در ویرایشگر کشیده.
     *
     * پیش از برش نسخه گرفته می‌شود، چون برخلاف سبک‌ها این کار پارامتر ندارد که
     * بعداً عوض شود؛ راه برگشتش همان نسخه است.
     */
    public function splitPiece(Request $request, Pattern $pattern, PatternPiece $piece): JsonResponse
    {
        // مثل updateGeometry، اعتبارسنجی دستی است تا پاسخ همیشه JSON بماند؛ این
        // مسیر از جاوااسکریپت صدا زده می‌شود و پیام خطایش باید در همان صفحه بنشیند.
        $validator = Validator::make($request->all(), [
            'path' => ['required', 'array', 'min:2', 'max:24'],
            'path.*.x' => ['required', 'numeric', 'between:-600,600'],
            'path.*.y' => ['required', 'numeric', 'between:-600,600'],
            'path.*.curve' => ['nullable', 'boolean'],
            'path.*.cx' => ['nullable', 'numeric', 'between:-600,600'],
            'path.*.cy' => ['nullable', 'numeric', 'between:-600,600'],
            'names' => ['nullable', 'array', 'size:2'],
            'names.*' => ['nullable', 'string', 'max:120'],
        ], [], ['path' => 'خط برش', 'names' => 'نام قطعه‌ها']);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'خط برش درست نیست؛ دست‌کم دو نقطه روی لبهٔ قطعه لازم است.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        if ($piece->pattern_id !== $pattern->id) {
            abort(404);
        }

        // اول هندسه ساخته و بررسی می‌شود، بعد نسخه ثبت می‌شود؛ برشی که انجام
        // نشده نباید نسخه‌ای در تاریخچه جا بگذارد.
        try {
            $halves = $this->splitter->prepare($pattern, $piece, $data['path'], [
                'names' => array_values(array_filter($data['names'] ?? [])) ?: null,
            ]);
        } catch (InvalidArgumentException $error) {
            return response()->json([
                'status' => 'error',
                'message' => $error->getMessage(),
            ], 422);
        }

        $this->versions->snapshot($pattern, 'پیش از برش دستی «'.$piece->name.'»', bump: true);

        $halves = $this->splitter->persist($pattern, $piece, $halves);

        $pattern->load('pieces');

        return response()->json([
            'status' => 'ok',
            'version' => (int) $pattern->fresh()->version,
            'pieces' => array_map(fn (PatternPiece $half) => [
                'id' => $half->id,
                'code' => $half->code,
                'name' => $half->name,
            ], $halves),
            'inspection' => $this->inspector->inspect($pattern),
        ]);
    }

    public function duplicate(Pattern $pattern): RedirectResponse
    {
        $copy = $this->builder->duplicate($pattern);

        return redirect()->route('patterns.show', $copy)->with('status', 'رونوشت الگو ساخته شد.');
    }

    /** ساخت الگو در چند سایز دیگر. */
    public function grade(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*' => [Rule::in(Measurements::sizes())],
        ], [], ['sizes' => 'سایزها']);

        $pattern->load('pieces');
        $created = [];

        foreach (array_unique($data['sizes']) as $size) {
            if ((string) $size === (string) $pattern->base_size) {
                continue;
            }

            $created[] = $this->grading->createGradedPattern($pattern, (string) $size)->base_size;
        }

        if ($created === []) {
            return back()->with('error', 'سایز تازه‌ای برای ساخت نبود.');
        }

        return redirect()->route('patterns.index')->with(
            'status',
            'سایزبندی انجام شد: '.count($created).' الگوی تازه در سایزهای '.implode('، ', $created).' ساخته شد.',
        );
    }

    /** انتشار الگو در کتابخانه عمومی (یا لغو انتشار). */
    public function publish(Pattern $pattern): RedirectResponse
    {
        $pattern->update(['is_published' => ! $pattern->is_published]);

        return back()->with('status', $pattern->is_published
            ? 'الگو در کتابخانه منتشر شد.'
            : 'انتشار الگو لغو شد.');
    }

    /** الگوهای پایه در دسترس این کارگاه. */
    /** چند کارت در هر بستهٔ فهرستِ انتخابگر. */
    protected const CARD_PAGE = 24;

    /**
     * یک بسته از فهرستِ مدل‌ها: شناسه، نام و نوعِ لباس.
     *
     * فهرست دیگر یک‌جا به مرورگر نمی‌رود. با هفت هزار مدل، فرستادنِ همه‌اش
     * هشتصد کیلوبایت بود و با هفده هزار مدل دو و نیم مگابایت — و با هر مدلِ
     * تازه بیشتر. حالا صفحه یک بستهٔ کوچک می‌گیرد و جستجو از سرور می‌آید، پس
     * وزنش دیگر به اندازهٔ کاتالوگ بستگی ندارد.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, more: bool}
     */
    protected function templateCards(string $term = '', int $page = 1): array
    {
        $term = trim($term);
        $page = max(1, $page);

        $query = PatternTemplate::query()
            ->availableTo(auth()->user()->workshop_id)
            ->leftJoin('garment_types', 'garment_types.id', '=', 'pattern_templates.garment_type_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('pattern_templates.name_fa', 'like', '%'.$term.'%')
                ->orWhere('pattern_templates.code', 'like', '%'.$term.'%')
                ->orWhere('garment_types.name_fa', 'like', '%'.$term.'%')));

        $total = (clone $query)->count('pattern_templates.id');

        $rows = $query
            ->orderBy('pattern_templates.sort')
            ->orderBy('pattern_templates.id')
            ->forPage($page, static::CARD_PAGE)
            ->get([
                'pattern_templates.id',
                'pattern_templates.name_fa',
                'garment_types.name_fa as garment_name',
            ])
            ->map(fn ($row) => [
                'i' => (int) $row->id,
                'n' => (string) $row->name_fa,
                'g' => (string) ($row->garment_name ?? ''),
            ])->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'more' => ($page * static::CARD_PAGE) < $total,
        ];
    }

    /**
     * همان فهرست، این بار برای جستجوی زندهٔ صفحه.
     *
     * صفحه با هر تایپ این را صدا می‌زند و بستهٔ بعدی را هم از همین‌جا می‌گیرد.
     */
    public function templateSearch(Request $request): JsonResponse
    {
        return response()->json($this->templateCards(
            (string) $request->query('q', ''),
            $request->integer('page') ?: 1,
        ));
    }

    /** یک کارت با شناسه، برای وقتی انتخابِ فعلی در بستهٔ نمایش‌داده‌شده نیست. */
    protected function templateCard(?int $id): ?array
    {
        if (! $id) {
            return null;
        }

        $row = PatternTemplate::query()
            ->availableTo(auth()->user()->workshop_id)
            ->leftJoin('garment_types', 'garment_types.id', '=', 'pattern_templates.garment_type_id')
            ->where('pattern_templates.id', $id)
            ->first([
                'pattern_templates.id',
                'pattern_templates.name_fa',
                'garment_types.name_fa as garment_name',
            ]);

        return $row === null ? null : [
            'i' => (int) $row->id,
            'n' => (string) $row->name_fa,
            'g' => (string) ($row->garment_name ?? ''),
        ];
    }

    protected function availableTemplates()
    {
        return PatternTemplate::query()
            ->withoutPreview()
            ->availableTo(auth()->user()->workshop_id)
            ->with('garmentType')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * تصویرِ پیش‌نمایشِ یک الگوی پایه، جدا از صفحه.
     *
     * فهرستِ الگوها هزاران ردیف دارد و تصویرِ هرکدام چند کیلوبایت SVG است.
     * تا وقتی تصویرها *در خودِ صفحه* بودند، باز کردنِ «الگوی جدید» یعنی خواندنِ
     * ده‌ها مگابایت از پایگاه داده و ساختنِ هزاران قاب در مرورگر. حالا هر تصویر
     * نشانیِ خودش را دارد و مرورگر همان‌هایی را می‌گیرد که به چشم می‌آیند.
     *
     * ردیفی که تصویرش خالی است (مثلاً چون از سرِ سرعت هنگام پرکردنِ کتابخانه
     * ساخته نشده) همان‌جا ساخته و ذخیره می‌شود، پس دفعهٔ بعد آماده است.
     */
    public function templatePreview(PatternTemplate $template, SvgRenderer $renderer): Response
    {
        abort_unless(
            $template->workshop_id === null || $template->workshop_id === auth()->user()->workshop_id,
            404,
        );

        $svg = (string) ($template->preview_svg ?? '');

        if ($svg === '') {
            $svg = $this->buildTemplatePreview($template, $renderer);

            if ($svg !== '') {
                $template->forceFill(['preview_svg' => $svg])->saveQuietly();
            }
        }

        return response($svg !== '' ? $svg : static::MISSING_PREVIEW, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * چهار نمای لباسِ دوخته‌شده برای یک مدل، روی اندازهٔ خواسته‌شده.
     *
     * صفحهٔ «الگوی تازه» پیش از ساختن الگو صدایش می‌زند تا خیاط ببیند این مدل
     * روی *این* اندازه چه شکلی می‌شود، نه روی سایز جدولی. سبک است چون فقط
     * قطعه‌ها ساخته می‌شوند و چیزی ذخیره نمی‌شود.
     */
    public function templateFlats(PatternTemplate $template, Request $request): JsonResponse
    {
        abort_unless(
            $template->workshop_id === null || $template->workshop_id === auth()->user()->workshop_id,
            404,
        );

        [$measurements] = $this->resolveMeasurements([
            'customer_id' => $request->integer('customer_id') ?: null,
            'measurement_set_id' => $request->integer('measurement_set_id') ?: null,
            'base_size' => $request->string('base_size')->toString() ?: null,
        ]);

        try {
            $pieces = $this->builder->buildFromTemplate($template, $measurements);

            return response()->json($this->flats->flats($pieces, $measurements, ['width' => 200]));
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'views' => [],
                'measures' => [],
                'notes' => ['نمای دوختِ این مدل ساخته نشد.'],
                'ok' => false,
            ]);
        }
    }

    /**
     * پارامترهای *همان* مدلی که انتخاب شده.
     *
     * پیش‌تر صفحه برای هر مدلِ کتابخانه یک فرمِ پارامتر می‌ساخت و همه را پنهان
     * نگه می‌داشت. با چهل مدل کار می‌کرد؛ با هزاران مدل یعنی ده‌ها هزار فیلدِ
     * پنهان در یک صفحه. حالا فرم به‌اندازهٔ یک مدل است و با هر انتخاب تازه
     * می‌شود.
     */
    public function templateParams(PatternTemplate $template): JsonResponse
    {
        abort_unless(
            $template->workshop_id === null || $template->workshop_id === auth()->user()->workshop_id,
            404,
        );

        return response()->json([
            'name' => $template->name_fa,
            'description' => (string) $template->description,
            'schema' => $template->params_schema ?: [],
            'defaults' => $template->default_params ?? [],
        ]);
    }

    /** ساختِ تصویرِ یک الگوی پایه؛ مدلی که ساخته نمی‌شود، تصویر هم ندارد. */
    protected function buildTemplatePreview(PatternTemplate $template, SvgRenderer $renderer): string
    {
        try {
            $generator = GeneratorRegistry::make($template->generator);
            $pieces = collect($generator->generate(
                Measurements::fromSize('40'),
                [],
                $template->params(),
            ))->take(3)->map(fn (array $piece) => new PatternPiece($piece));

            return $renderer->renderPieces($pieces, [
                'width' => 260,
                'labels' => false,
                'seam_allowance' => false,
                'gap' => 3,
            ]);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * اندازه‌ها را از مشتری، دفترچه اندازه یا جدول سایز پیدا می‌کند.
     *
     * @return array{0: array<string, float>, 1: int|null, 2: string}
     */
    protected function resolveMeasurements(array $data): array
    {
        $set = null;

        if (! empty($data['measurement_set_id'])) {
            $set = MeasurementSet::find($data['measurement_set_id']);
        } elseif (! empty($data['customer_id'])) {
            $set = Customer::with('defaultMeasurementSet')->find($data['customer_id'])?->defaultMeasurementSet;
        }

        if ($set !== null) {
            $measurements = $set->completed();
            $size = ($data['base_size'] ?? null) ?: ($set->base_size ?: Measurements::guessSize($measurements));

            return [$measurements, $set->id, (string) $size];
        }

        $size = (string) ($data['base_size'] ?? '40');

        return [Measurements::fromSize($size), null, $size];
    }

    /** @return array<string, float> */
    protected function cleanNumbers(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $clean[$key] = round((float) $value, 2);
        }

        return $clean;
    }

    /**
     * نقاط ورودی ویرایشگر را به قرارداد مسیر برمی‌گرداند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function cleanOutline(array $outline): array
    {
        $points = [];

        foreach ($outline as $point) {
            $clean = [
                'x' => round((float) ($point['x'] ?? 0), 2),
                'y' => round((float) ($point['y'] ?? 0), 2),
            ];

            if (! empty($point['curve']) && isset($point['cx'], $point['cy'])) {
                $clean['curve'] = true;
                $clean['cx'] = round((float) $point['cx'], 2);
                $clean['cy'] = round((float) $point['cy'], 2);
            }

            $points[] = $clean;
        }

        return $points;
    }

    /** @return array<string, float> */
    protected function workshopSeamAllowances(): array
    {
        return auth()->user()->workshop?->defaultSeamAllowances() ?? PatternBuilder::DEFAULT_SEAM_ALLOWANCES;
    }
}
