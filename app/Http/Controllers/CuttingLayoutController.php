<?php

namespace App\Http\Controllers;

use App\Models\CuttingLayout;
use App\Models\Fabric;
use App\Models\Project;
use App\Services\Cutting\LayoutRenderer;
use App\Services\Cutting\NestingService;
use Illuminate\Http\Request;
use ReflectionMethod;
use Throwable;

/**
 * چیدمان برش: از الگو و پارچه به «چند متر پارچه بخرم» و نقشه چاپی برش.
 */
class CuttingLayoutController extends Controller
{
    /** کلیدهایی که کاربر می‌تواند در فرم تنظیمات بفرستد. */
    protected const OPTION_KEYS = [
        'fabric_width_cm', 'gap_cm', 'folded', 'respect_nap',
        'match_stripes', 'include_allowance', 'allow_rotation',
    ];

    public function __construct(
        protected NestingService $nesting,
        protected LayoutRenderer $renderer,
    ) {}

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'fabric_id' => ['nullable', 'integer'],
            'fabric_width_cm' => ['nullable', 'numeric', 'between:30,320'],
            'gap_cm' => ['nullable', 'numeric', 'between:0,15'],
            'folded' => ['nullable', 'boolean'],
            'respect_nap' => ['nullable', 'boolean'],
            'match_stripes' => ['nullable', 'boolean'],
            'include_allowance' => ['nullable', 'boolean'],
            'allow_rotation' => ['nullable', 'boolean'],
        ], [], [
            'fabric_width_cm' => 'عرض پارچه',
            'gap_cm' => 'فاصله بین قطعه‌ها',
            'folded' => 'تا کردن پارچه',
            'respect_nap' => 'رعایت جهت خواب پارچه',
            'match_stripes' => 'تطبیق راه‌راه',
            'include_allowance' => 'احتساب جای دوخت',
            'allow_rotation' => 'اجازه چرخش قطعه‌ها',
        ]);

        $pattern = $project->pattern;

        if ($pattern === null) {
            return back()->with('error', 'برای ساختن نقشه برش، اول باید برای این پروژه الگو بسازید.');
        }

        $pattern->load('pieces');

        if ($pattern->pieces->isEmpty()) {
            return back()->with('error', 'الگوی این پروژه هیچ قطعه‌ای ندارد؛ اول قطعه‌های الگو را بسازید.');
        }

        $fabric = $request->filled('fabric_id')
            ? Fabric::find($request->integer('fabric_id')) ?? $project->fabric
            : $project->fabric;

        $result = $this->nesting->nest($pattern, $fabric, $this->options($request));

        $layout = CuttingLayout::create([
            'project_id' => $project->id,
            'pattern_id' => $pattern->id,
            'fabric_id' => $fabric?->id,
            'fabric_width_cm' => $result['fabric_width_cm'],
            'required_length_cm' => $result['required_length_cm'],
            'waste_percent' => $result['waste_percent'],
            'efficiency' => $result['efficiency'],
            'respect_nap' => $result['options']['respect_nap'],
            'match_stripes' => $result['options']['match_stripes'],
            'folded' => $result['options']['folded'],
            'placements' => $result['placements'],
            'warnings' => $result['warnings'],
        ]);

        if ($project->step < 9) {
            $project->update(['step' => 9]);
        }

        return redirect()
            ->route('cutting-layouts.show', $layout)
            ->with('status', $layout->summaryText());
    }

    public function show(CuttingLayout $cuttingLayout)
    {
        $cuttingLayout->load(['pattern.pieces', 'fabric.fabricType', 'project.customer']);

        $pattern = $cuttingLayout->pattern;
        $fabric = $cuttingLayout->fabric;
        $options = $cuttingLayout->options();

        return view('cutting.show', [
            'layout' => $cuttingLayout,
            'project' => $cuttingLayout->project,
            'plan' => $this->renderer->render($cuttingLayout),
            'alternatives' => $pattern
                ? $this->safe(fn () => $this->nesting->alternatives($pattern, $fabric, $options), [])
                : [],
            'widthTable' => $pattern
                ? $this->safe(fn () => $this->nesting->estimateForWidths(
                    $pattern,
                    NestingService::COMMON_WIDTHS,
                    $fabric,
                    ['folded' => $cuttingLayout->folded],
                ), [])
                : [],
            'fabrics' => Fabric::query()->active()->orderBy('name')->get(),
            'ruleWarnings' => $this->ruleWarnings($cuttingLayout->project),
        ]);
    }

    public function print(CuttingLayout $cuttingLayout)
    {
        $cuttingLayout->load(['pattern.pieces', 'fabric.fabricType', 'project.customer']);

        return view('cutting.print', [
            'layout' => $cuttingLayout,
            'project' => $cuttingLayout->project,
            'plan' => $this->renderer->render($cuttingLayout, ['print' => true]),
        ]);
    }

    /** فقط گزینه‌هایی که کاربر واقعاً فرستاده است؛ بقیه پیش‌فرض هوشمند می‌گیرند. */
    protected function options(Request $request): array
    {
        $options = [];

        foreach (static::OPTION_KEYS as $key) {
            $value = $request->input($key);

            if ($request->has($key) && $value !== null && $value !== '') {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * هشدارهای قواعد طراحی مربوط به برش (اگر موتور قواعد در دسترس باشد).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ruleWarnings(?Project $project): array
    {
        $engine = 'App\Services\Rules\RuleEngine';

        if ($project === null || ! class_exists($engine)) {
            return [];
        }

        try {
            $method = new ReflectionMethod($engine, 'forProject');
            $results = $method->invoke($method->isStatic() ? null : app($engine), $project);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($results)) {
            return [];
        }

        $scoped = array_filter(
            $results,
            fn ($item) => is_array($item) && ($item['scope'] ?? null) === 'cutting',
        );

        if ($scoped === []) {
            $scoped = array_filter(
                $results,
                fn ($item) => is_array($item)
                    && ! array_key_exists('scope', $item)
                    && in_array($item['severity'] ?? '', ['warning', 'error', 'danger'], true),
            );
        }

        return array_values($scoped);
    }

    protected function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
