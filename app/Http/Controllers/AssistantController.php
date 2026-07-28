<?php

namespace App\Http\Controllers;

use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Project;
use App\Services\Assistant\WorkshopAssistant;
use Illuminate\Http\Request;

/**
 * دستیار کارگاه.
 *
 * پاسخ‌ها با موتور قواعد و سنجش سازگاری پارچه ساخته می‌شوند؛ هیچ سرویس بیرونی در
 * کار نیست. پرسش با POST می‌رود و پاسخ در نشست برمی‌گردد تا صفحه ساده بماند.
 */
class AssistantController extends Controller
{
    public function __construct(protected WorkshopAssistant $assistant) {}

    public function index(Request $request)
    {
        return view('assistant.index', [
            'answer' => session('assistant.answer'),
            'examples' => WorkshopAssistant::EXAMPLES,
            'fabrics' => Fabric::query()->active()->orderBy('name')->get(),
            'garmentTypes' => GarmentType::query()->active()->orderBy('sort')->pluck('name_fa', 'id')->all(),
            'projects' => Project::query()->with('garmentType')->orderByDesc('id')->take(20)->get(),
            'selected' => [
                'question' => (string) session('assistant.question', $request->query('question', '')),
                'fabric_id' => session('assistant.fabric_id', $request->query('fabric_id')),
                'garment_type_id' => session('assistant.garment_type_id', $request->query('garment_type_id')),
                'project_id' => session('assistant.project_id', $request->query('project_id')),
            ],
        ]);
    }

    public function ask(Request $request)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'fabric_id' => ['nullable', 'integer'],
            'garment_type_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ], [], ['question' => 'پرسش']);

        $project = $data['project_id'] ? Project::query()->with(['fabric', 'garmentType', 'pattern', 'latestSimulation'])->find($data['project_id']) : null;
        $fabric = $data['fabric_id'] ? Fabric::query()->with('fabricType')->find($data['fabric_id']) : null;
        $garmentType = $data['garment_type_id'] ? GarmentType::find($data['garment_type_id']) : null;

        $answer = $this->assistant->ask($data['question'], $fabric, $garmentType, $project);

        return redirect()->route('assistant.index')->with([
            'assistant.answer' => $answer,
            'assistant.question' => $data['question'],
            'assistant.fabric_id' => $data['fabric_id'] ?? null,
            'assistant.garment_type_id' => $data['garment_type_id'] ?? null,
            'assistant.project_id' => $data['project_id'] ?? null,
        ]);
    }
}
