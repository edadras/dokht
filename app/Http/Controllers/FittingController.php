<?php

namespace App\Http\Controllers;

use App\Models\Fitting;
use App\Models\Project;
use App\Services\Fit\AlterationService;
use App\Services\Fit\FitAnalysisService;
use App\Support\Alterations;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * پرو: ثبت آنچه روی تن مشتری دیده شد و برگرداندنش به الگو.
 */
class FittingController extends Controller
{
    public function __construct(
        protected AlterationService $alterations = new AlterationService,
        protected FitAnalysisService $fit = new FitAnalysisService,
    ) {}

    public function index(Project $project): View
    {
        $project->load(['pattern.pieces', 'customer', 'fabric']);

        $suggested = [];

        if ($project->pattern) {
            $report = $this->fit->analyze(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
            );

            $suggested = Alterations::suggestFromFit($report);
        }

        return view('fittings.index', [
            'project' => $project,
            'fittings' => $project->fittings()->latest('fitted_on')->latest('id')->get(),
            'catalogue' => Alterations::all(),
            'suggested' => $suggested,
            'today' => Jalali::date(now()),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'fitted_on' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'numeric', 'between:-30,30'],
        ], [], [
            'fitted_on' => 'تاریخ پرو',
            'notes' => 'یادداشت',
        ]);

        $adjustments = [];

        foreach ($data['values'] ?? [] as $key => $value) {
            if (! Alterations::has((string) $key) || $value === null || abs((float) $value) < 0.01) {
                continue;
            }

            $adjustments[] = ['key' => (string) $key, 'value' => Alterations::clamp((string) $key, (float) $value)];
        }

        if ($adjustments === [] && blank($data['notes'] ?? null)) {
            return back()->withInput()->with('error', 'دست‌کم یک اصلاح یا یک یادداشت لازم است.');
        }

        $fitting = new Fitting([
            'project_id' => $project->id,
            'pattern_id' => $project->pattern_id,
            'fitted_on' => Jalali::parseJalali($data['fitted_on'] ?? null) ?? now(),
            'round' => $project->fittings()->count() + 1,
            'notes' => $data['notes'] ?? null,
            'adjustments' => $adjustments,
            'created_by' => $request->user()?->id,
        ]);

        $fitting->save();

        return redirect()
            ->route('projects.fittings', $project)
            ->with('status', 'پروی '.Jalali::digits((string) $fitting->round).'ام ثبت شد.');
    }

    /** اعمال اصلاح‌های یک پرو روی الگو. */
    public function apply(Project $project, Fitting $fitting): RedirectResponse
    {
        abort_unless($fitting->project_id === $project->id, 404);

        try {
            $pattern = $this->alterations->apply($fitting);
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()
            ->route('projects.fittings', $project)
            ->with('status', 'الگو اصلاح شد و نسخه '.Jalali::digits((string) $pattern->version).' ساخته شد.');
    }

    public function destroy(Project $project, Fitting $fitting): RedirectResponse
    {
        abort_unless($fitting->project_id === $project->id, 404);

        if ($fitting->isApplied()) {
            return back()->with('error', 'پرویی که روی الگو نشسته پاک نمی‌شود؛ برای برگشت از تاریخچه نسخه‌ها استفاده کنید.');
        }

        $fitting->delete();

        return back()->with('status', 'پرو پاک شد.');
    }
}
