<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ردیف‌های سفارش: پارچه، متعلقات و خدمات (صورت‌حساب مواد). */
#[Fillable([
    'order_id', 'kind', 'fabric_id', 'material_id', 'description',
    'quantity', 'unit', 'unit_price',
])]
class OrderItem extends Model
{
    use HasFactory;

    public const KINDS = [
        'fabric' => 'پارچه',
        'material' => 'متعلقات',
        'service' => 'خدمات',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function kindLabel(): string
    {
        return static::KINDS[$this->kind] ?? $this->kind;
    }

    public function total(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
