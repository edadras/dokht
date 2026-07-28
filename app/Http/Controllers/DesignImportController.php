<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Services\Pattern\PatternBuilder;
use App\Services\Vision\GarmentImageAnalyzer;
use App\Services\Vision\SketchAnalyzer;
use App\Support\Format;
use App\Support\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * ورود طرح: از روی عکس لباس یا از روی طرح دستی.
 *
 * این بخش «الگو نمی‌سازد»؛ فقط یک نقطه شروع پیشنهاد می‌دهد. سنجش شکل کاملاً
 * هندسی و قابل توضیح است (بدون هیچ سرویس بیرونی و بدون هیچ درخواست شبکه‌ای)، و
 * کاربر می‌تواند هر تصمیم را ببیند، بپذیرد یا عوض کند. ساخت خود الگو با همان
 * تولیدکننده‌های واقعی انجام می‌شود تا الگو ویرایش‌پذیر، سایزبندی‌پذیر و
 * خروجی‌گرفتنی بماند.
 */
class DesignImportController extends Controller
{
    /** بیشترین حجم عکس ورودی (کیلوبایت). */
    public const MAX_IMAGE_KB = 6144;

    public function __construct(
        protected GarmentImageAnalyzer $images = new GarmentImageAnalyzer,
        protected SketchAnalyzer $sketches = new SketchAnalyzer,
        protected PatternBuilder $builder = new PatternBuilder,
    ) {}

    public function create(Request $request): View
    {
        $templates = PatternTemplate::query()
            ->availableTo(auth()->user()->workshop_id)
            ->with('garmentType')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $proposal = $request->session()->get('designProposal');

        return view('design-import.create', [
            'templates' => $templates,
            'garmentTypes' => GarmentType::active()->orderBy('sort')->get(),
            'customers' => Customer::query()->with('defaultMeasurementSet')->orderBy('name')->get(),
            'sizes' => Measurements::sizes(),
            'maxImageKb' => self::MAX_IMAGE_KB,
            'proposal' => $proposal,
            'config' => [
                'maxImageBytes' => self::MAX_IMAGE_KB * 1024,
                'strokes' => $request->session()->get('designStrokes', []),
                'tab' => $proposal['source'] ?? 'photo',
            ],
        ]);
    }

