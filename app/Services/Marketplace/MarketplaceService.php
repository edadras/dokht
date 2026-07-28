<?php

namespace App\Services\Marketplace;

use App\Models\Pattern;
use App\Models\PatternListing;
use App\Models\PatternPurchase;
use Illuminate\Support\Facades\DB;

/**
 * قاعده‌های بازارچه الگو.
 *
 * پول در این سامانه جابه‌جا نمی‌شود. «paid» یعنی فروشنده گفته است وجه را بیرون از
 * سامانه گرفته‌ام؛ سامانه فقط این ادعا را با زمانش ثبت می‌کند. تحویل کالا هم یعنی
 * ساخته‌شدن یک نسخه مستقل از الگو در کارگاه خریدار.
 *
 * اجازه دسترسی (چه کسی حق چه کاری دارد) کار کنترلر است؛ اینجا فقط قاعده‌های خودِ
 * کسب‌وکار نگهبانی می‌شوند و در صورت شکستن، MarketplaceException پرتاب می‌شود.
 */
class MarketplaceService
{
    public function __construct(
        protected PatternDuplicator $duplicator = new PatternDuplicator,
        protected ListingPreview $preview = new ListingPreview,
    ) {}

    /** گذاشتن الگوی کارگاه برای فروش. */
    public function publish(Pattern $pattern, int $sellerWorkshopId, array $data): PatternListing
    {
        if ((int) $pattern->workshop_id !== $sellerWorkshopId) {
            throw new MarketplaceException('فقط می‌توانید الگوی کارگاه خودتان را برای فروش بگذارید.');
        }

        if ($this->activeListingFor($pattern) !== null) {
            throw new MarketplaceException('این الگو همین حالا یک آگهی فعال در بازارچه دارد.');
        }

        return PatternListing::create([
            'seller_workshop_id' => $sellerWorkshopId,
            'pattern_id' => $pattern->id,
            'garment_type_id' => $pattern->garment_type_id,
            'title' => trim($data['title'] ?? '') ?: $pattern->name,
            'description' => $data['description'] ?? null,
            'price' => (float) ($data['price'] ?? 0),
            'currency' => $data['currency'] ?? 'تومان',
            'is_active' => true,
            'preview' => $this->preview->build($pattern),
            'tags' => $this->tags($pattern),
        ]);
    }

    /** ویرایش آگهی؛ پیش‌نمایش هم دوباره ساخته می‌شود تا با الگوی امروز بخواند. */
    public function updateListing(PatternListing $listing, array $data): PatternListing
    {
        $activate = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : $listing->is_active;

        if ($activate && ! $listing->is_active) {
            $other = $this->activeListingFor($listing->pattern_id, $listing->id);

            if ($other !== null) {
                throw new MarketplaceException('برای این الگو آگهی فعال دیگری وجود دارد.');
            }
        }

        $pattern = $listing->pattern;

        $listing->fill(array_filter([
            'title' => $data['title'] ?? null,
            'price' => array_key_exists('price', $data) ? (float) $data['price'] : null,
        ], fn ($value) => $value !== null));

        $listing->description = $data['description'] ?? $listing->description;
        $listing->is_active = $activate;

        if ($pattern) {
            $listing->preview = $this->preview->build($pattern);
        }

        $listing->save();

        return $listing;
    }

    /** برداشتن آگهی از ویترین؛ سفارش‌های ثبت‌شده دست‌نخورده می‌مانند. */
    public function remove(PatternListing $listing): void
    {
        $listing->delete();
    }

    /** ثبت سفارش خرید؛ هنوز هیچ پولی و هیچ الگویی جابه‌جا نشده است. */
    public function order(PatternListing $listing, int $buyerWorkshopId, ?int $buyerUserId = null, ?string $note = null): PatternPurchase
    {
        if (! $listing->isVisible()) {
            throw new MarketplaceException('این آگهی دیگر روی ویترین بازارچه نیست.');
        }

        if ($listing->isOwnedBy($buyerWorkshopId)) {
            throw new MarketplaceException('این الگو خودِ کارگاه شماست؛ نیازی به خرید ندارد.');
        }

        if (($existing = $this->existingPurchase($listing, $buyerWorkshopId)) !== null) {
            throw new MarketplaceException(
                $existing->isDelivered()
                    ? 'نسخه‌ای از این الگو پیش‌تر به کارگاه شما تحویل شده است.'
                    : 'برای این الگو سفارش بازی دارید؛ همان را دنبال کنید.'
            );
        }

        return PatternPurchase::create([
            'pattern_listing_id' => $listing->id,
            'seller_workshop_id' => $listing->seller_workshop_id,
            'buyer_workshop_id' => $buyerWorkshopId,
            'buyer_user_id' => $buyerUserId,
            'price' => $listing->price,     // قیمت لحظه سفارش؛ تغییر بعدی آگهی روی آن اثر ندارد
            'currency' => $listing->currency,
            'status' => PatternPurchase::PENDING,
            'ordered_at' => now(),
            'buyer_note' => $note,
        ]);
    }

