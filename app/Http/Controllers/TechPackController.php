<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Cutting\LayoutRenderer;
use App\Services\Export\ProjectArchiveExporter;
use App\Services\TechPack\TechPackBuilder;
use App\Services\TechPack\ThumbnailRenderer;
use App\Support\Jalali;
use Illuminate\Http\Request;

/**
 * کارت فنی پروژه: برگه‌ای که با آن می‌توان لباس را تولید کرد و بسته خروجی آن.
 */
class TechPackController extends Controller
{
    public function __construct(
        protected TechPackBuilder $builder,
        protected ThumbnailRenderer $thumbnails,
        protected LayoutRenderer $renderer,
        protected ProjectArchiveExporter $exporter,
    ) {}

    public function show(Project $project)
    {
        $techPack = $project->techPacks()->first();
        $data = $techPack?->data ?: $this->builder->build($project);
        $layout = $project->latestCuttingLayout;

        return view('tech-packs.show', [
            'project' => $project,
            'techPack' => $techPack,
            'data' => $data,
            'versions' => $project->techPacks()->get(),
            'thumbnails' => $this->thumbnails->groups($project->pattern),
            'plan' => $layout ? $this->renderer->render($layout, ['legend' => false]) : null,
            'layout' => $layout,
        ]);
    }

    public function store(Project $project)
    {
        $techPack = $this->builder->store($project);

        if ($project->step < 10) {
            $project->update(['step' => 10]);
        }

        return redirect()
            ->route('tech-packs.show', $project)
            ->with('status', sprintf(
                'کارت فنی نسخه %s ساخته شد.',
                Jalali::digits((string) $techPack->version),
            ));
    }

    public function print(Project $project)
    {
        $techPack = $project->techPacks()->first();
        $data = $techPack?->data ?: $this->builder->build($project);
        $layout = $project->latestCuttingLayout;

        return view('tech-packs.print', [
            'project' => $project,
            'techPack' => $techPack,
            'data' => $data,
            'thumbnails' => $this->thumbnails->groups($project->pattern),
            'plan' => $layout ? $this->renderer->render($layout, ['print' => true, 'page_width_mm' => 170]) : null,
            'layout' => $layout,
        ]);
    }

    public function export(Request $request, Project $project)
    {
        $format = (string) $request->query('format', '');

        if ($format === '') {
            return $this->exporter->download($project);
        }

        if (! in_array($format, ['svg', 'dxf', 'json', 'csv'], true)) {
            return back()->with('error', 'این قالب خروجی پشتیبانی نمی‌شود.');
        }

        $file = $this->exporter->file($project, $format);

        if ($file === null) {
            return back()->with('error', 'برای این قالب خروجی، اول باید الگوی پروژه ساخته شود.');
        }

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => sprintf(
                'attachment; filename="project-%d-%s"',
                $project->id,
                $file['name'],
            ),
        ]);
    }
}
