<?php

namespace App\Models;

use App\Support\Format;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سفارش خرید یک آگهی بازارچه.
 *
 * وضعیت‌ها به ترتیب: pending (سفارش ثبت شد) → paid (فروشنده دریافت وجه را تأیید کرد)
 * → delivered (خریدار نسخه خودش را برداشت). cancelled هم در هر نقطه پیش از تحویل
 * ممکن است. هیچ‌کدام از این‌ها به معنای جابه‌جایی پول در سامانه نیست؛ پرداخت بیرون
 * از سامانه انجام می‌شود و اینجا فقط ثبت می‌شود که فروشنده آن را تأیید کرده است.
 *
 * این مدل هم عمداً به کارگاه فعال محدود نمی‌شود: هر ردیف دو طرف دارد (خریدار و
 * فروشنده) و هر پرس‌وجو صریح می‌گوید طرفِ کدام است.
 */
#[Fillable([
    'pattern_listing_id', 'seller_workshop_id', 'buyer_workshop_id', 'buyer_user_id',
    'delivered_pattern_id', 'price', 'currency', 'status', 'ordered_at', 'paid_at',
    'delivered_at', 'cancelled_at', 'seller_note', 'buyer_note',
])]
class PatternPurchase extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    /** وضعیت‌های سفارش با برچسب فارسی و رنگ نشان. */
    public const STATUSES = [
        self::PENDING => ['label' => 'در انتظار تأیید فروشنده', 'color' => 'amber'],
        self::PAID => ['label' => 'پرداخت تأییدشده', 'color' => 'sky'],
        self::DELIVERED => ['label' => 'تحویل شد', 'color' => 'emerald'],
        self::CANCELLED => ['label' => 'لغو شد', 'color' => 'rose'],
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** آگهی، حتی اگر فروشنده بعداً آن را برداشته باشد (تاریخچه نباید ناقص شود). */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(PatternListing::class, 'pattern_listing_id')->withTrashed();
    }

    public function sellerWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'seller_workshop_id');
    }

    public function buyerWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'buyer_workshop_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /** نسخه‌ای که در کارگاه خریدار ساخته شد. */
    public function deliveredPattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class, 'delivered_pattern_id');
    }

    public function scopeOfBuyer(Builder $query, ?int $workshopId): Builder
    {
        return $query->where('buyer_workshop_id', $workshopId);
    }

    public function scopeOfSeller(Builder $query, ?int $workshopId): Builder
    {
        return $query->where('seller_workshop_id', $workshopId);
    }

    /** سفارش‌هایی که هنوز زنده‌اند (لغو نشده‌اند). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::CANCELLED);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function isSeller(?int $workshopId): bool
    {
        return $workshopId !== null && $this->seller_workshop_id === $workshopId;
    }

    public function isBuyer(?int $workshopId): bool
    {
        return $workshopId !== null && $this->buyer_workshop_id === $workshopId;
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return static::STATUSES[$this->status]['color'] ?? 'slate';
    }

    public function priceLabel(): string
    {
        return $this->price > 0
            ? Format::money($this->price, $this->currency ?: 'تومان')
            : 'رایگان';
    }
}
