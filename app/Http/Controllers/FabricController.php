<?php

namespace App\Http\Controllers;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\Guide;
use App\Services\Fabric\FabricCompatibilityService;
use App\Services\Rules\RuleEngine;
use App\Support\FabricProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** پارچه‌های موجود در کارگاه. */
class FabricController extends Controller
{
    public function __construct(
        protected RuleEngine $engine,
        protected FabricCompatibilityService $compatibility,
    ) {}

    public function index(Request $request)
    {
        $fabrics = Fabric::query()
            ->with('fabricType')
            ->search($request->query('q'))
            ->when($request->query('type'), fn ($query, $type) => $query->where('fabric_type_id', $type))
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('fabrics.index', [
            'fabrics' => $fabrics,
            'q' => (string) $request->query('q'),
            'typeOptions' => FabricType::query()->active()->orderBy('sort')->pluck('name_fa', 'id')->all(),
            'selectedType' => $request->query('type'),
            'totalMeters' => (float) Fabric::query()->sum('stock_meters'),
        ]);
    }

    public function create()
    {
        return view('fabrics.create', $this->formData(new Fabric(['surface_pattern' => 'plain'])));
    }

    public function store(Request $request)
    {
        $data = $this->validateFabric($request);
        $type = FabricType::find($data['fabric_type_id']);

        $fabric = new Fabric;
        $this->fill($fabric, $data, $type, $request);
        $fabric->save();

        return redirect()->route('fabrics.show', $fabric)
            ->with('success', 'پارچه «'.$fabric->name.'» ثبت شد.');
    }

    public function show(Fabric $fabric)
    {
        $fabric->load('fabricType');
        $profile = $fabric->profile();
        $facts = $this->engine->factsFor($fabric);

        return view('fabrics.show', [
            'fabric' => $fabric,
            'profile' => $profile,
            'physics' => $profile->physics(),
            'groups' => FabricProfile::GROUPS,
            'schema' => FabricProfile::SCHEMA,
            'fabricResults' => $this->engine->evaluate('fabric', $facts),
            'cuttingResults' => $this->engine->evaluate('cutting', $facts),
            'productionResults' => $this->engine->evaluate('production', $facts),
            'guides' => $fabric->fabric_type_id
                ? Guide::query()->where('fabric_type_id', $fabric->fabric_type_id)->orderBy('sort')->get()
                : collect(),
            'matches' => $this->compatibility->suggestGarmentTypes($fabric, 6),
        ]);
    }

    public function edit(Fabric $fabric)
    {
        return view('fabrics.edit', $this->formData($fabric->load('fabricType')));
    }

    public function update(Request $request, Fabric $fabric)
    {
        $data = $this->validateFabric($request, $fabric);
        $type = FabricType::find($data['fabric_type_id']);

        $this->fill($fabric, $data, $type, $request);
        $fabric->save();

        return redirect()->route('fabrics.show', $fabric)->with('success', 'پارچه به‌روز شد.');
    }

    public function destroy(Fabric $fabric)
    {
        $fabric->delete();

        return redirect()->route('fabrics.index')->with('success', 'پارچه حذف شد.');
    }

    /** داده‌های مشترک فرم ساخت و ویرایش. */
    protected function formData(Fabric $fabric): array
    {
        $types = FabricType::query()->active()->orderBy('sort')->get();

        return [
            'fabric' => $fabric,
            'typeGroups' => $types
                ->groupBy(fn (FabricType $type) => $type->categoryLabel())
                ->map(fn ($group) => $group->pluck('name_fa', 'id')->all())
                ->all(),
            'typeProfiles' => $types->mapWithKeys(fn (FabricType $type) => [
                $type->id => [
                    'name' => $type->name_fa,
                    'gsm' => (int) $type->fabricProfile()->get('weight_gsm'),
                    'profile' => $type->fabricProfile()->toArray(),
                ],
            ])->all(),
            'baseProfile' => $fabric->profile()->toArray(),
            'overrides' => $fabric->profile_overrides ?? [],
            'groups' => FabricProfile::GROUPS,
            'schema' => FabricProfile::SCHEMA,
            'patternOptions' => Fabric::SURFACE_PATTERNS,
        ];
    }

