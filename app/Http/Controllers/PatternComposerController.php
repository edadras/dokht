<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * ترکیب مدل‌ها: ساخت یک لباس از بالاتنه، آستین، پایین‌تنه و یقهٔ جدا.
 *
 * صفحه فقط چهار انتخاب تصویری دارد و پیش‌نمایش زنده با همان سرویس ترکیب ساخته
 * می‌شود؛ همه تنظیم‌های حرفه‌ای پشت بخش بازشو پنهان است. خروجی یک الگوی معمولی
 * است، پس ویرایشگر و چاپ و خروجی موجود بدون تغییر رویش کار می‌کنند.
 */
class PatternComposerController extends Controller
{
    public function __construct(
        protected PatternComposer $composer,
        protected SvgRenderer $renderer,
    ) {}

    public function create(Request $request): View
    {
        $selection = $this->selectionFrom($request, fallback: true);

        return view('patterns.compose', [
            'options' => $this->optionsWithThumbnails(),
            'selection' => $selection,
            'schemas' => $this->allSchemas(),
            'customers' => Customer::query()->with('defaultMeasurementSet')->orderBy('name')->get(),
            'sizes' => Measurements::sizes(),
            'seamTags' => SeamAllowanceService::TAGS,
            'defaultSeamAllowances' => $this->workshopSeamAllowances(),
            'previewUrl' => route('patterns.compose.preview'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        [$measurements, $measurementSetId, $size] = $this->resolveMeasurements($data);

        try {
            $pattern = $this->composer->composeIntoPattern($this->selectionFrom($request), [
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

        return redirect()
            ->route('patterns.show', $pattern)
            ->with('status', 'لباس ترکیبی ساخته شد: '.$pattern->pieces->count().' قطعه آماده است.');
    }

    /** پیش‌نمایش زنده: با هر تغییر انتخاب‌ها از صفحه صدا زده می‌شود. */
    public function preview(Request $request): JsonResponse
    {
        $data = $this->validated($request, forPreview: true);
        [$measurements] = $this->resolveMeasurements($data);

        try {
            $result = $this->composer->compose(
                $this->selectionFrom($request),
                $measurements,
                $this->easeFrom($data),
                $this->paramsFrom($data),
                $this->cleanNumbers($data['seam_allowances'] ?? []) ?: $this->workshopSeamAllowances(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'name' => $result['name'],
            'notes' => $result['notes'],
            'metrics' => $result['metrics'],
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
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $forPreview = false): array
    {
        return $request->validate([
            'bodice' => [$forPreview ? 'nullable' : 'required', 'string', 'max:32'],
            'sleeve' => ['nullable', 'string', 'max:32'],
            'lower' => ['nullable', 'string', 'max:32'],
            'skirt' => ['nullable', 'string', 'max:32'],
            'pants' => ['nullable', 'string', 'max:32'],
            'collar' => ['nullable', 'string', 'max:32'],
            'customer_id' => ['nullable', 'integer'],
            'measurement_set_id' => ['nullable', 'integer'],
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
            'base_size' => 'سایز',
            'name' => 'نام الگو',
        ]);
    }

    /** انتخاب چهار بخش از درخواست. */
    protected function selectionFrom(Request $request, bool $fallback = false): array
    {
        $selection = [
            'bodice' => $request->string('bodice')->toString() ?: ($fallback ? 'bodice_block' : null),
            'sleeve' => $request->string('sleeve')->toString() ?: ($fallback ? 'sleeve' : null),
            'lower' => $request->string('lower')->toString() ?: null,
            'skirt' => $request->string('skirt')->toString() ?: null,
            'pants' => $request->string('pants')->toString() ?: null,
            'collar' => $request->string('collar')->toString() ?: ($fallback ? 'none' : null),
        ];

        if ($fallback && $selection['lower'] === null && $selection['skirt'] === null && $selection['pants'] === null) {
            $selection['lower'] = 'skirt_a_line';
        }

        return $selection;
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
                ->reject(fn ($value) => $value === null || $value === '')
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

    /**
     * انتخاب‌ها به‌همراه بندانگشتی هر بلوک.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function optionsWithThumbnails(): array
    {
        $options = $this->composer->options();

        foreach ($options as $group => $items) {
            foreach ($items as $key => $item) {
                $options[$group][$key]['thumbnail'] = $key === 'none' ? null : $this->thumbnail($group, $key);
            }
        }

        return $options;
    }

    /** بندانگشتی یک بلوک (کش می‌شود چون فقط به کد بلوک بستگی دارد). */
    protected function thumbnail(string $group, string $key): string
    {
        return Cache::remember("compose-thumb:v1:{$group}:{$key}", now()->addDay(), function () use ($group, $key) {
            $pieces = PatternComposer::toModels($this->composer->previewPieces($group, $key));

            return $this->renderer->renderPieces($pieces, [
                'width' => 240,
                'labels' => false,
                'seam_allowance' => false,
                'gap' => 3,
            ]);
        });
    }

    /**
     * توضیح پارامتر همه بلوک‌ها (برای بخش «تنظیمات حرفه‌ای»).
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function allSchemas(): array
    {
        $schemas = ['bodice' => [], 'sleeve' => [], 'lower' => [], 'collar' => []];

        foreach ([
            'bodice' => PatternComposer::BODICE_BLOCKS,
            'sleeve' => PatternComposer::SLEEVE_BLOCKS,
            'lower' => array_merge(PatternComposer::SKIRT_BLOCKS, PatternComposer::PANTS_BLOCKS),
        ] as $group => $keys) {
            foreach ($keys as $key) {
                $selection = ['bodice' => 'bodice_block'];
                $selection[$group] = $key;

                $schemas[$group][$key] = $this->composer->paramsSchema($selection)[$group] ?? null;
            }
        }

        foreach (PatternComposer::COLLAR_STYLES as $style) {
            $schemas['collar'][$style] = [
                'key' => $style,
                'label' => PatternComposer::COLLAR_LABELS[$style],
                'schema' => $this->composer->collarSchema($style),
                'defaults' => PatternComposer::COLLAR_DEFAULTS[$style],
            ];
        }

        return $schemas;
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
