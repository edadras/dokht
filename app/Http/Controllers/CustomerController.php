<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\Customer;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** دفتر مشتری‌های کارگاه. */
class CustomerController extends Controller
{
    use HandlesPersianInput;

    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->search((string) Jalali::latinDigits((string) $request->query('q')))
            ->with('defaultMeasurementSet')
            ->withCount(['orders', 'measurementSets'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'q' => $request->query('q'),
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->data($request));

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'مشتری «'.$customer->name.'» ثبت شد. حالا می‌توانید اندازه‌هایش را بگیرید.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'measurementSets',
            'projects' => fn ($query) => $query->limit(12),
        ]);

        $orders = $customer->orders()
            ->with(['payments', 'assignee'])
            ->orderByDesc('id')
            ->get();

        return view('customers.show', [
            'customer' => $customer,
            'orders' => $orders,
            'totals' => [
                'ordered' => (float) $orders->sum('price'),
                'paid' => (float) $orders->sum(fn ($order) => $order->paidAmount()),
                'remaining' => (float) $orders->sum(fn ($order) => $order->remainingAmount()),
            ],
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->data($request));

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'اطلاعات مشتری ذخیره شد.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $name = $customer->name;
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', 'مشتری «'.$name.'» حذف شد.');
    }

    /** اعتبارسنجی و آماده‌سازی داده مشتری؛ فقط نام لازم است. */
    protected function data(Request $request): array
    {
        $this->normalizeDigits($request, ['phone', 'birth_date']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::in(array_keys(Customer::GENDERS))],
            'birth_date' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'نام مشتری',
            'phone' => 'شماره تماس',
            'email' => 'ایمیل',
            'gender' => 'جنسیت',
            'birth_date' => 'تاریخ تولد',
            'notes' => 'یادداشت',
        ]);

        $data['gender'] = $data['gender'] ?? 'female';
        $data['phone'] = $this->numeric($data['phone'] ?? null) ?: null;
        $data['birth_date'] = $this->jalaliDate($data['birth_date'] ?? null);

        return $data;
    }
}
