<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Services\Pattern\GarmentFlatService;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\Style\StyleRegistry;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * کارگاه دوخت: ساخت یک لباس از یک «پایه» و هر تعداد «سبک».
 *
 * صفحه سه گام دارد — پایه، سبک‌ها، اندازه — و همه چیز از رجیستری مدل‌ها و سبک‌ها
 * خوانده می‌شود، پس هر مدل یا سبک تازه‌ای بدون تغییر این فایل در صفحه پیدا می‌شود.
 * پیش‌نمایش زنده با همان سرویس ترکیب ساخته می‌شود و خروجی یک الگوی معمولی است،
 * پس ویرایشگر و چاپ و خروجی موجود بدون تغییر رویش کار می‌کنند.
 */
class PatternComposerController extends Controller
{
    /** بندانگشتی‌ها فقط به کد مدل بستگی دارند، پس یک روز کش می‌شوند. */
    protected const THUMBNAIL_VERSION = 'v2';

    /** چند کارت در هر بستهٔ انتخابگر. */
    protected const PICKER_PAGE = 12;

    /** نشانِ «تصویری نیست» — همان اندازهٔ بندانگشتی‌های واقعی تا چیدمان نپرد. */
    protected const MISSING_THUMBNAIL = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 160" '
        .'width="220" height="160" role="img" aria-label="بدون تصویر"><rect width="220" height="160" fill="#fafaf9"/>'
        .'<path d="M96 72h28v4H96zM110 60v28" stroke="#d6d3d1" stroke-width="4" fill="none"/></svg>';

    /** آیا صفحه از روی دستور یک الگوی ساخته‌شده باز شده است؟ */
    protected bool $reopened = false;

    public function __construct(
        protected PatternComposer $composer,
        protected SvgRenderer $renderer,
        protected GarmentFlatService $flats = new GarmentFlatService,
    ) {}

