<?php

namespace App\Http\Controllers;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use App\Services\Fabric\FabricCompatibilityService;
use App\Support\FabricProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * بانک پایه پارچه.
 *
 * کاربر اینجا فقط مرور و انتخاب می‌کند؛ با یک کلیک، پارچه با تمام شناسنامه‌اش به
 * انبار کارگاه اضافه می‌شود و لازم نیست هیچ عدد فنی وارد کند.
 */
class FabricBankController extends Controller
{
    public function __construct(protected FabricCompatibilityService $compatibility) {}

    public function index(Request $request)
    {
        $category = $request->query('category');
        $family = $request->query('family');
        $term = trim((string) $request->query('q'));

        $types = FabricType::query()
            ->active()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($family, fn ($query) => $query->where('family', $family))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name_fa', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->orderBy('sort')
            ->get();

        return view('fabric-bank.index', [
            'types' => $types,
            'q' => $term,
            'category' => $category,
            'family' => $family,
            'categories' => FabricType::CATEGORIES,
            'families' => FabricType::FAMILIES,
            'total' => FabricType::query()->active()->count(),
        ]);
    }

    public function show(FabricType $fabricType)
    {
        $profile = $fabricType->fabricProfile();

        return view('fabric-bank.show', [
            'type' => $fabricType,
            'profile' => $profile,
            'physics' => $profile->physics(),
            'groups' => FabricProfile::GROUPS,
            'schema' => FabricProfile::SCHEMA,
            'guides' => Guide::query()->where('fabric_type_id', $fabricType->id)->orderBy('sort')->get(),
            'matches' => GarmentType::query()->active()->orderBy('sort')->get()
                ->map(fn (GarmentType $garmentType) => [
                    'garment_type' => $garmentType,
                    'score' => $this->compatibility->scoreType($fabricType, $garmentType),
                ])
                ->sortByDesc(fn (array $row) => $row['score']['overall'])
                ->take(6)
                ->values(),
            'myFabrics' => Fabric::query()->where('fabric_type_id', $fabricType->id)->orderByDesc('id')->get(),
        ]);
    }

    /** افزودن پارچه بانک به انبار کارگاه با یک کلیک. */
    public function store(Request $request, FabricType $fabricType)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'width_cm' => ['nullable', 'numeric', 'min:30', 'max:400'],
            'stock_meters' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'price_per_meter' => ['nullable', 'numeric', 'min:0'],
            'surface_pattern' => ['nullable', Rule::in(array_keys(Fabric::SURFACE_PATTERNS))],
        ]);

        $name = trim((string) ($data['name'] ?? '')) ?: $fabricType->name_fa;

        $fabric = Fabric::create([
            'fabric_type_id' => $fabricType->id,
            'name' => $name,
            'color' => $data['color'] ?? null,
            'color_hex' => $data['color_hex'] ?? null,
            'surface_pattern' => $data['surface_pattern'] ?? 'plain',
            'width_cm' => $data['width_cm'] ?? null,
            'gsm' => (int) $fabricType->fabricProfile()->get('weight_gsm'),
            'price_per_meter' => $data['price_per_meter'] ?? null,
            'stock_meters' => $data['stock_meters'] ?? 0,
            'is_active' => true,
        ]);

        return redirect()->route('fabrics.show', $fabric)
            ->with('success', '«'.$fabric->name.'» با همه مشخصاتش به پارچه‌های شما اضافه شد.');
    }
}
