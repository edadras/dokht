<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** انبار متعلقات: نخ، دکمه، زیپ، لایی، آستر و کشی. */
class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q'));

        $materials = Material::query()
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('spec', 'like', "%{$term}%")
                ->orWhere('color', 'like', "%{$term}%")))
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->groupBy('kind');

        return view('materials.index', [
            'grouped' => $materials,
            'kinds' => Material::KINDS,
            'q' => $term,
            'totalValue' => (float) Material::query()->selectRaw('COALESCE(SUM(price * stock), 0) as total')->value('total'),
        ]);
    }

    public function create()
    {
        return view('materials.create', [
            'material' => new Material(['kind' => 'thread', 'unit' => 'عدد']),
            'kinds' => Material::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        Material::create($this->validateMaterial($request));

        return redirect()->route('materials.index')->with('success', 'متعلقات جدید ثبت شد.');
    }

    public function edit(Material $material)
    {
        return view('materials.edit', [
            'material' => $material,
            'kinds' => Material::KINDS,
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validateMaterial($request));

        return redirect()->route('materials.index')->with('success', 'متعلقات به‌روز شد.');
    }

    public function destroy(Material $material)
    {
        $material->delete();

        return redirect()->route('materials.index')->with('success', 'متعلقات حذف شد.');
    }

    protected function validateMaterial(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(Material::KINDS))],
            'spec' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:16'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'color' => ['nullable', 'string', 'max:60'],
        ], [], [
            'name' => 'نام',
            'kind' => 'نوع',
            'spec' => 'مشخصات',
            'unit' => 'واحد',
            'price' => 'قیمت',
            'stock' => 'موجودی',
            'color' => 'رنگ',
        ]);
    }
}
