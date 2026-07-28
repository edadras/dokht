<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Support\FabricProfile;
use App\Support\Jalali;
use App\Support\WorkshopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * مدیریت بانک پایه پارچه (سراسری).
 *
 * فرم دو لایه است: شناسه‌های ساده بالا و شناسنامه فیزیکی کامل داخل بخش پیشرفته که
 * مستقیم از FabricProfile::SCHEMA ساخته می‌شود تا هیچ کلیدی جا نماند.
 */
class FabricTypeController extends Controller
{
    public function __construct(protected WorkshopContext $context) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $types = $this->context->withoutScope(fn () => FabricType::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")))
            ->when($request->query('category'), fn ($q, $value) => $q->where('category', $value))
            ->when($request->query('family'), fn ($q, $value) => $q->where('family', $value))
            ->withCount('fabrics')
            ->orderBy('sort')
            ->orderBy('name_fa')
            ->paginate(25)
            ->withQueryString());

        return view('admin.fabric-types.index', [
            'types' => $types,
            'term' => $term,
            'category' => $request->query('category'),
            'family' => $request->query('family'),
        ]);
    }

    public function create(): View
    {
        return view('admin.fabric-types.create', [
            'type' => new FabricType(['profile' => FabricProfile::defaults(), 'is_active' => true, 'sort' => 0]),
            'profile' => FabricProfile::defaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $type = FabricType::create($data);

        return redirect()->route('admin.fabric-types.index')
            ->with('status', 'پارچه «'.$type->name_fa.'» به بانک پارچه اضافه شد.');
    }

    public function edit(FabricType $fabricType): View
    {
        return view('admin.fabric-types.edit', [
            'type' => $fabricType,
            'profile' => FabricProfile::normalize($fabricType->profile ?? []),
        ]);
    }

    public function update(Request $request, FabricType $fabricType): RedirectResponse
    {
        $fabricType->update($this->validated($request, $fabricType));

        return redirect()->route('admin.fabric-types.index')
            ->with('status', 'پارچه «'.$fabricType->name_fa.'» به‌روز شد.');
    }

    public function destroy(FabricType $fabricType): RedirectResponse
    {
        // کلید خارجی nullOnDelete است، پس اگر جلوی حذف را نگیریم پارچه‌های کارگاه‌ها بی‌شناسنامه می‌شوند
        $inUse = $this->context->withoutScope(
            fn () => Fabric::acrossWorkshops()->where('fabric_type_id', $fabricType->id)->count()
        );

        if ($inUse > 0) {
            return back()->with('error', 'این پارچه در '.Jalali::digits($inUse).
                ' پارچه ثبت‌شده کارگاه‌ها استفاده شده است و حذف نمی‌شود. می‌توانید آن را «غیرفعال» کنید تا در فهرست‌های تازه دیده نشود.');
        }

        $name = $fabricType->name_fa;
        $fabricType->delete();

        return redirect()->route('admin.fabric-types.index')
            ->with('status', 'پارچه «'.$name.'» حذف شد.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?FabricType $type = null): array
    {
        $validator = Validator::make($request->all(), [
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/i',
                Rule::unique('fabric_types', 'code')->ignore($type?->id),
            ],
            'name_fa' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(FabricType::CATEGORIES))],
            'family' => ['nullable', Rule::in(array_keys(FabricType::FAMILIES))],
            'fibers' => ['nullable', 'array'],
            'fibers.*.name' => ['nullable', 'string', 'max:60'],
            'fibers.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profile' => ['nullable', 'array'],
            'care' => ['nullable', 'string'],
            'sewing' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'شناسه باید فقط حرف لاتین، رقم، خط تیره یا زیرخط باشد.',
        ], [
            'code' => 'شناسه',
            'name_fa' => 'نام فارسی',
            'name_en' => 'نام لاتین',
            'category' => 'دسته',
            'family' => 'خانواده بافت',
            'description' => 'توضیح',
            'sort' => 'ترتیب',
        ]);

        $fibers = $this->fibers((array) $request->input('fibers', []));

        // مجموع درصد الیاف باید حدود ۱۰۰ باشد؛ کمی رواداری برای رُند کردن
        $validator->after(function ($validator) use ($fibers) {
            if ($fibers !== [] && abs(array_sum(array_column($fibers, 'percent')) - 100) > 1) {
                $validator->errors()->add('fibers', 'مجموع درصد الیاف باید حدود ۱۰۰ باشد.');
            }
        });

        $data = $validator->validate();

        return [
            'code' => $data['code'],
            'name_fa' => $data['name_fa'],
            'name_en' => $data['name_en'] ?? null,
            'category' => $data['category'],
            'family' => $data['family'] ?? null,
            'fiber_composition' => $fibers,
            'profile' => FabricProfile::normalize($this->profileInput($request)),
            'care' => $this->lines($data['care'] ?? null),
            'sewing' => $this->lines($data['sewing'] ?? null),
            'description' => $data['description'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * ورودی خام شناسنامه.
     *
     * تیک‌های خالی‌نشده در HTML فرستاده نمی‌شوند، پس مقدار bool را از روی وجود
     * کلید تعیین می‌کنیم تا خاموش‌کردن یک ویژگی هم ذخیره شود.
     */
    protected function profileInput(Request $request): array
    {
        $values = (array) $request->input('profile', []);

        foreach (FabricProfile::SCHEMA as $key => $definition) {
            if ($definition['type'] === 'bool') {
                $values[$key] = $request->boolean('profile.'.$key);
            } elseif (isset($values[$key]) && is_string($values[$key])) {
                $values[$key] = Jalali::latinDigits($values[$key]);
            }
        }

        return $values;
    }

    /** @return array<int, array{name: string, percent: float}> */
    protected function fibers(array $rows): array
    {
        return collect($rows)
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'percent' => round((float) Jalali::latinDigits((string) ($row['percent'] ?? 0)), 1),
            ])
            ->filter(fn ($row) => $row['name'] !== '' && $row['percent'] > 0)
            ->values()
            ->all();
    }

    /** هر خط یک نکته. */
    protected function lines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