    /** تحلیل عکس لباس. */
    public function photo(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_IMAGE_KB],
            'sensitivity' => ['nullable', 'numeric', 'between:0.5,1.6'],
        ], [], ['photo' => 'عکس لباس', 'sensitivity' => 'حساسیت جداسازی']);

        $path = $request->file('photo')->store('design-imports', 'public');

        try {
            $proposal = $this->images->analyze(Storage::disk('public')->path($path), [
                'sensitivity' => (float) ($request->input('sensitivity') ?: 1.0),
                'workshop_id' => auth()->user()->workshop_id,
                'image_url' => Storage::disk('public')->url($path),
                'image_path' => $path,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            return back()->with('error', 'خواندن این عکس ممکن نشد: '.$exception->getMessage());
        }

        return redirect()
            ->route('design-import.create')
            ->with('designProposal', $proposal)
            ->with('status', 'عکس خوانده شد؛ نتیجه اندازه‌گیری پایین همین صفحه است.');
    }

    /**
     * تحلیل طرح دستی از روی نقطه‌های قلم.
     *
     * نقطه‌ها به شکل JSON در یک فیلد پنهان می‌آیند (نه تصویر بوم): مختصات دقیق قلم
     * را داریم و تحلیل دوباره پیکسل‌ها هیچ چیزی به آن اضافه نمی‌کند.
     */
    public function sketch(Request $request): RedirectResponse
    {
        $request->validate([
            'strokes' => ['required', 'string', 'max:400000'],
        ], [], ['strokes' => 'خط‌های طرح']);

        $strokes = json_decode((string) $request->input('strokes'), true);

        $validator = Validator::make(['strokes' => $strokes], [
            'strokes' => ['required', 'array', 'min:1', 'max:40'],
            'strokes.*' => ['required', 'array', 'min:3', 'max:2000'],
            'strokes.*.*.x' => ['required', 'numeric', 'between:-20000,20000'],
            'strokes.*.*.y' => ['required', 'numeric', 'between:-20000,20000'],
        ], [
            'strokes.required' => 'هنوز چیزی نکشیده‌اید.',
            'strokes.*.min' => 'هر خط دست‌کم به سه نقطه نیاز دارد.',
        ], ['strokes' => 'خط‌های طرح']);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'sketch')
                ->with('error', 'طرح خوانده نشد؛ دست‌کم یک خط بسته با سه نقطه لازم است.');
        }

        try {
            $proposal = $this->sketches->analyze($validator->validated()['strokes'], [
                'workshop_id' => auth()->user()->workshop_id,
            ]);
        } catch (Throwable $exception) {
            return back()->with('error', 'تحلیل طرح ممکن نشد: '.$exception->getMessage());
        }

        return redirect()
            ->route('design-import.create')
            ->with('designProposal', $proposal)
            ->with('designStrokes', $validator->validated()['strokes'])
            ->with('status', 'طرح خوانده شد؛ نتیجه اندازه‌گیری پایین همین صفحه است.');
    }

    /** پذیرش پیشنهاد و ساخت الگوی واقعی با تولیدکننده‌های موجود. */
    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pattern_template_id' => ['required', 'integer'],
            'garment_type_id' => ['nullable', 'integer', Rule::exists('garment_types', 'id')],
            'source' => ['nullable', Rule::in(['photo', 'sketch'])],
            'measurement_source' => ['nullable', Rule::in(['customer', 'size'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'base_size' => ['nullable', Rule::in(Measurements::sizes())],
            'name' => ['nullable', 'string', 'max:160'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'detected' => ['nullable', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
        ], [], [
            'pattern_template_id' => 'الگوی پایه',
            'garment_type_id' => 'نوع لباس',
            'base_size' => 'سایز',
            'name' => 'نام الگو',
        ]);

        $template = PatternTemplate::query()
            ->availableTo(auth()->user()->workshop_id)
            ->findOrFail($data['pattern_template_id']);

        [$measurements, $measurementSetId, $size] = $this->resolveMeasurements($data);

        $pattern = $this->builder->createPattern($template, [
            'name' => ($data['name'] ?? null) ?: $template->name_fa,
            'garment_type_id' => $data['garment_type_id'] ?? null,
            'base_size' => $size,
            'measurement_set_id' => $measurementSetId,
            'notes' => $this->provenance($data),
        ], [
            'measurements' => $measurements,
            'params' => $this->cleanParams($template, $data['params'] ?? []),
        ]);

        $source = ($data['source'] ?? 'photo') === 'sketch' ? 'طرح دستی' : 'عکس لباس';

        return redirect()
            ->route('patterns.show', $pattern)
            ->with('status', 'الگو از روی '.$source.' ساخته شد: '.$pattern->pieces->count()
                .' قطعه آماده است. اندازه‌ها و جزئیات را بازبینی کنید.');
    }

    /** یادداشت منشأ الگو، تا بعداً معلوم باشد این الگو از کجا شروع شده است. */
    protected function provenance(array $data): string
    {
        $source = ($data['source'] ?? 'photo') === 'sketch' ? 'طرح دستی' : 'عکس لباس';
        $note = 'این الگو از «'.$source.'» شروع شده است.';

        if (! empty($data['detected'])) {
            $note .= ' تشخیص خودکار: '.$data['detected'];

            if (isset($data['confidence'])) {
                $note .= ' (اطمینان '.Format::percent(((float) $data['confidence']) * 100).')';
            }

            $note .= '.';
        }

        return $note.' تشخیص فقط یک نقطه شروع است؛ اندازه‌ها و جزئیات را خودتان بازبینی کنید.';
    }

    /**
     * اندازه‌ها از مشتری یا از جدول سایز استاندارد.
     *
     * @return array{0: array<string, float>, 1: int|null, 2: string}
     */
    protected function resolveMeasurements(array $data): array
    {
        $set = ($data['measurement_source'] ?? 'size') === 'customer' && ! empty($data['customer_id'])
            ? Customer::with('defaultMeasurementSet')->find($data['customer_id'])?->defaultMeasurementSet
            : null;

        if ($set !== null) {
            $measurements = $set->completed();
            $size = ($data['base_size'] ?? null) ?: ($set->base_size ?: Measurements::guessSize($measurements));

            return [$measurements, $set->id, (string) $size];
        }

        $size = (string) (($data['base_size'] ?? null) ?: '40');

        return [Measurements::fromSize($size), null, $size];
    }

    /**
     * فقط پارامترهایی که خود تولیدکننده می‌شناسد، و در محدوده مجازشان.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function cleanParams(PatternTemplate $template, array $params): array
    {
        $schema = $template->params_schema ?? [];
        $clean = [];

        foreach ($params as $key => $value) {
            $rule = $schema[$key] ?? null;

            if ($rule === null || $value === null || $value === '') {
                continue;
            }

            if (($rule['type'] ?? null) === 'toggle') {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $clean[$key] = round(max((float) ($rule['min'] ?? -1e6), min((float) ($rule['max'] ?? 1e6), (float) $value)), 2);
        }

        return $clean;
    }
}
