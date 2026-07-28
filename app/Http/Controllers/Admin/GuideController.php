<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** مدیریت بخش آموزش: راهنمای پارچه، راهنمای مدل و راهنمای عمومی. */
class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $guides = Guide::query()
            ->with(['fabricType', 'garmentType'])
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$term}%")
                ->orWhere('summary', 'like', "%{$term}%")))
            ->when($request->query('scope'), fn ($q, $value) => $q->where('scope', $value))
            ->orderBy('scope')
            ->orderBy('sort')
            ->orderBy('title')
            ->paginate(25)
            ->withQueryString();

        return view('admin.guides.index', [
            'guides' => $guides,
            'term' => $term,
            'scope' => $request->query('scope'),
        ]);
    }

    public function create(): View
    {
        return view('admin.guides.create', $this->formData(new Guide(['scope' => 'general', 'sort' => 0])));
    }

    public function store(Request $request): RedirectResponse
    {
        $guide = Guide::create($this->validated($request));

        return redirect()->route('admin.guides.index')
            ->with('status', 'راهنمای «'.$guide->title.'» اضافه شد.');
    }

    public function edit(Guide $guide): View
    {
        return view('admin.guides.edit', $this->formData($guide));
    }

    public function update(Request $request, Guide $guide): RedirectResponse
    {
        $guide->update($this->validated($request));

        return redirect()->route('admin.guides.index')
            ->with('status', 'راهنمای «'.$guide->title.'» به‌روز شد.');
    }

    public function destroy(Guide $guide): RedirectResponse
    {
        $title = $guide->title;
        $guide->delete();

        return redirect()->route('admin.guides.index')
            ->with('status', 'راهنمای «'.$title.'» حذف شد.');
    }

    /** @return array<string, mixed> */
    protected function formData(Guide $guide): array
    {
        return [
            'guide' => $guide,
            'fabricTypes' => FabricType::orderBy('name_fa')->pluck('name_fa', 'id')->all(),
            'garmentTypes' => GarmentType::orderBy('name_fa')->pluck('name_fa', 'id')->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(Guide::SCOPES))],
            'fabric_type_id' => ['nullable', 'required_if:scope,fabric_type', 'exists:fabric_types,id'],
            'garment_type_id' => ['nullable', 'required_if:scope,garment_type', 'exists:garment_types,id'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'tips' => ['nullable', 'array'],
            'tips.*' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'fabric_type_id.required_if' => 'برای راهنمای پارچه، نوع پارچه را انتخاب کنید.',
            'garment_type_id.required_if' => 'برای راهنمای مدل لباس، نوع لباس را انتخاب کنید.',
        ], [
            'scope' => 'نوع راهنما',
            'title' => 'عنوان',
            'summary' => 'خلاصه',
            'body' => 'متن',
            'sort' => 'ترتیب',
        ]);

        return [
            'scope' => $data['scope'],
            'fabric_type_id' => $data['scope'] === 'fabric_type' ? (int) $data['fabric_type_id'] : null,
            'garment_type_id' => $data['scope'] === 'garment_type' ? (int) $data['garment_type_id'] : null,
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'tips' => collect($data['tips'] ?? [])
                ->map(fn ($tip) => trim((string) $tip))
                ->filter()
                ->values()
                ->all(),
            'sort' => (int) ($data['sort'] ?? 0),
        ];
    }
}
