<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** سفارش دوخت؛ کار روزمره کارگاه. */
#[Fillable([
    'workshop_id', 'customer_id', 'project_id', 'assigned_to', 'code', 'title',
    'status', 'due_date', 'price', 'deposit', 'position', 'notes', 'delivered_at',
])]
class Order extends Model
{
    use BelongsToWorkshop, HasFactory, SoftDeletes;

    /** مراحل کار روی تخته سفارش‌ها، به ترتیب. */
    public const STATUSES = [
        'new' => ['label' => 'سفارش نو', 'color' => 'slate'],
        'cutting' => ['label' => 'برش', 'color' => 'amber'],
        'sewing' => ['label' => 'دوخت', 'color' => 'sky'],
        'fitting' => ['label' => 'پرو', 'color' => 'violet'],
        'ready' => ['label' => 'آماده تحویل', 'color' => 'emerald'],
        'delivered' => ['label' => 'تحویل شد', 'color' => 'gray'],
        'cancelled' => ['label' => 'لغو شد', 'color' => 'rose'],
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'delivered_at' => 'datetime',
            'price' => 'float',
            'deposit' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")
            ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")));
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return static::STATUSES[$this->status]['color'] ?? 'slate';
    }

    /** مجموع پرداخت‌های ثبت‌شده به‌همراه بیعانه. */
    public function paidAmount(): float
    {
        return round((float) $this->deposit + (float) $this->payments->sum('amount'), 2);
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->price - $this->paidAmount()), 2);
    }

    public function itemsTotal(): float
    {
        return round($this->items->sum(fn (OrderItem $item) => $item->total()), 2);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, ['delivered', 'cancelled'], true);
    }

    /** شماره سفارش بعدی کارگاه: ۱۴۰۵-۰۰۱ */
    public static function nextCode(int $workshopId): string
    {
        $year = (int) date('Y');
        [$jy] = Jalali::fromGregorian($year, (int) date('n'), (int) date('j'));

        $count = static::withoutGlobalScopes()
            ->withTrashed()
            ->where('workshop_id', $workshopId)
            ->where('code', 'like', $jy.'-%')
            ->count();

        return $jy.'-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
