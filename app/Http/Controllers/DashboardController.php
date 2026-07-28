<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GroupsByJalaliMonth;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * نخستین صفحه‌ای که خیاط هر روز می‌بیند.
 *
 * هدف آرام بودن است: چند کار پرکاربرد، کارهای امروز و هفته، و خلاصه تخته تولید.
 */
class DashboardController extends Controller
{
    use GroupsByJalaliMonth;

    public function __invoke(): View
    {
        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(7);
        $month = $this->jalaliMonth();

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $customerCount = Customer::query()->count();
        $orderCount = (int) $statusCounts->sum();
        $projectCount = Project::query()->count();

        $income = (float) Payment::query()
            ->whereBetween('paid_at', [$month['start'], $month['end']])
            ->sum('amount');

        $income += (float) Order::query()
            ->whereBetween('created_at', [$month['start'], $month['end']])
            ->sum('deposit');

        return view('dashboard', [
            'workshop' => auth()->user()->workshop,
            'isFirstRun' => $customerCount === 0 && $orderCount === 0 && $projectCount === 0,
            'today' => $today,
            'month' => $month,
            'stats' => [
                'open' => (int) $statusCounts->except(['delivered', 'cancelled'])->sum(),
                'dueThisWeek' => Order::query()->open()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '>=', $today->toDateString())
                    ->whereDate('due_date', '<=', $weekEnd->toDateString())
                    ->count(),
                'overdue' => Order::query()->open()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today->toDateString())
                    ->count(),
                'income' => $income,
                'customers' => $customerCount,
                'fabricMeters' => (float) Fabric::query()->active()->sum('stock_meters'),
            ],
            'statusCounts' => $statusCounts,
            'tasks' => Order::query()->open()
                ->with(['customer', 'payments'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $weekEnd->toDateString())
                ->orderBy('due_date')
                ->limit(12)
                ->get(),
            'projects' => Project::query()->active()
                ->with('customer')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'lowFabrics' => Fabric::query()->active()
                ->where('stock_meters', '<', 2)
                ->orderBy('stock_meters')
                ->limit(6)
                ->get(),
        ]);
    }
}
