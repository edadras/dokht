<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workshop_id', 'order_id', 'amount', 'method', 'paid_at', 'note'])]
class Payment extends Model
{
    use BelongsToWorkshop, HasFactory, RecordsActivity;

    public const METHODS = [
        'cash' => 'نقدی',
        'card' => 'کارت‌خوان',
        'transfer' => 'کارت به کارت / انتقال',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function methodLabel(): string
    {
        return static::METHODS[$this->method] ?? $this->method;
    }
}
