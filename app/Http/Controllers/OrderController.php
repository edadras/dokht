<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Material;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** سفارش‌های کارگاه و تخته تولید. */
class OrderController extends Controller
{
    use HandlesPersianInput;

    public function index(Request $request): View
    {
        if ($request->query('view') === 'list') {
            return $this->listView($request);
        }

        $orders = Order::query()
            ->with(['customer', 'assignee', 'payments'])
            ->orderBy('position')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $columns = [];

        foreach (Order::STATUSES as $status => $meta) {
            $columns[$status] = $orders->where('status', $status)->values();
        }

        return view('orders.index', [
            'columns' => $columns,
            'total' => $orders->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('orders.create', [
            'order' => new Order(['status' => 'new']),
            'customers' => $this->customerOptions(),
            'projects' => $this->projectOptions(),
            'members' => $this->memberOptions(),
            'selectedCustomer' => $request->query('customer'),
            'selectedProject' => $request->query('project'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);

        $order = new Order($data);
        $order->code = Order::nextCode((int) auth()->user()->workshop_id);
        $order->save();

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'سفارش '.Jalali::digits($order->code).' ثبت شد.');
    }

    public function show(Order $order): View
    {
        $order->load([
            'customer.defaultMeasurementSet',
            'project.pattern',
            'assignee',
            'items.fabric',
            'items.material',
            'payments',
        ]);

        return view('orders.show', [
            'order' => $order,
            'fabrics' => Fabric::query()->orderBy('name')->get(),
            'materials' => Material::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Order $order): View
    {
        return view('orders.edit', [
            'order' => $order,
            'customers' => $this->customerOptions(),
            'projects' => $this->projectOptions(),
            'members' => $this->memberOptions(),
            'selectedCustomer' => $order->customer_id,
            'selectedProject' => $order->project_id,
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $order->update($this->data($request, $order));

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'سفارش به‌روز شد.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $code = $order->code;
        $order->delete();

        return redirect()
            ->route('orders.index')
            ->with('status', 'سفارش '.Jalali::digits($code).' حذف شد.');
    }

    /** جابه‌جایی سفارش میان ستون‌های تخته تولید. */
    public function updateStatus(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'position' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ], [], ['status' => 'وضعیت']);

        $order->status = $data['status'];
        $order->position = (int) ($data['position'] ?? $order->position);

        if ($data['status'] === 'delivered') {
            $order->delivered_at ??= now();
        } else {
            $order->delivered_at = null;
        }

        $order->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', 'سفارش به «'.$order->statusLabel().'» منتقل شد.');
    }

    /** فاکتور قابل چاپ. */
    public function invoice(Order $order): View
    {
        $order->load(['customer', 'items.fabric', 'items.material', 'payments']);

        return view('orders.invoice', [
            'order' => $order,
            'workshop' => auth()->user()->workshop,
        ]);
    }

    /** نمای جدولی با مرتب‌سازی و پالایه. */
    protected function listView(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['due_date', 'code', 'price', 'status'], true)
            ? $request->query('sort')
            : 'due_date';

        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = Order::query()
            ->with(['customer', 'assignee', 'payments'])
            ->search((string) Jalali::latinDigits((string) $request->query('q')));

        if (array_key_exists((string) $request->query('status'), Order::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->boolean('overdue')) {
            $query->open()->whereDate('due_date', '<', now()->toDateString());
        }

        if ($request->filled('customer')) {
            $query->where('customer_id', (int) $request->query('customer'));
        }

        return view('orders.list', [
            'orders' => $query->orderBy($sort, $direction)->paginate(25)->withQueryString(),
            'customers' => $this->customerOptions(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /** اعتبارسنجی سفارش: مشتری + عنوان + تاریخ تحویل کافی است. */
    protected function data(Request $request, ?Order $order = null): array
    {
        $this->normalizeDigits($request, ['due_date']);
        $this->normalizeNumbers($request, ['price', 'deposit']);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'new_customer_name' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
            'due_date' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'project_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'customer_id' => 'مشتری',
            'new_customer_name' => 'نام مشتری تازه',
            'title' => 'عنوان سفارش',
            'due_date' => 'تاریخ تحویل',
            'price' => 'مبلغ کل',
            'deposit' => 'بیعانه',
        ]);

        $customerId = $this->resolveCustomer($validated) ?? $order?->customer_id;

        if ($customerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'مشتری را انتخاب کنید یا نام مشتری تازه را بنویسید.',
            ]);
        }

        $dueDate = null;

        if (filled($validated['due_date'] ?? null)) {
            $dueDate = $this->jalaliDate($validated['due_date']);

            if ($dueDate === null) {
                throw ValidationException::withMessages([
                    'due_date' => 'تاریخ تحویل را به شکل ۱۴۰۵/۰۵/۱۲ بنویسید.',
                ]);
            }
        }

        return [
            'customer_id' => $customerId,
            'title' => $validated['title'],
            'status' => $validated['status'] ?? $order?->status ?? 'new',
            'due_date' => $dueDate,
            'price' => $this->amount($validated['price'] ?? 0),
            'deposit' => $this->amount($validated['deposit'] ?? 0),
            'project_id' => $this->existingId(Project::class, $validated['project_id'] ?? null),
            'assigned_to' => $this->existingMemberId($validated['assigned_to'] ?? null),
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /** مشتری انتخاب‌شده یا مشتری تازه‌ای که نامش همان‌جا تایپ شده. */
    protected function resolveCustomer(array $validated): ?int
    {
        $id = $this->existingId(Customer::class, $validated['customer_id'] ?? null);

        if ($id !== null) {
            return $id;
        }

        $name = trim((string) ($validated['new_customer_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return Customer::create(['name' => $name])->id;
    }

    /** @param  class-string<Model>  $model */
    protected function existingId(string $model, mixed $id): ?int
    {
        if (! filled($id)) {
            return null;
        }

        return $model::query()->whereKey($id)->value('id');
    }

    protected function existingMemberId(mixed $id): ?int
    {
        if (! filled($id)) {
            return null;
        }

        return User::query()
            ->where('workshop_id', auth()->user()->workshop_id)
            ->whereKey($id)
            ->value('id');
    }

    protected function customerOptions(): array
    {
        return Customer::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function projectOptions(): array
    {
        return Project::query()->orderByDesc('id')->limit(100)->pluck('name', 'id')->all();
    }

    protected function memberOptions(): array
    {
        return User::query()
            ->where('workshop_id', auth()->user()->workshop_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
