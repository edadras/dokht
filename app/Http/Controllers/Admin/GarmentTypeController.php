<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Support\Jalali;
use App\Support\Measurements;
use App\Support\WorkshopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** مدیریت انواع لباس: اجزا، آزادی پیش‌فرض، اندازه‌های لازم و ترجیح پارچه. */
class GarmentTypeController extends Controller
{
    /** آزادی‌هایی که در فرم گرفته می‌شود. */
    public const EASE_FIELDS = [
        'bust' => 'دور سینه',
        'waist' => 'دور کمر',
        'hip' => 'دور باسن',
        'bicep' => 'دور بازو',
    ];

    public function __construct(protected WorkshopContext $context) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $types = $this->context->withoutScope(fn () => GarmentType::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->when($request->query('category'), fn ($q, $value) => $q->where('category', $value))
            ->withCount(['patternTemplates', 'patterns'])
            ->orderBy('sort')
            ->orderBy('name_fa')
            ->paginate(25)
            ->withQueryString());

        return view('admin.garment-types.index', [
            'types' => $types,
            'term' => $term,
            'category' => $request->query('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.garment-types.create', [
            'type' => new GarmentType(['is_active' => true, 'sort' => 0]),
            'preferences' => $this->preferenceDefaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = GarmentType::create($this->validated($request));

        return redirect()->route('admin.garment-types.index')
            ->with('status', 'نوع لباس «'.$type->name_fa.'» اضافه شد.');
    }

    public function edit(GarmentType $garmentType): View
    {
        return view('admin.garment-types.edit', [
            'type' => $garmentType,
            'preferences' => array_replace_recursive(
                $this->preferenceDefaults(),
                (array) ($garmentType->fabric_preferences ?? [])
            ),
        ]);
    }

    public function update(Request $request, GarmentType $garmentType): RedirectResponse
    {
        $garmentType->update($this->validated($request, $garmentType));

        return redirect()->route('admin.garment-types.index')
            ->with('status', 'نوع لباس «'.$garmentType->name_fa.'» به‌روز شد.');
    }

    public function destroy(GarmentType $garmentType): RedirectResponse
    {
        $usage = $this->context->withoutScope(fn () => [
            'patterns' => Pattern::acrossWorkshops()->where('garment_type_id', $garmentType->id)->count(),
            'projects' => Project::acrossWorkshops()->where('garment_type_id', $garmentType->id)->count(),
            'templates' => PatternTemplate::where('garment_type_id', $garmentType->id)->count(),
        ]);

        if (array_sum($usage) > 0) {
            return back()->with('error', 'این نوع لباس در '
                .Jalali::digits($usage['patterns']).' الگو، '
                .Jalali::digits($usage['projects']).' پروژه و '
                .Jalali::digits($usage['templates']).' الگوی پایه استفاده شده است و حذف نمی‌شود. آن را «غیرفعال» کنید.');
        }

        $name = $garmentType->name_fa;
        $garmentType->delete();

        return redirect()->route('admin.garment-types.index')
            ->with('status', 'نوع لباس «'.$name.'» حذف شد.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?GarmentType $type = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/i',
                Rule::unique('garment_types', 'code')->ignore($type?->id),
            ],
            'name_fa' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(GarmentType::CATEGORIES))],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*' => [Rule::in(array_keys(GarmentType::PART_LABELS))],
            'ease' => ['nullable', 'array'],
            'ease.*' => ['nullable', 'numeric', 'min:-10', 'max:60'],
            'required_measurements' => ['nullable', 'array'],
            'required_measurements.*' => [Rule::in(array_keys(Measurements::FIELDS))],
            'preferences' => ['nullable', 'array'],
            'preferences.*.*' => ['nullable', 'numeric'],
            'icon' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'شناسه باید فقط حرف لاتین، رقم، خط تیره یا زیرخط باشد.',
            'parts.required' => 'دست‌کم یک جزء لباس را انتخاب کنید.',
        ], [
            'code' => 'شناسه',
            'name_fa' => 'نام فارسی',
            'name_en' => 'نام لاتین',
            'category' => 'دسته',
            'parts' => 'اجزای لباس',
            'sort' => 'ترتیب',
        ]);

        $ease = [];

        foreach (array_keys(static::EASE_FIELDS) as $key) {
            $value = $data['ease'][$key] ?? null;

            if ($value !== null && $value !== '') {
                $ease[$key] = round((float) $value, 1);
            }
        }

        return [
            'code' => $data['code'],
            'name_fa' => $data['name_fa'],
            'name_en' => $data['name_en'] ?? null,
            'category' => $data['category'],
            'parts' => array_values($data['parts']),
            'default_ease' => $ease,
            'required_measurements' => array_values($data['required_measurements'] ?? []),
            'fabric_preferences' => $this->preferences((array) ($data['preferences'] ?? [])),
            'icon' => $data['icon'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * ترجیح پارچه در همان ساختاری که نمره‌دهنده سازگاری می‌خواند.
     *
     * کلید stretch_weft برای صراحت نگه داشته می‌شود و همان بازه با کلید stretch هم
     * ذخیره می‌شود، چون سرویس سازگاری بیشترین کشسانی را با کلید stretch می‌خواند.
     */
    protected function preferences(array $input): array
    {
        $number = fn (string $group, string $key) => isset($input[$group][$key]) && $input[$group][$key] !== ''
            ? (float) $input[$group][$key]
            : null;

        $preferences = [
            'drape' => ['ideal' => $number('drape', 'ideal'), 'tolerance' => $number('drape', 'tolerance')],
            'weight_gsm' => ['min' => $number('weight_gsm', 'min'), 'max' => $number('weight_gsm', 'max')],
            'stretch_weft' => ['min' => $number('stretch_weft', 'min'), 'max' => $number('stretch_weft', 'max')],
            'transparency' => ['max' => $number('transparency', 'max')],
            'stiffness' => ['ideal' => $number('stiffness', 'ideal'), 'tolerance' => $number('stiffness', 'tolerance')],
        ];

        $preferences['stretch'] = $preferences['stretch_weft'];

        // کلیدهای خالی حذف می‌شوند تا مقدار پیش‌فرض سرویس سازگاری به‌کار بیاید
        return collect($preferences)
            ->map(fn (array $group) => array_filter($group, fn ($value) => $value !== null))
            ->filter(fn (array $group) => $group !== [])
            ->all();
    }

    /** مقدارهای آغازین فرم ترجیح پارچه. */
    protected function preferenceDefaults(): array
    {
        return [
            'drape' => ['ideal' => 0.5, 'tolerance' => 0.3],
            'weight_gsm' => ['min' => 100, 'max' => 300],
            'stretch_weft' => ['min' => 0, 'max' => 40],
            'transparency' => ['max' => 0.3],
            'stiffness' => ['ideal' => 0.4, 'tolerance' => 0.3],
        ];
    }
}
