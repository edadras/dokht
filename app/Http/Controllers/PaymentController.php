<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ثبت پرداخت‌های مشتری روی یک سفارش. */
class PaymentController extends Controller
{
    use HandlesPersianInput;

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->normalizeNumbers($request, ['amount']);
        $this->normalizeDigits($request, ['paid_at']);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_at' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], [
            'amount' => 'مبلغ',
            'method' => 'روش پرداخت',
            'paid_at' => 'تاریخ پرداخت',
            'note' => 'توضیح',
        ]);

        $payment = $order->payments()->create([
            'amount' => $this->amount($data['amount']),
            'method' => $data['method'],
            'paid_at' => $this->jalaliDate($data['paid_at'] ?? null) ?? now()->startOfDay(),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'پرداخت '.Format::money($payment->amount).' ثبت شد.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $order = $payment->order;

        abort_if($order === null, 404);

        $payment->delete();

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'پرداخت حذف شد.');
    }
}
