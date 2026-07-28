<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\Fabric;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ردیف‌های بیل مواد سفارش: پارچه، متعلقات و خدمات. */
class OrderItemController extends Controller
{
    use HandlesPersianInput;

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->normalizeNumbers($request, ['quantity', 'unit_price']);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(OrderItem::KINDS))],
            'fabric_id' => ['nullable', 'integer'],
            'material_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:200'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'unit' => ['nullable', 'string', 'max:16'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ], [], [
            'kind' => 'نوع ردیف',
            'description' => 'شرح',
            'quantity' => 'مقدار',
            'unit' => 'یکا',
            'unit_price' => 'قیمت واحد',
        ]);

        $item = [
            'kind' => $data['kind'],
            'quantity' => max(0.01, $this->amount($data['quantity'] ?? 1) ?: 1),
            'unit' => $data['unit'] ?? 'عدد',
            'unit_price' => $this->amount($data['unit_price'] ?? 0),
            'description' => trim((string) ($data['description'] ?? '')),
            'fabric_id' => null,
            'material_id' => null,
        ];

        if ($data['kind'] === 'fabric') {
            $fabric = Fabric::query()->whereKey($data['fabric_id'] ?? null)->first();

            if ($fabric === null) {
                return back()->with('error', 'پارچه را از فهرست پارچه‌های کارگاه انتخاب کنید.');
            }

            $item['fabric_id'] = $fabric->id;
            $item['unit'] = 'متر';
            $item['description'] = $item['description'] ?: $fabric->displayName();

            if ($item['unit_price'] <= 0) {
                $item['unit_price'] = (float) $fabric->price_per_meter;
            }
        }

        if ($data['kind'] === 'material') {
            $material = Material::query()->whereKey($data['material_id'] ?? null)->first();

            if ($material === null) {
                return back()->with('error', 'متعلقات را از فهرست کارگاه انتخاب کنید.');
            }

            $item['material_id'] = $material->id;
            $item['unit'] = $material->unit ?: 'عدد';
            $item['description'] = $item['description'] ?: $material->name;

            if ($item['unit_price'] <= 0) {
                $item['unit_price'] = (float) $material->price;
            }
        }

        if ($item['description'] === '') {
            return back()->with('error', 'شرح ردیف را بنویسید.');
        }

        $order->items()->create($item);

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'ردیف «'.$item['description'].'» به سفارش اضافه شد.');
    }

    public function destroy(OrderItem $orderItem): RedirectResponse
    {
        $order = $orderItem->order;

        abort_if($order === null, 404);

        $orderItem->delete();

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'ردیف سفارش حذف شد.');
    }
}
