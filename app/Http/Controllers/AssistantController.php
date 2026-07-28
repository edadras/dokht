<?php

namespace App\Http\Controllers;

use App\Models\Fabric;
use App\Models\GarmentType;
use App\Models\Project;
use App\Services\Assistant\AssistantManager;
use App\Services\Assistant\WorkshopAssistant;
use Illuminate\Http\Request;
use Throwable;

/**
 * دستیار کارگاه.
 *
 * پاسخ‌ها با موتور قواعد و سنجش سازگاری پارچه ساخته می‌شوند. اگر در تنظیمات
 * درایور مدل زبانی روشن شده باشد، همان داده‌ها به مدل داده می‌شود تا پاسخ روان‌تری
 * بنویسد؛ در هر حال پاسخ نهایی می‌گوید از کدام راه آمده است و هیچ خطایی در این
 * مسیر نباید صفحه را از کار بیندازد.
 */
class AssistantController extends Controller
{
    public function __construct(
        protected WorkshopAssistant $assistant,
        protected AssistantManager $assistants,
    ) {}

    public function index(Request $request)
    {
        return view('assistant.index', [
            'answer' => session('assistant.answer'),
            'examples' => WorkshopAssistant::EXAMPLES,
            'fabrics' => Fabric::query()->active()->orderBy('name')->get(),
            'garmentTypes' => GarmentType::query()->active()->orderBy('sort')->pluck('name_fa', 'id')->all(),
            'projects' => Project::query()->with('garmentType')->orderByDesc('id')->take(20)->get(),
            'driver' => $this->assistants->driverName(),
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

        $project = ($data['project_id'] ?? null)
            ? Project::query()->with(['fabric.fabricType', 'garmentType', 'pattern', 'latestSimulation'])->find($data['project_id'])
            : null;
        $fabric = ($data['fabric_id'] ?? null) ? Fabric::query()->with('fabricType')->find($data['fabric_id']) : null;
        $garmentType = ($data['garment_type_id'] ?? null) ? GarmentType::find($data['garment_type_id']) : null;

        try {
            $answer = $this->assistants->driver()->ask($data['question'], $fabric, $garmentType, $project);
        } catch (Throwable $exception) {
            report($exception);

            // هر خطایی در مسیر دستیار باید به پاسخ قاعده‌محور ختم شود، نه به خطای ۵۰۰
            $answer = $this->safeAnswer($data['question'], $fabric, $garmentType, $project);
            $answer['fallback_reason'] = 'در ساخت پاسخ با مدل زبانی خطایی رخ داد.';
        }

        return redirect()->route('assistant.index')->with([
            'assistant.answer' => $answer,
            'assistant.question' => $data['question'],
            'assistant.fabric_id' => $data['fabric_id'] ?? null,
            'assistant.garment_type_id' => $data['garment_type_id'] ?? null,
            'assistant.project_id' => $data['project_id'] ?? null,
        ]);
    }

    /** آخرین تور ایمنی: اگر خود موتور قواعد هم بلغزد، باز هم صفحه سالم می‌ماند. */
    protected function safeAnswer(string $question, $fabric, $garmentType, $project): array
    {
        try {
            return $this->assistant->ask($question, $fabric, $garmentType, $project);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'question' => $question,
                'topic' => 'overview',
                'topic_label' => 'پاسخ کلی',
                'headline' => 'در ساخت پاسخ مشکلی پیش آمد؛ پرسش را کمی ساده‌تر بنویسید یا پارچه و مدل لباس را انتخاب کنید.',
                'points' => WorkshopAssistant::EXAMPLES,
                'reasons' => [],
                'source' => 'rules',
                'source_label' => WorkshopAssistant::SOURCE_LABEL,
            ];
        }
    }
}
