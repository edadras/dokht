<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Simulation;
use App\Services\Fit\FitAnalysisService;
use App\Services\Simulation\ClothPreviewService;
use App\Services\Simulation\ServerRenderService;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * پوشاندن لباس روی مانکن.
 *
 * هر بار که کاربر دکمه‌ی «شبیه‌سازی کن» را می‌زند یک نتیجه‌ی تازه ذخیره می‌شود تا
 * تاریخچه‌ی تغییرها بماند و بتوان دو حالت را با هم مقایسه کرد.
 */
class SimulationController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        FitAnalysisService $fit,
        ClothPreviewService $preview,
        ServerRenderService $renders,
    )
    {
        $data = $request->validate([
            'pose' => ['nullable', 'string', 'in:'.implode(',', array_keys(Simulation::POSES))],
        ], [
            'pose.in' => 'این حالت بدن شناخته نمی‌شود.',
        ]);

        $pose = $data['pose'] ?? 'stand';

        if (! $project->pattern) {
            return redirect()
                ->route('projects.step', [$project, 'pattern'])
                ->with('error', 'برای شبیه‌سازی اول باید الگو ساخته شود.');
        }

        try {
            $analysis = $fit->analyze(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
                $pose,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'شبیه‌سازی انجام نشد. اندازه‌ها و الگو را بررسی کنید.');
        }

        $simulation = $project->simulations()->create([
            'pattern_id' => $project->pattern_id,
            'fabric_id' => $project->fabric_id,
            'measurement_set_id' => $project->measurement_set_id,
            'pose' => $pose,
            'params' => $analysis['params'],
            'zones' => $analysis['zones'],
            'warnings' => $analysis['warnings'],
            'suggestions' => $analysis['suggestions'],
            'fit_score' => $analysis['fit_score'],
        ]);

        try {
            $project->pattern->loadMissing(['pieces', 'garmentType']);
            $renders->queue($simulation, $preview->payload(
                $project->pattern,
                $project->fabric,
                $project->resolvedMeasurements(),
                ['zones' => $analysis['zones'], 'pose' => $pose],
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        $project->update([
            'settings' => array_merge($project->settings ?? [], ['pose' => $pose]),
            'step' => max((int) $project->step, 8),
        ]);

        try {
            $fit->scorecard($project->refresh());
        } catch (\Throwable) {
            // کارنامه اختیاری است و نباید جلوی ادامه‌ی کار را بگیرد.
        }

        $target = $request->input('redirect') === 'back' ? url()->previous() : route('projects.step', [$project, 'fit']);

        return redirect($target)->with('status', $this->summary($simulation));
    }

    public function show(
        Simulation $simulation,
        ClothPreviewService $preview,
        ServerRenderService $renders,
    ): View
    {
        $simulation->load(['project', 'pattern.pieces', 'fabric', 'measurementSet']);

        $payload = null;

        if ($simulation->pattern) {
            try {
                $payload = $preview->payload(
                    $simulation->pattern,
                    $simulation->fabric,
                    $simulation->measurementSet?->completed()
                        ?? $simulation->project?->resolvedMeasurements()
                        ?? [],
                    [
                        'zones' => $simulation->zones ?? [],
                        'pose' => $simulation->pose,
                    ],
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('simulations.show', [
            'simulation' => $simulation,
            'project' => $simulation->project,
            'payload' => $payload,
            'levels' => Simulation::LEVELS,
            'serverRender' => $renders->result($simulation),
        ]);
    }

    public function renderStatus(Simulation $simulation, ServerRenderService $renders)
    {
        return response()->json($renders->result($simulation));
    }

    /** خلاصه‌ی فارسی نتیجه برای پیام موفقیت. */
    protected function summary(Simulation $simulation): string
    {
        $problems = count($simulation->warnings ?? []);
        $tight = collect($simulation->zones ?? [])->where('level', 'tight')->count();

        $summary = 'شبیه‌سازی در حالت «'.$simulation->poseLabel().'» انجام شد؛ امتیاز تناسب '
            .Jalali::digits((string) $simulation->fit_score).' از ۱۰۰.';

        if ($tight > 0) {
            return $summary.' '.Jalali::digits((string) $tight).' ناحیه تنگ است و باید اصلاح شود.';
        }

        if ($problems > 0) {
            return $summary.' '.Jalali::digits((string) $problems).' نکته برای بررسی هست.';
        }

        return $summary.' هیچ مشکل مهمی پیدا نشد.';
    }
}