    protected function validateFabric(Request $request, ?Fabric $fabric = null): array
    {
        return $request->validate([
            'fabric_type_id' => ['required', Rule::exists('fabric_types', 'id')->where('is_active', true)],
            'name' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'surface_pattern' => ['nullable', Rule::in(array_keys(Fabric::SURFACE_PATTERNS))],
            'pattern_repeat_cm' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'width_cm' => ['nullable', 'numeric', 'min:30', 'max:400'],
            'gsm' => ['nullable', 'integer', 'min:10', 'max:900'],
            'price_per_meter' => ['nullable', 'numeric', 'min:0'],
            'stock_meters' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'profile' => ['nullable', 'array'],
            'texture' => ['nullable', 'image', 'max:4096'],
            'normal_map' => ['nullable', 'image', 'max:4096'],
            'roughness_map' => ['nullable', 'image', 'max:4096'],
        ], [], [
            'fabric_type_id' => 'پارچه پایه',
            'name' => 'نام پارچه',
            'color' => 'رنگ',
            'color_hex' => 'کد رنگ',
            'width_cm' => 'عرض پارچه',
            'price_per_meter' => 'قیمت هر متر',
            'stock_meters' => 'موجودی',
        ]);
    }

    protected function fill(Fabric $fabric, array $data, ?FabricType $type, Request $request): void
    {
        $fabric->fill([
            'fabric_type_id' => $data['fabric_type_id'],
            'name' => trim((string) ($data['name'] ?? '')) ?: ($type?->name_fa ?? 'پارچه'),
            'color' => $data['color'] ?? null,
            'color_hex' => $data['color_hex'] ?? null,
            'surface_pattern' => $data['surface_pattern'] ?? 'plain',
            'pattern_repeat_cm' => $data['pattern_repeat_cm'] ?? null,
            'width_cm' => $data['width_cm'] ?? null,
            'gsm' => $data['gsm'] ?? null,
            'price_per_meter' => $data['price_per_meter'] ?? null,
            'stock_meters' => $data['stock_meters'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'profile_overrides' => $this->overrides($type, $data['profile'] ?? []),
        ]);

        $maps = [
            'texture' => 'texture_path',
            'normal_map' => 'normal_map_path',
            'roughness_map' => 'roughness_map_path',
        ];

        foreach ($maps as $input => $column) {
            if ($request->hasFile($input)) {
                $fabric->{$column} = $request->file($input)->store('fabrics', 'public');
            }
        }
    }

    /**
     * فقط تفاوت‌های شناسنامه با پارچه پایه ذخیره می‌شود.
     *
     * این‌طور اگر بانک پارچه بعداً به‌روز شود پارچه کارگاه هم به‌روز می‌شود و تنها
     * چیزهایی که کاربر دستی عوض کرده دست‌نخورده می‌ماند.
     */
    protected function overrides(?FabricType $type, array $input): ?array
    {
        $base = FabricProfile::make($type?->profile ?? [])->toArray();

        $provided = array_filter(
            array_intersect_key($input, FabricProfile::SCHEMA),
            fn ($value) => $value !== null && $value !== '' && ! is_array($value),
        );

        if ($provided === []) {
            return null;
        }

        $normalized = FabricProfile::normalize($provided);
        $out = [];

        foreach (array_keys($provided) as $key) {
            if (! $this->same($normalized[$key], $base[$key] ?? null)) {
                $out[$key] = $normalized[$key];
            }
        }

        return $out === [] ? null : $out;
    }

    protected function same(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-6;
        }

        return (string) $a === (string) $b;
    }
}