    public function create(Request $request): View
    {
        $recipe = $this->recipeFrom($request, fallback: true);
        $catalogue = $this->catalogue();
        $initial = $this->initialPreview($recipe);

        return view('patterns.compose', [
            'catalogue' => $catalogue,
            'recipe' => $recipe,
            'initial' => $initial,
            'availability' => $initial['availability'],
            'customers' => Customer::query()->with('defaultMeasurementSet')->orderBy('name')->get(),
            'sizes' => Measurements::sizes(),
            'seamTags' => SeamAllowanceService::TAGS,
            'defaultSeamAllowances' => $this->workshopSeamAllowances(),
            'previewUrl' => route('patterns.compose.preview'),
            'modelsUrl' => route('patterns.compose.models'),
            'picker' => $this->pickerLists($recipe),
            'reopened' => $this->reopened,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        [$measurements, $measurementSetId, $size] = $this->resolveMeasurements($data);

        try {
            $pattern = $this->composer->composeIntoPattern($this->recipeFrom($request), [
                'measurements' => $measurements,
                'measurement_set_id' => $measurementSetId,
                'base_size' => $size,
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'ease' => $this->easeFrom($data),
                'params' => $this->paramsFrom($data),
                'seam_allowances' => $this->cleanNumbers($data['seam_allowances'] ?? []) ?: $this->workshopSeamAllowances(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        // به کارگاه برمی‌گردیم تا گزارش جورکردن‌ها را ببیند و بتواند همان‌جا
        // چیزی را عوض کند و دوباره بسازد؛ نشانی خود الگو هم در همان گزارش هست.
        return redirect()
            ->route('patterns.compose', ['pattern' => $pattern->id])
            ->with('status', 'لباس ترکیبی ساخته شد: '.$pattern->pieces->count().' قطعه آماده است.')
            ->with('composed', [
                'id' => $pattern->id,
                'name' => $pattern->name,
                'pieces' => $pattern->pieces->count(),
                'cut_pieces' => (int) $pattern->pieces->sum('cut_quantity'),
                'url' => route('patterns.show', $pattern),
                'notes' => $pattern->params['compose']['notes'] ?? [],
            ]);
    }

    /** پیش‌نمایش زنده: با هر تغییر انتخاب‌ها از صفحه صدا زده می‌شود. */
    public function preview(Request $request): JsonResponse
    {
        $data = $this->validated($request, forPreview: true);
        [$measurements] = $this->resolveMeasurements($data);
        $recipe = $this->recipeFrom($request);

        try {
            $result = $this->composer->compose(
                $recipe,
                $measurements,
                $this->easeFrom($data),
                $this->paramsFrom($data),
                $this->cleanNumbers($data['seam_allowances'] ?? []) ?: $this->workshopSeamAllowances(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'schemas' => $this->schemasFor($recipe),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'name' => $result['name'],
            'notes' => $result['notes'],
            'metrics' => $result['metrics'],
            'recipe' => $result['recipe'],
            'styles' => $result['metrics']['styles'] ?? [],
            'schemas' => $this->schemasFor($recipe),
            'availability' => $this->composer->styleAvailability($result['pieces'], [
                'measurements' => $result['measurements'],
                'garment' => $result['recipe']['base'],
            ]),
            'pieces' => collect($result['pieces'])->map(fn (array $piece) => [
                'code' => $piece['code'],
                'name' => $piece['name'],
                'cut_quantity' => $piece['cut_quantity'],
                'on_fold' => (bool) $piece['on_fold'],
                'group' => $piece['meta']['group'] ?? null,
            ])->values()->all(),
            'relations' => count($result['sewing_relations']),
            'svg' => $this->renderer->renderPieces(PatternComposer::toModels($result['pieces']), [
                'width' => 900,
                'labels' => true,
                'seam_allowance' => false,
                'gap' => 5,
            ]),
            'flats' => $this->garmentFlats($result['pieces'], $result['measurements'] ?? $measurements),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * چهار نمای لباسِ دوخته‌شده، با تور ایمنی.
     *
     * پیش‌نمایشِ کارگاه نباید سرِ این بشکند: اگر ترکیبی قطعه‌بندیِ نامنتظری
     * داشته باشد، نما نمی‌آید ولی نقشه و بقیهٔ گزارش سر جایشان می‌مانند.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, float|int>  $measurements
     * @return array{views: array<string, string>, measures: array<string, float>, notes: array<int, string>, ok: bool}
     */
    protected function garmentFlats(array $pieces, array $measurements): array
    {
        try {
            return $this->flats->flats($pieces, $measurements, ['width' => 200]);
        } catch (Throwable $error) {
            report($error);

            return [
                'views' => [],
                'measures' => [],
                'notes' => ['نمای دوختِ این ترکیب ساخته نشد: '.$error->getMessage()],
                'ok' => false,
            ];
        }
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $forPreview = false): array
    {
        return $request->validate([
            'kind' => ['nullable', Rule::in(['garment', 'blocks'])],
            'garment' => [
                Rule::requiredIf(fn () => ! $forPreview && $request->string('kind')->toString() === 'garment'),
                'nullable', 'string', 'max:48',
            ],
            'bodice' => [
                Rule::requiredIf(fn () => ! $forPreview && $request->string('kind')->toString() !== 'garment'),
                'nullable', 'string', 'max:48',
            ],
            'sleeve' => ['nullable', 'string', 'max:48'],
            'lower' => ['nullable', 'string', 'max:48'],
            'skirt' => ['nullable', 'string', 'max:48'],
            'pants' => ['nullable', 'string', 'max:48'],
            'collar' => ['nullable', 'string', 'max:48'],
            'styles' => ['nullable', 'array', 'max:40'],
            'styles.*.key' => ['nullable', 'string', 'max:48'],
            'styles.*.side' => ['nullable', 'string', 'in:both,left,right'],
            'styles.*.params' => ['nullable', 'array'],
            'styles.*.params.*' => ['nullable', 'string', 'max:64'],
            'customer_id' => ['nullable', 'integer'],
            'measurement_set_id' => ['nullable', 'integer'],
            'pattern' => ['nullable', 'integer'],
            'base_size' => ['nullable', Rule::in(Measurements::sizes())],
            'name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'gather' => ['nullable', 'numeric', 'between:0,40'],
            'waist_join' => ['nullable', Rule::in(['auto', 'gather', 'true_side_seams'])],
            'ease' => ['nullable', 'array'],
            'ease.*' => ['nullable', 'numeric', 'between:-20,50'],
            'seam_allowances' => ['nullable', 'array'],
            'seam_allowances.*' => ['nullable', 'numeric', 'between:0,10'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'array'],
        ], [], [
            'bodice' => 'بالاتنه',
            'garment' => 'لباس',
            'base_size' => 'سایز',
            'name' => 'نام الگو',
        ]);
    }

    /**
     * دستور ترکیب از درخواست: پایه به‌علاوه فهرست سبک‌ها.
     *
     * اگر نشانی «?pattern=» داشته باشد، دستور همان الگو دوباره باز می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function recipeFrom(Request $request, bool $fallback = false): array
    {
        $kind = $request->string('kind')->toString();
        $garment = $request->string('garment')->toString();

        $recipe = [
            'kind' => $kind ?: ($garment !== '' ? 'garment' : 'blocks'),
            'garment' => $garment ?: null,
            'bodice' => $request->string('bodice')->toString() ?: null,
            'sleeve' => $request->string('sleeve')->toString() ?: null,
            'lower' => $request->string('lower')->toString() ?: null,
            'skirt' => $request->string('skirt')->toString() ?: null,
            'pants' => $request->string('pants')->toString() ?: null,
            'collar' => $request->string('collar')->toString() ?: null,
            'styles' => $this->stylesFrom($request),
        ];

        if (! $fallback) {
            return $recipe;
        }

        if ($request->integer('pattern') > 0) {
            $stored = $this->storedRecipe($request->integer('pattern'));

            if ($stored !== null) {
                $this->reopened = true;

                return $stored;
            }
        }

        if ($recipe['kind'] === 'blocks' && $recipe['bodice'] === null) {
            $recipe['bodice'] = 'bodice_block';
            $recipe['sleeve'] ??= 'sleeve';
            $recipe['lower'] ??= 'skirt_a_line';
            $recipe['collar'] ??= 'none';
        }

        return $recipe;
    }

    /** دستور ذخیره‌شده یک الگوی ترکیبی، به همان شکلی که صفحه می‌خواهد. */
    protected function storedRecipe(int $id): ?array
    {
        $pattern = Pattern::query()->find($id);

        if ($pattern === null) {
            return null;
        }

        $recipe = $this->composer->recipeOf($pattern);

        return array_merge($recipe['base'], [
            'styles' => array_map(
                fn (array $style) => [
                    'key' => $style['key'],
                    'side' => $style['side'] ?? 'both',
                    'params' => $style['params'],
                ],
                $recipe['styles'],
            ),
        ]);
    }

    /**
     * فهرست سبک‌های انتخاب‌شده با پارامترهایشان.
     *
     * @return array<int, array{key: string, params: array<string, mixed>}>
     */
    protected function stylesFrom(Request $request): array
    {
        $styles = [];

        foreach ((array) $request->input('styles', []) as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $params = is_array($row) && is_array($row['params'] ?? null) ? $row['params'] : [];

            $styles[] = [
                'key' => $key,
                // سمت لباس برای سبک‌های یک‌طرفه (جیب فقط سمت چپ، لتِ یک‌طرفه…)
                'side' => is_array($row) ? (string) ($row['side'] ?? 'both') : 'both',
                'params' => collect($params)
                    ->reject(fn ($value) => $value === null || $value === '' || is_array($value))
                    ->map(fn ($value) => is_numeric($value) ? (float) $value : $value)
                    ->all(),
            ];
        }

        return $styles;
    }

    /** آزادی لباس؛ «چین کمر» روی آزادی کمر پایین‌تنه سوار می‌شود. */
    protected function easeFrom(array $data): array
    {
        $ease = $this->cleanNumbers($data['ease'] ?? []);
        $gather = (float) ($data['gather'] ?? 0);

        if ($gather > 0) {
            $ease['lower'] = ['waist' => (float) ($ease['waist'] ?? 0) + $gather];
        }

        return $ease;
    }

    /** پارامترهای هر بخش به‌علاوه روش جورکردن کمر. */
    protected function paramsFrom(array $data): array
    {
        $params = [];

        foreach ($data['params'] ?? [] as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            $params[$group] = collect($values)
                ->reject(fn ($value) => $value === null || $value === '' || is_array($value))
                ->map(fn ($value) => is_numeric($value) ? (float) $value : $value)
                ->all();
        }

        if (! empty($data['waist_join'])) {
            $params['waist_join'] = $data['waist_join'];
        }

        return $params;
    }

    /**
     * اندازه‌ها از مشتری، دفترچه اندازه یا جدول سایز.
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

    /* --- فهرست‌های صفحه ---------------------------------------------------- */

    /**
     * فهرست پایه‌ها و سبک‌ها با بندانگشتی هر پایه.
     *
     * مدلی که خودش خطا می‌دهد از فهرست حذف نمی‌شود؛ فقط بی‌بندانگشتی و
     * «فعلاً در دسترس نیست» نشان داده می‌شود تا یک مدل خرابِ تازه کل صفحه را
     * از کار نیندازد.
     *
     * @return array<string, mixed>
     */
    /**
     * فهرست سبک‌های کارگاه — بی هیچ بندانگشتی، حتی بی نشانیِ بندانگشتی.
     *
     * پیش‌تر این‌جا برای هر مدل یک بندانگشتی ساخته می‌شد. با چند ده مدل کُند بود
     * و با هزاران مدل ناممکن: باز کردن صفحه یعنی ساختنِ هزاران الگو، و اگر هم
     * همه در کش بودند، صفحه با هزاران نقشهٔ SVG تویش از دستِ مرورگر درمی‌رفت.
     *
     * بعد نشانی‌شان این‌جا ساخته می‌شد؛ آن هم هفده هزار بار صدا زدنِ route() سرِ
     * هر بارِ خالی‌بودنِ کش بود. حالا صفحه یک *قالبِ* نشانی می‌گیرد و کلیدِ هر
     * کارت را خودش تویش می‌گذارد؛ ساختِ الگو همان‌جا که تصویر خواسته شد انجام
     * می‌شود و همان‌طور که بود کش می‌شود.
     *
     * فهرستِ خودِ پایه‌ها هم دیگر این‌جا نیست: صفحه بسته‌بسته می‌گیردش (pickerPage)
     * و این‌جا فقط سبک‌ها می‌ماند، که چند دَه‌تایند نه چند هزارتا.
     */
    protected function catalogue(): array
    {
        /*
         * فهرست فقط به کدِ برنامه بستگی دارد — نه به کاربر، نه به کارگاه، نه به
         * پایگاه داده — پس یک بار ساخته و کش می‌شود. کلیدِ کش از خودِ فهرستِ
         * مدل‌ها و سبک‌ها می‌آید، پس مدلِ تازه‌ای که اضافه شود همان لحظه فهرست را
         * از نو می‌سازد و کسی لازم نیست کش را دستی پاک کند.
         */
        $fingerprint = md5(implode(',', array_keys(GeneratorRegistry::all()))
            .'|'.implode(',', array_keys(StyleRegistry::all())));

        return Cache::remember(
            'compose-catalogue:'.static::THUMBNAIL_VERSION.':'.$fingerprint,
            now()->addDay(),
            fn () => $this->composer->styleCatalogue(),
        );
    }

    /**
     * بندانگشتی یک پایه، جدا از صفحه.
     *
     * مدلی که ساخته نمی‌شود، به‌جای خطا یک نشانِ خالی می‌گیرد؛ صفحه‌ای که صد
     * بندانگشتی می‌خواهد نباید سرِ یکی‌شان بشکند.
     */
    public function thumb(string $group, string $key): Response
    {
        $svg = $this->thumbnail($group, $key) ?? static::MISSING_THUMBNAIL;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * بستهٔ اولِ هر نقش، بعلاوهٔ کارتِ چیزی که همین حالا انتخاب شده.
     *
     * @return array<string, mixed>
     */
    protected function pickerLists(array $recipe): array
    {
        $lists = [];

        foreach (array_keys($this->pickerLabels()) as $group) {
            $lists[$group] = $this->pickerPage($group);
            $lists[$group]['chosen'] = $this->pickerCard($group, $recipe[$group] ?? null);
        }

        return $lists;
    }

    /**
     * نامِ همهٔ انتخاب‌ها، بی توضیح — همان چیزی که برای جستجو و شمارش لازم است.
     *
     * @return array<string, array<string, string>>
     */
    protected function pickerLabels(): array
    {
        $fingerprint = md5(implode(',', array_keys(GeneratorRegistry::all())));

        return Cache::remember(
            'compose-labels:'.$fingerprint,
            now()->addDay(),
            fn () => $this->composer->optionLabels(),
        );
    }

    /**
     * یک بسته از مدل‌های یک نقش: کلید، نام و توضیح.
     *
     * فهرست دیگر یک‌جا به مرورگر نمی‌رود. با هفده هزار مدل، فرستادنِ همه‌اش سه و
     * نیم مگابایت بود و با هر مدلِ تازه بیشتر — و کاربر هر بار چند تایش را
     * می‌بیند. صفحه یک بستهٔ کوچک می‌گیرد و جستجو از سرور می‌آید.
     *
     * جستجو روی نامِ مدل‌هاست، نه توضیحشان: توضیح از داکبلاکِ کلاس با Reflection
     * درمی‌آید و ساختنش برای هفده هزار مدل نزدیک یک ثانیه است. پس فقط برای همان
     * دوازده ردیفی ساخته می‌شود که واقعاً فرستاده می‌شوند.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, more: bool}
     */
    protected function pickerPage(string $group, string $term = '', int $page = 1): array
    {
        $labels = $this->pickerLabels()[$group] ?? [];
        $term = mb_strtolower(trim($term));
        $page = max(1, $page);
        $keys = [];

        foreach ($labels as $key => $label) {
            if ($term === '' || str_contains(mb_strtolower($label.' '.$key), $term)) {
                $keys[] = $key;
            }
        }

        $total = count($keys);
        $rows = [];

        foreach (array_slice($keys, ($page - 1) * static::PICKER_PAGE, static::PICKER_PAGE) as $key) {
            $rows[] = [
                'k' => $key,
                'l' => (string) $labels[$key],
                'h' => $this->composer->optionHint($group, $key),
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'more' => ($page * static::PICKER_PAGE) < $total,
        ];
    }

    /** همان بسته، برای جستجوی زندهٔ صفحه. */
    public function pickerSearch(Request $request): JsonResponse
    {
        $group = (string) $request->query('group', '');

        abort_unless(array_key_exists($group, $this->pickerLabels()), 404);

        return response()->json($this->pickerPage(
            $group,
            (string) $request->query('q', ''),
            $request->integer('page') ?: 1,
        ));
    }

    /** یک کارت با کلید، برای وقتی انتخابِ فعلی در بستهٔ نمایش‌داده‌شده نیست. */
    protected function pickerCard(string $group, ?string $key): ?array
    {
        $label = $key === null ? null : ($this->pickerLabels()[$group][$key] ?? null);

        return $label === null ? null : [
            'k' => $key,
            'l' => (string) $label,
            'h' => $this->composer->optionHint($group, $key),
        ];
    }

    /** بندانگشتی یک پایه (کش می‌شود چون فقط به کد آن بستگی دارد). */
    protected function thumbnail(string $group, string $key): ?string
    {
        return Cache::remember(
            'compose-thumb:'.static::THUMBNAIL_VERSION.":{$group}:{$key}",
            now()->addDay(),
            function () use ($group, $key) {
                try {
                    $pieces = PatternComposer::toModels($this->composer->previewPieces($group, $key));

                    return $this->renderer->renderPieces($pieces, [
                        'width' => 220,
                        'labels' => false,
                        'seam_allowance' => false,
                        'gap' => 3,
                    ]);
                } catch (Throwable $exception) {
                    return null;
                }
            },
        );
    }

    /**
     * توضیح پارامترهای همان چیزی که همین حالا انتخاب شده — نه همه ۴۸ مدل و ۴۳ سبک.
     *
     * فرم «تنظیمات حرفه‌ای» در صفحه از روی همین داده ساخته می‌شود و با هر
     * پیش‌نمایش تازه می‌شود، پس صفحه با بزرگ‌شدن کاتالوگ سنگین نمی‌شود.
     *
     * @return array{roles: array<string, mixed>, styles: array<string, mixed>}
     */
    protected function schemasFor(array $recipe): array
    {
        try {
            $roles = $this->composer->paramsSchema($recipe);
            $styles = [];

            foreach ($this->composer->normalizeRecipe($recipe, validate: false)['styles'] as $entry) {
                if (! StyleRegistry::has($entry['key'])) {
                    continue;
                }

                $style = StyleRegistry::make($entry['key']);

                $styles[$entry['key']] = [
                    'label' => $style->label(),
                    'schema' => $style->paramsSchema(),
                    'defaults' => $this->composer->styleDefaults($style),
                ];
            }

            return ['roles' => $roles, 'styles' => $styles];
        } catch (Throwable $exception) {
            return ['roles' => [], 'styles' => []];
        }
    }

    /**
     * پیش‌نمایش نخست، همان‌جا روی سرور.
     *
     * صفحه با نقشه و یادداشت‌های آماده باز می‌شود (نه خالی) و وضعیت پذیرش سبک‌ها
     * از همین ترکیب درمی‌آید.
     *
     * @return array<string, mixed>
     */
    protected function initialPreview(array $recipe): array
    {
        $measurements = Measurements::fromSize((string) request()->input('base_size', '40'));
        $empty = [
            'svg' => '', 'pieces' => [], 'notes' => [], 'metrics' => [], 'name' => '',
            'flats' => ['views' => [], 'measures' => [], 'notes' => [], 'ok' => false],
            'schemas' => $this->schemasFor($recipe),
            'availability' => $this->composer->styleAvailability([], ['measurements' => $measurements]),
            'error' => null,
        ];

        try {
            $result = $this->composer->compose($recipe, $measurements);

            return [
                'svg' => $this->renderer->renderPieces(PatternComposer::toModels($result['pieces']), [
                    'width' => 900,
                    'labels' => true,
                    'seam_allowance' => false,
                    'gap' => 5,
                ]),
                'pieces' => collect($result['pieces'])->map(fn (array $piece) => [
                    'code' => $piece['code'],
                    'name' => $piece['name'],
                    'cut_quantity' => $piece['cut_quantity'],
                    'on_fold' => (bool) $piece['on_fold'],
                    'group' => $piece['meta']['group'] ?? null,
                ])->values()->all(),
                'notes' => $result['notes'],
                'metrics' => $result['metrics'],
                'name' => $result['name'],
                'flats' => $this->garmentFlats($result['pieces'], $measurements),
                'error' => null,
                'schemas' => $this->schemasFor($recipe),
                'availability' => $this->composer->styleAvailability($result['pieces'], [
                    'measurements' => $measurements,
                    'garment' => $result['recipe']['base'],
                ]),
            ];
        } catch (Throwable $exception) {
            // صفحه باید باز شود حتی اگر همین ترکیب ساخته نشود؛ پیش‌نمایش زنده
            // دوباره تلاش می‌کند و دلیل را نشان می‌دهد.
            return array_merge($empty, [
                'error' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : null,
            ]);
        }
    }

    /** @return array<string, float> */
    protected function cleanNumbers(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $out[$key] = round((float) $value, 2);
        }

        return $out;
    }

    /** @return array<string, float> */
    protected function workshopSeamAllowances(): array
    {
        return app(WorkshopContext::class)->get()?->defaultSeamAllowances() ?? PatternBuilder::DEFAULT_SEAM_ALLOWANCES;
    }
}
