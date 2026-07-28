<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Pattern;
use App\Models\Project;
use App\Support\Format;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** جست‌وجوی یکجا در مشتری‌ها، سفارش‌ها، پروژه‌ها، الگوها و پارچه‌ها. */
class SearchController extends Controller
{
    protected const LIMIT = 8;

    public function __invoke(Request $request): View
    {
        $raw = trim((string) $request->query('q'));
        $term = trim((string) Jalali::latinDigits($raw));

        return view('search', [
            'q' => $raw,
            'groups' => $term === '' ? $this->recent() : $this->results($term),
            'isSearching' => $term !== '',
        ]);
    }

    /** نتیجه جست‌وجو، گروه‌بندی‌شده. */
    protected function results(string $term): array
    {
        return [
            'customers' => [
                'title' => 'مشتری‌ها',
                'icon' => 'users',
                'items' => Customer::query()->search($term)
                    ->withCount('orders')
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Customer $c) => [
                        'title' => $c->name,
                        'meta' => $c->phone ? Jalali::digits($c->phone) : $c->genderLabel(),
                        'url' => route('customers.show', $c),
                    ])->all(),
            ],
            'orders' => [
                'title' => 'سفارش‌ها',
                'icon' => 'clipboard',
                'items' => Order::query()->search($term)
                    ->with('customer')
                    ->orderByDesc('id')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Order $o) => [
                        'title' => Jalali::digits($o->code).' — '.$o->title,
                        'meta' => ($o->customer?->name ?? 'بدون مشتری').' • '.$o->statusLabel(),
                        'url' => route('orders.show', $o),
                    ])->all(),
            ],
            'projects' => [
                'title' => 'پروژه‌ها',
                'icon' => 'shirt',
                'items' => Project::query()
                    ->where('name', 'like', "%{$term}%")
                    ->with('customer')
                    ->orderByDesc('id')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Project $p) => [
                        'title' => $p->name,
                        'meta' => ($p->customer?->name ?? '—').' • '.$p->statusLabel(),
                        'url' => route('projects.show', $p),
                    ])->all(),
            ],
            'patterns' => [
                'title' => 'الگوها',
                'icon' => 'scissors',
                'items' => Pattern::query()->search($term)
                    ->orderByDesc('id')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Pattern $p) => [
                        'title' => $p->name,
                        'meta' => 'سایز '.Jalali::digits((string) $p->base_size),
                        'url' => route('patterns.show', $p),
                    ])->all(),
            ],
            'fabrics' => [
                'title' => 'پارچه‌ها',
                'icon' => 'layers',
                'items' => Fabric::query()->search($term)
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get()
                    ->map(fn (Fabric $f) => [
                        'title' => $f->displayName(),
                        'meta' => Format::number($f->stock_meters, 1).' متر موجودی',
                        'url' => route('fabrics.show', $f),
                    ])->all(),
            ],
        ];
    }

    /** وقتی چیزی جست‌وجو نشده، تازه‌ترین‌ها را نشان می‌دهیم. */
    protected function recent(): array
    {
        return [
            'orders' => [
                'title' => 'تازه‌ترین سفارش‌ها',
                'icon' => 'clipboard',
                'items' => Order::query()->with('customer')->orderByDesc('id')->limit(self::LIMIT)->get()
                    ->map(fn (Order $o) => [
                        'title' => Jalali::digits($o->code).' — '.$o->title,
                        'meta' => $o->customer?->name ?? 'بدون مشتری',
                        'url' => route('orders.show', $o),
                    ])->all(),
            ],
            'customers' => [
                'title' => 'تازه‌ترین مشتری‌ها',
                'icon' => 'users',
                'items' => Customer::query()->orderByDesc('id')->limit(self::LIMIT)->get()
                    ->map(fn (Customer $c) => [
                        'title' => $c->name,
                        'meta' => $c->phone ? Jalali::digits($c->phone) : $c->genderLabel(),
                        'url' => route('customers.show', $c),
                    ])->all(),
            ],
            'projects' => [
                'title' => 'تازه‌ترین پروژه‌ها',
                'icon' => 'shirt',
                'items' => Project::query()->orderByDesc('id')->limit(self::LIMIT)->get()
                    ->map(fn (Project $p) => [
                        'title' => $p->name,
                        'meta' => $p->statusLabel(),
                        'url' => route('projects.show', $p),
                    ])->all(),
            ],
        ];
    }
}
