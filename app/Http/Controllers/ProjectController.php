<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\Project;
use App\Services\Fit\FitAnalysisService;
use App\Support\Measurements;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * پروژه‌ها: هر پروژه یک لباس است که گام‌به‌گام کامل می‌شود.
 *
 * ساختن پروژه فقط به یک نام نیاز دارد؛ مشتری، نوع لباس و پارچه بعداً در همان
 * مسیر گام‌به‌گام انتخاب می‌شوند تا کاربر پشت یک فرم بلند نماند.
 */
class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $projects = Project::query()
            ->with(['customer', 'garmentType', 'fabric'])
            ->when(isset(Project::STATUSES[$status]), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'status' => $status,
            'search' => $search,
            'counts' => Project::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'customers' => $this->recentCustomers(),
            'garmentTypes' => $this->garmentTypes(),
            'sizes' => Measurements::sizes(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'garment_type_id' => ['nullable', 'integer', 'exists:garment_types,id'],
            'size' => ['nullable', 'string', 'in:'.implode(',', Measurements::sizes())],
        ], [
            'name.required' => 'برای شروع فقط یک نام لازم است.',
        ]);

        $project = Project::create([
            'name' => $data['name'],
            'customer_id' => $data['customer_id'] ?? null,
            'garment_type_id' => $data['garment_type_id'] ?? null,
            'size' => $data['size'] ?? null,
            'measurement_set_id' => $this->defaultMeasurementSet($data['customer_id'] ?? null),
            'status' => 'draft',
            'step' => 1,
        ]);

        return redirect()
            ->route('projects.step', [$project, 'design'])
            ->with('status', 'پروژه ساخته شد. در هر مرحله فقط به یک سؤال جواب بدهید.');
    }

    public function show(Project $project, FitAnalysisService $fit): View
    {
        $project->load([
            'customer', 'garmentType', 'fabric', 'liningFabric', 'measurementSet',
            'pattern.pieces', 'latestSimulation', 'latestCuttingLayout', 'techPacks',
        ]);

        return view('projects.show', [
            'project' => $project,
            'completed' => $project->completedSteps(),
            'scorecard' => $this->scorecard($project, $fit),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', [
            'project' => $project,
            'customers' => $this->recentCustomers(),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Project::STATUSES))],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $project->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'تغییرات پروژه ذخیره شد.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', 'پروژه حذف شد.');
    }

    /** کارنامه کیفیت؛ اگر محاسبه ممکن نبود صفحه از کار نمی‌افتد. */
    protected function scorecard(Project $project, FitAnalysisService $fit): ?array
    {
        try {
            return $fit->scorecard($project);
        } catch (\Throwable) {
            return $project->scorecard;
        }
    }

    /** مشتری‌ها با تازه‌ترین‌ها در ابتدا. */
    protected function recentCustomers()
    {
        return Customer::query()->latest('id')->limit(200)->get(['id', 'name']);
    }

    /** انواع لباس فعال، آماده گروه‌بندی بر پایه دسته. */
    protected function garmentTypes()
    {
        return GarmentType::query()->active()->orderBy('sort')->orderBy('id')->get();
    }

    /** دفترچه اندازه پیش‌فرض مشتری، اگر داشته باشد. */
    protected function defaultMeasurementSet(?int $customerId): ?int
    {
        if (! $customerId) {
            return null;
        }

        return Customer::find($customerId)?->defaultMeasurementSet?->id;
    }
}
