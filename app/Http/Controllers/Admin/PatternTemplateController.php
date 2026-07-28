<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\PatternTemplate;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\SvgRenderer;
use App\Support\Jalali;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * مدیریت الگوهای پایه سراسری.
 *
 * هر الگوی پایه یک تولیدکننده هندسی دارد و پارامترهایش به صورت داده ذخیره می‌شود؛
 * دکمه «پیش‌نمایش» همان تولیدکننده را با سایز استاندارد اجرا می‌کند و تصویر حاصل را
 * برای نمایش در کتابخانه نگه می‌دارد.
 */
class PatternTemplateController extends Controller
{
    /** سایز استانداردی که پیش‌نمایش با آن ساخته می‌شود. */
    public const PREVIEW_SIZE = '40';

    public function __construct(protected WorkshopContext $context) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $templates = $this->context->withoutScope(fn () => PatternTemplate::query()
            ->with(['garmentType', 'workshop'])
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('generator', 'like', "%{$term}%")))
            ->when($request->query('garment_type'), fn ($q, $value) => $q->where('garment_type_id', $value))
            ->orderBy('sort')
            ->orderBy('name_fa')
            ->paginate(25)
            ->withQueryString());

        return view('admin.templates.index', [
            'templates' => $templates,
            'term' => $term,
            'garmentTypeId' => $request->query('garment_type'),
            'garmentTypes' => GarmentType::orderBy('name_fa')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.templates.create', [
            'template' => new PatternTemplate(['is_public' => true, 'sort' => 0]),
            'garmentTypes' => GarmentType::active()->orderBy('name_fa')->get(),
            'generators' => $this->generatorOptions(),
            'params' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = PatternTemplate::create($this->validated($request));

        $message = 'الگوی پایه «'.$template->name_fa.'» ساخته شد.';

        if ($request->boolean('render_preview')) {
            $message .= ' '.$this->renderPreview($template);
        }

        return redirect()->route('admin.templates.edit', $template)->with('status', $message);
    }

    public function edit(PatternTemplate $patternTemplate): View
    {
        return view('admin.templates.edit', [
            'template' => $patternTemplate,
            'garmentTypes' => GarmentType::active()->orderBy('name_fa')->get(),
            'generators' => $this->generatorOptions(),
            'params' => $this->paramRows($patternTemplate),
        ]);
    }

    public function update(Request $request, PatternTemplate $patternTemplate): RedirectResponse
    {
        $patternTemplate->update($this->validated($request, $patternTemplate));

        $message = 'الگوی پایه «'.$patternTemplate->name_fa.'» به‌روز شد.';

        if ($request->boolean('render_preview')) {
            $message .= ' '.$this->renderPreview($patternTemplate);
        }

        return redirect()->route('admin.templates.edit', $patternTemplate)->with('status', $message);
    }

    public function destroy(PatternTemplate $patternTemplate): RedirectResponse
    {
        $inUse = $this->context->withoutScope(
            fn () => Pattern::acrossWorkshops()->where('pattern_template_id', $patternTemplate->id)->count()
        );

        if ($inUse > 0) {
            return back()->with('error', 'این الگوی پایه مبنای '.Jalali::digits($inUse).
                ' الگوی ساخته‌شده است و حذف نمی‌شود. برای پنهان‌کردن، آن را «غیرعمومی» کنید.');
        }

        $name = $patternTemplate->name_fa;
        $patternTemplate->delete();

        return redirect()->route('admin.templates.index')
            ->with('status', 'الگوی پایه «'.$name.'» حذف شد.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?PatternTemplate $template = null): array
    {
        $data = $request->validate([
            'garment_type_id' => ['required', 'exists:garment_types,id'],
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/i',
                Rule::unique('pattern_templates', 'code')->whereNull('workshop_id')->ignore($template?->id),
            ],
            'name_fa' => ['required', 'string', 'max:120'],
            'generator' => ['required', 'string', 'max:64'],
            'params' => ['nullable', 'array'],
            'params.*.key' => ['nullable', 'string', 'max:48', 'regex:/^[a-z0-9_]*$/i'],
            'params.*.label' => ['nullable', 'string', 'max:80'],
            'params.*.default' => ['nullable', 'numeric'],
            'params.*.min' => ['nullable', 'numeric'],
            'params.*.max' => ['nullable', 'numeric'],
            'params.*.step' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'code.regex' => 'شناسه باید فقط حرف لاتین، رقم، خط تیره یا زیرخط باشد.',
            'params.*.key.regex' => 'کلید پارامتر باید فقط حرف لاتین، رقم یا زیرخط باشد.',
        ], [
            'garment_type_id' => 'نوع لباس',
            'code' => 'شناسه',
            'name_fa' => 'نام فارسی',
            'generator' => 'تولیدکننده',
            'description' => 'توضیح',
            'sort' => 'ترتیب',
        ]);

        [$defaults, $schema] = $this->paramsFromRows((array) ($data['params'] ?? []));

        return [
            'workshop_id' => null, // الگوهای پایه پنل مدیریت همیشه سراسری‌اند
            'garment_type_id' => (int) $data['garment_type_id'],
            'code' => $data['code'],
            'name_fa' => $data['name_fa'],
            'generator' => $data['generator'],
            'default_params' => $defaults,
            'params_schema' => $schema,
            'description' => $data['description'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'sort' => (int) ($data['sort'] ?? 0),
        ];
    }

    /**
     * سطرهای فرم پارامترها را به دو آرایه ذخیره‌شدنی تبدیل می‌کند.
     *
     * @return array{0: array<string, float>, 1: array<string, array>}
     */
    protected function paramsFromRows(array $rows): array
    {
        $defaults = [];
        $schema = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $defaults[$key] = round((float) ($row['default'] ?? 0), 2);

            $schema[$key] = array_filter([
                'label' => trim((string) ($row['label'] ?? '')) ?: $key,
                'min' => isset($row['min']) && $row['min'] !== '' ? (float) $row['min'] : null,
                'max' => isset($row['max']) && $row['max'] !== '' ? (float) $row['max'] : null,
                'step' => isset($row['step']) && $row['step'] !== '' ? (float) $row['step'] : 0.5,
            ], fn ($value) => $value !== null);
        }

        return [$defaults, $schema];
    }

    /** سطرهای فرم از روی داده ذخیره‌شده. */
    protected function paramRows(PatternTemplate $template): array
    {
        $schema = (array) ($template->params_schema ?? []);
        $defaults = (array) ($template->default_params ?? []);
        $keys = array_unique(array_merge(array_keys($schema), array_keys($defaults)));

        return collect($keys)
            ->map(fn ($key) => [
                'key' => $key,
                'label' => $schema[$key]['label'] ?? $key,
                'default' => $defaults[$key] ?? '',
                'min' => $schema[$key]['min'] ?? '',
                'max' => $schema[$key]['max'] ?? '',
                'step' => $schema[$key]['step'] ?? 0.5,
            ])
            ->values()
            ->all();
    }

    /**
     * پیش‌نمایش الگوی پایه را می‌سازد و ذخیره می‌کند.
     *
     * اگر موتور ساخت الگو در دسترس نباشد، صفحه بدون خطا کار می‌کند و فقط پیام
     * فارسی مناسب برگردانده می‌شود.
     */
    protected function renderPreview(PatternTemplate $template): string
    {
        if (! class_exists(PatternBuilder::class)) {
            return 'ساخت پیش‌نمایش در این نسخه در دسترس نیست.';
        }

        $template->loadMissing('garmentType');

        try {
            $result = app(PatternBuilder::class)->buildFromTemplate(
                $template,
                Measurements::fromSize(static::PREVIEW_SIZE),
                $template->garmentType?->ease() ?? [],
                $template->default_params ?? [],
                [],
            );
        } catch (\Throwable $exception) {
            return 'ساخت پیش‌نمایش انجام نشد: '.Str::limit($exception->getMessage(), 120);
        }

        // موتور الگو ممکن است فهرست قطعه‌ها را برگرداند یا آرایه‌ای شامل تصویر آماده
        $svg = is_array($result) ? ($result['preview_svg'] ?? $result['svg'] ?? null) : null;
        $pieces = match (true) {
            ! is_array($result) => [],
            array_is_list($result) => $result,
            default => (array) ($result['pieces'] ?? []),
        };

        if (! is_string($svg) || trim($svg) === '') {
            $svg = $this->renderWithEngine($template, $pieces) ?? $this->svgFromPieces($pieces);
        }

        if ($svg === null) {
            return 'موتور الگو تصویری برنگرداند؛ پیش‌نمایش ذخیره نشد.';
        }

        $template->update(['preview_svg' => $svg]);

        return 'پیش‌نمایش با سایز '.Jalali::digits(static::PREVIEW_SIZE).' ساخته شد.';
    }

    /**
     * ترسیم با موتور تصویر پروژه.
     *
     * قطعه‌های ساخته‌شده به مدل‌های ذخیره‌نشده تبدیل می‌شوند تا بدون نوشتن در پایگاه
     * داده بتوان بندانگشتی گرفت.
     */
    protected function renderWithEngine(PatternTemplate $template, array $pieces): ?string
    {
        if ($pieces === [] || ! class_exists(SvgRenderer::class)) {
            return null;
        }

        try {
            $pattern = new Pattern([
                'name' => $template->name_fa,
                'base_size' => static::PREVIEW_SIZE,
                'seam_allowances' => [],
            ]);

            $pattern->setRelation('pieces', collect($pieces)->map(fn ($piece) => new PatternPiece((array) $piece)));

            $svg = app(SvgRenderer::class)->thumbnail($pattern, 320);

            return trim($svg) === '' ? null : $svg;
        } catch (\Throwable) {
            return null;
        }
    }

    /** ترسیم ساده قطعه‌ها؛ تنها برای بندانگشتی کتابخانه. */
    protected function svgFromPieces(array $pieces): ?string
    {
        $polygons = [];
        $maxX = 0.0;
        $maxY = 0.0;
        $offsetX = 0.0;

        foreach ($pieces as $piece) {
            $points = collect($piece['outline'] ?? [])
                ->map(fn ($point) => [(float) ($point['x'] ?? 0), (float) ($point['y'] ?? 0)])
                ->all();

            if (count($points) < 3) {
                continue;
            }

            $minX = min(array_column($points, 0));
            $minY = min(array_column($points, 1));

            $shifted = array_map(
                fn ($point) => round($point[0] - $minX + $offsetX, 2).','.round($point[1] - $minY, 2),
                $points
            );

            $polygons[] = '<polygon points="'.implode(' ', $shifted).'" fill="#f5f3ff" stroke="#7c4ddb" stroke-width="0.4" />';

            $width = max(array_column($points, 0)) - $minX;
            $offsetX += $width + 4;
            $maxX = max($maxX, $offsetX);
            $maxY = max($maxY, max(array_column($points, 1)) - $minY);
        }

        if ($polygons === []) {
            return null;
        }

        $viewBox = '-2 -2 '.round($maxX + 4, 2).' '.round($maxY + 4, 2);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'" role="img" aria-label="پیش‌نمایش الگوی پایه">'
            .implode('', $polygons).'</svg>';
    }

    /**
     * فهرست تولیدکننده‌های موجود.
     *
     * اگر ثبت‌کننده تولیدکننده‌ها در دسترس باشد از آن خوانده می‌شود، در غیر این صورت
     * پوشه تولیدکننده‌ها خوانده می‌شود و اگر آن هم نبود فرم ورودی متنی نشان می‌دهد.
     *
     * @return array<string, string>
     */
    protected function generatorOptions(): array
    {
        $registry = 'App\Services\Pattern\GeneratorRegistry';

        if (class_exists($registry)) {
            foreach (['options', 'labels', 'all', 'keys'] as $method) {
                if (! method_exists($registry, $method)) {
                    continue;
                }

                try {
                    $result = $registry::{$method}();
                } catch (\Throwable) {
                    continue;
                }

                $options = $this->normalizeGeneratorList(is_array($result) ? $result : []);

                if ($options !== []) {
                    return $options;
                }
            }
        }

        $path = app_path('Services/Pattern/Generators');

        if (! is_dir($path)) {
            return [];
        }

        return collect(glob($path.'/*.php') ?: [])
            ->mapWithKeys(function (string $file) {
                $class = basename($file, '.php');
                $key = Str::snake(Str::replaceLast('Generator', '', $class));

                return [$key => $key];
            })
            ->filter(fn ($value, $key) => $key !== '')
            ->all();
    }

    /** @return array<string, string> */
    protected function normalizeGeneratorList(array $list): array
    {
        $options = [];

        foreach ($list as $key => $value) {
            if (is_int($key)) {
                if (is_string($value)) {
                    $options[$value] = $value;
                }

                continue;
            }

            $options[$key] = match (true) {
                is_string($value) => $value,
                is_array($value) => (string) ($value['label'] ?? $value['name'] ?? $key),
                default => (string) $key,
            };
        }

        return $options;
    }
}
