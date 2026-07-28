<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\Customer;
use App\Models\MeasurementSet;
use App\Support\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * دفترچه اندازه.
 *
 * فرم اصلی فقط شش عدد ضروری را می‌پرسد؛ باقی اندازه‌ها اختیاری‌اند و اگر خالی
 * بمانند هنگام ساخت الگو با Measurements::complete() تخمین زده می‌شوند.
 */
class MeasurementSetController extends Controller
{
    use HandlesPersianInput;

    public function create(Request $request, Customer $customer): View
    {
        $previous = $customer->measurementSets()->first();

        // شروع از مجموعه پیشین یا از یک سایز استاندارد
        $values = [];

        if ($request->filled('copy') && $previous) {
            $copy = $customer->measurementSets()->whereKey($request->query('copy'))->first() ?? $previous;
            $values = $copy->values ?? [];
        } elseif ($request->filled('size')) {
            $values = Measurements::SIZE_TABLE[(string) $request->query('size')] ?? [];
        }

        return view('measurements.create', [
            'customer' => $customer,
            'set' => null,
            'values' => Measurements::clean($values),
            'previous' => $previous,
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->data($request);

        $set = new MeasurementSet([
            'customer_id' => $customer->id,
            'title' => $data['title'],
            'unit' => 'cm',
            'base_size' => $data['base_size'],
            'values' => $data['values'],
            'notes' => $data['notes'],
        ]);

        $set->save();

        $this->keepSingleDefault($set, $data['is_default']);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'اندازه‌ها ثبت شد. سایز پیشنهادی: '.$data['base_size']);
    }

    public function edit(MeasurementSet $measurementSet): View
    {
        abort_if($measurementSet->customer === null, 404);

        return view('measurements.edit', [
            'customer' => $measurementSet->customer,
            'set' => $measurementSet,
            'values' => Measurements::clean($measurementSet->values ?? []),
            'previous' => $measurementSet->customer
                ->measurementSets()
                ->whereKeyNot($measurementSet->id)
                ->first(),
        ]);
    }

    public function update(Request $request, MeasurementSet $measurementSet): RedirectResponse
    {
        abort_if($measurementSet->customer === null, 404);

        $data = $this->data($request);

        $measurementSet->update([
            'title' => $data['title'],
            'base_size' => $data['base_size'],
            'values' => $data['values'],
            'notes' => $data['notes'],
        ]);

        $this->keepSingleDefault($measurementSet, $data['is_default']);

        return redirect()
            ->route('customers.show', $measurementSet->customer)
            ->with('status', 'اندازه‌ها به‌روز شد.');
    }

    public function destroy(MeasurementSet $measurementSet): RedirectResponse
    {
        $customer = $measurementSet->customer;

        abort_if($customer === null, 404);

        $wasDefault = $measurementSet->is_default;
        $measurementSet->delete();

        if ($wasDefault) {
            $next = $customer->measurementSets()->first();
            $next?->forceFill(['is_default' => true])->save();
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'این مجموعه اندازه حذف شد.');
    }

    /** اعتبارسنجی هر اندازه با کمینه و بیشینه خودش. */
    protected function data(Request $request): array
    {
        $this->normalizeNumbers($request, ['values']);

        $rules = [
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'base_size' => ['nullable', Rule::in(Measurements::sizes())],
            'is_default' => ['nullable', 'boolean'],
        ];

        $names = [];

        foreach (Measurements::FIELDS as $key => $field) {
            $rules['values.'.$key] = array_filter([
                ($field['essential'] ?? false) ? 'required' : 'nullable',
                'numeric',
                'min:'.$field['min'],
                'max:'.$field['max'],
            ]);

            $names['values.'.$key] = $field['label'];
        }

        $validated = $request->validate($rules, [], $names + [
            'title' => 'عنوان',
            'notes' => 'یادداشت',
            'base_size' => 'سایز پایه',
        ]);

        // فقط اندازه‌های وارد‌شده ذخیره می‌شوند؛ تخمین‌ها در دیتابیس نمی‌نشینند.
        $values = Measurements::clean($validated['values'] ?? []);

        return [
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'values' => $values,
            'base_size' => $validated['base_size'] ?? Measurements::guessSize($values),
            'is_default' => (bool) ($validated['is_default'] ?? false),
        ];
    }

    /** برای هر مشتری دقیقاً یک مجموعه پیش‌فرض باقی می‌ماند. */
    protected function keepSingleDefault(MeasurementSet $set, bool $wanted): void
    {
        $siblings = fn () => MeasurementSet::query()
            ->where('customer_id', $set->customer_id)
            ->whereKeyNot($set->id);

        if ($wanted || $siblings()->doesntExist()) {
            $siblings()->update(['is_default' => false]);
            $set->forceFill(['is_default' => true])->save();

            return;
        }

        $set->forceFill(['is_default' => false])->save();

        if ($siblings()->where('is_default', true)->doesntExist()) {
            $siblings()->orderByDesc('id')->first()?->forceFill(['is_default' => true])->save();
        }
    }
}
