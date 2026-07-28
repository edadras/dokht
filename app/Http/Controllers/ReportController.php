<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GroupsByJalaliMonth;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * گزارش‌های ساده و صادق کارگاه.
 *
 * گروه‌بندی تاریخ‌ها در PHP انجام می‌شود تا به توابع تاریخ MySQL وابسته نباشیم.
 */
class ReportController extends Controller
{
    use GroupsByJalaliMonth;

    public function __invoke(): View
    {
        $orders = Order::query()->with(['customer', 'payments'])->get();
        $payments = Payment::query()->get();

        $ordersByMonth = $orders->groupBy(fn (Order $order) => $this->jalaliMonthKey($order->created_at));
        $paymentsByMonth = $payments->groupBy(fn (Payment $payment) => $this->jalaliMonthKey($payment->paid_at));

        $months = $this->recentJalaliMonths(6)->map(function (array $month) use ($ordersByMonth, $paymentsByMonth) {
            $monthOrders = $ordersByMonth->get($month['key'], collect());

            return [
                'label' => $month['label'],
                'orders' => $monthOrders->count(),
                'revenue' => (float) $monthOrders->sum('deposit')
                    + (float) $paymentsByMonth->get($month['key'], collect())->sum('amount'),
            ];
        });

        return view('reports.index', [
            'months' => $months,
            'maxOrders' => max(1, (int) $months->max('orders')),
            'maxRevenue' => max(1, (float) $months->max('revenue')),
            'statuses' => $this->statusBreakdown($orders),
            'topCustomers' => $this->topCustomers($orders),
            'topFabrics' => $this->topFabrics($orders),
            'deliveryDays' => $this->averageDeliveryDays($orders),
            'balances' => $this->balances($orders),
            'hasData' => $orders->isNotEmpty(),
        ]);
    }

    /** شمار سفارش در هر وضعیت. */
    protected function statusBreakdown(Collection $orders): Collection
    {
        return collect(Order::STATUSES)->map(fn (array $meta, string $status) => [
            'label' => $meta['label'],
            'color' => $meta['color'],
            'count' => $orders->where('status', $status)->count(),
            'status' => $status,
        ])->values();
    }

    /** مشتری‌هایی که بیشترین سفارش و خرید را داشته‌اند. */
    protected function topCustomers(Collection $orders): Collection
    {
        return $orders
            ->filter(fn (Order $order) => $order->customer !== null)
            ->groupBy('customer_id')
            ->map(fn (Collection $group) => [
                'customer' => $group->first()->customer,
                'orders' => $group->count(),
                'amount' => (float) $group->sum('price'),
                'paid' => (float) $group->sum(fn (Order $order) => $order->paidAmount()),
            ])
            ->sortByDesc('amount')
            ->take(8)
            ->values();
    }

    /** پارچه‌هایی که بیشترین متراژ از آن‌ها مصرف شده. */
    protected function topFabrics(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->where('kind', 'fabric')
            ->whereNotNull('fabric_id')
            ->with('fabric')
            ->get()
            ->groupBy('fabric_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->fabric?->displayName() ?? $group->first()->description,
                'meters' => (float) $group->sum('quantity'),
                'amount' => (float) $group->sum(fn (OrderItem $item) => $item->total()),
            ])
            ->sortByDesc('meters')
            ->take(8)
            ->values();
    }

    /** میانگین روزهای میان ثبت سفارش و تحویل آن. */
    protected function averageDeliveryDays(Collection $orders): ?float
    {
        $delivered = $orders
            ->filter(fn (Order $order) => $order->delivered_at !== null && $order->created_at !== null)
            ->map(fn (Order $order) => max(0, $order->created_at->diffInDays($order->delivered_at)));

        return $delivered->isEmpty() ? null : round((float) $delivered->avg(), 1);
    }

    /** سفارش‌هایی که هنوز مانده حساب دارند. */
    protected function balances(Collection $orders): array
    {
        $open = $orders
            ->reject(fn (Order $order) => $order->status === 'cancelled')
            ->filter(fn (Order $order) => $order->remainingAmount() > 0)
            ->sortByDesc(fn (Order $order) => $order->remainingAmount())
            ->values();

        return [
            'total' => (float) $open->sum(fn (Order $order) => $order->remainingAmount()),
            'count' => $open->count(),
            'rows' => $open->take(10),
        ];
    }
}