    /** فروشنده تأیید می‌کند که وجه را بیرون از سامانه گرفته است. */
    public function confirm(PatternPurchase $purchase, ?string $note = null): PatternPurchase
    {
        if (! $purchase->isPending()) {
            throw new MarketplaceException('فقط سفارشِ در انتظار تأیید را می‌توان تأیید کرد.');
        }

        $purchase->forceFill([
            'status' => PatternPurchase::PAID,
            'paid_at' => now(),
            'seller_note' => $note ?: $purchase->seller_note,
        ])->save();

        return $purchase;
    }

    /** لغو سفارش پیش از تحویل. */
    public function cancel(PatternPurchase $purchase, ?string $note = null): PatternPurchase
    {
        if ($purchase->isDelivered()) {
            throw new MarketplaceException('سفارش تحویل‌شده را نمی‌توان لغو کرد.');
        }

        if ($purchase->isCancelled()) {
            throw new MarketplaceException('این سفارش پیش‌تر لغو شده است.');
        }

        $purchase->forceFill([
            'status' => PatternPurchase::CANCELLED,
            'cancelled_at' => now(),
            'seller_note' => $note ?: $purchase->seller_note,
        ])->save();

        return $purchase;
    }

    /**
     * تحویل: ساخته‌شدن نسخه مستقل الگو در کارگاه خریدار.
     *
     * دوبار زدن دکمه، دو نسخه نمی‌سازد؛ اگر سفارش پیش‌تر تحویل شده باشد همان نسخه
     * برگردانده می‌شود.
     */
    public function deliver(PatternPurchase $purchase): Pattern
    {
        return DB::transaction(function () use ($purchase) {
            $fresh = PatternPurchase::whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isDelivered()) {
                $delivered = $this->deliveredPattern($fresh);

                if ($delivered !== null) {
                    $purchase->setRawAttributes($fresh->getAttributes(), true);

                    return $delivered;
                }

                throw new MarketplaceException('نسخه تحویل‌شده این سفارش دیگر در دسترس نیست.');
            }

            if (! $fresh->isPaid()) {
                throw new MarketplaceException($fresh->isCancelled()
                    ? 'این سفارش لغو شده است.'
                    : 'تا وقتی فروشنده دریافت وجه را تأیید نکند، نسخه الگو تحویل نمی‌شود.');
            }

            $listing = PatternListing::withTrashed()->find($fresh->pattern_listing_id);
            $source = $listing?->pattern;

            if ($source === null) {
                throw new MarketplaceException('الگوی این آگهی دیگر در دسترس نیست؛ با فروشنده تماس بگیرید.');
            }

            $copy = $this->duplicator->duplicate(
                $source,
                (int) $fresh->buyer_workshop_id,
                'خرید از بازارچه («'.$listing->title.'» از '.($listing->sellerWorkshop?->name ?? 'کارگاه فروشنده').')',
                $fresh->buyer_user_id,
            );

            $fresh->forceFill([
                'status' => PatternPurchase::DELIVERED,
                'delivered_at' => now(),
                'delivered_pattern_id' => $copy->id,
            ])->save();

            if ($listing !== null) {
                PatternListing::withTrashed()->whereKey($listing->id)->increment('sales_count');
            }

            $purchase->setRawAttributes($fresh->getAttributes(), true);

            return $copy;
        });
    }

    /** آگهی فعالِ یک الگو، اگر وجود داشته باشد. */
    public function activeListingFor(Pattern|int $pattern, ?int $exceptListingId = null): ?PatternListing
    {
        return PatternListing::query()
            ->active()
            ->where('pattern_id', $pattern instanceof Pattern ? $pattern->id : $pattern)
            ->when($exceptListingId !== null, fn ($q) => $q->whereKeyNot($exceptListingId))
            ->first();
    }

    /**
     * سفارش زنده کارگاه خریدار برای همین الگو (از هر آگهی‌ای که باشد).
     *
     * ملاک، الگوست نه آگهی: اگر فروشنده آگهی را بردارد و دوباره بگذارد، خریداری که
     * نسخه‌اش را گرفته دوباره پول نمی‌دهد.
     */
    public function existingPurchase(PatternListing $listing, int $buyerWorkshopId): ?PatternPurchase
    {
        $listingIds = PatternListing::withTrashed()
            ->where('pattern_id', $listing->pattern_id)
            ->pluck('id');

        return PatternPurchase::query()
            ->ofBuyer($buyerWorkshopId)
            ->live()
            ->whereIn('pattern_listing_id', $listingIds)
            ->orderByDesc('id')
            ->first();
    }

    /** نسخه تحویل‌شده، بدون محدودیت کارگاه فعال (فروشنده هم می‌تواند وجودش را بسنجد). */
    public function deliveredPattern(PatternPurchase $purchase): ?Pattern
    {
        if (! $purchase->delivered_pattern_id) {
            return null;
        }

        return Pattern::query()->acrossWorkshops()->find($purchase->delivered_pattern_id);
    }

    /** برچسب‌های ساده آگهی برای جست‌وجو و دسته‌بندی. */
    protected function tags(Pattern $pattern): array
    {
        return array_values(array_filter([
            $pattern->garmentType?->name_fa,
            $pattern->garmentType?->categoryLabel(),
            'سایز '.$pattern->base_size,
        ]));
    }
}
