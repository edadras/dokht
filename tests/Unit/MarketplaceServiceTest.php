<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternListing;
use App\Models\PatternPurchase;
use App\Models\PatternVersion;
use App\Models\Workshop;
use App\Services\Marketplace\ListingPreview;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceService;
use App\Services\Marketplace\PatternDuplicator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قاعده‌های بازارچه، بدون واسطه صفحه‌ها.
 */
class MarketplaceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceService $market;

    protected function setUp(): void
    {
        parent::setUp();

        $this->market = app(MarketplaceService::class);
    }

    // ────────────────────────── آگهی ──────────────────────────

    public function test_a_workshop_can_only_list_its_own_pattern(): void
    {
        $seller = Workshop::factory()->create();
        $stranger = Workshop::factory()->create();
        $pattern = $this->pattern($seller);

        $this->expectException(MarketplaceException::class);

        $this->market->publish($pattern, $stranger->id, ['title' => 'الگوی مردم', 'price' => 1000]);
    }

    public function test_a_pattern_can_have_only_one_active_listing(): void
    {
        $seller = Workshop::factory()->create();
        $pattern = $this->pattern($seller);

        $first = $this->market->publish($pattern, $seller->id, ['title' => 'آگهی یکم', 'price' => 1000]);

        try {
            $this->market->publish($pattern, $seller->id, ['title' => 'آگهی دوم', 'price' => 2000]);
            $this->fail('آگهی دوم برای همان الگو نباید ساخته شود.');
        } catch (MarketplaceException) {
            // انتظار همین است
        }

        $this->assertSame(1, PatternListing::where('pattern_id', $pattern->id)->count());

        // پس از برداشتن آگهی، همان الگو دوباره قابل فروش است
        $this->market->remove($first);

        $second = $this->market->publish($pattern, $seller->id, ['title' => 'آگهی تازه', 'price' => 3000]);

        $this->assertTrue($second->is_active);
        $this->assertSame(2, PatternListing::withTrashed()->where('pattern_id', $pattern->id)->count());
    }

    public function test_the_database_itself_refuses_a_second_active_listing(): void
    {
        $seller = Workshop::factory()->create();
        $pattern = $this->pattern($seller);

        PatternListing::factory()->forPattern($pattern)->create();

        $this->expectException(QueryException::class);

        PatternListing::factory()->forPattern($pattern)->create();
    }

    public function test_a_listing_keeps_its_purchases_after_being_removed(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();

        $purchase = $this->market->order($listing, $buyer->id);
        $this->market->remove($listing);

        $this->assertSoftDeleted('pattern_listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('pattern_purchases', ['id' => $purchase->id, 'status' => PatternPurchase::PENDING]);
        $this->assertSame($listing->title, $purchase->fresh()->listing->title);
        $this->assertFalse($listing->fresh()->isVisible());
    }

    // ────────────────────────── سفارش ──────────────────────────

    public function test_a_workshop_cannot_buy_its_own_pattern(): void
    {
        $seller = Workshop::factory()->create();
        $listing = PatternListing::factory()->forPattern($this->pattern($seller))->create();

        $this->expectException(MarketplaceException::class);

        $this->market->order($listing, $seller->id);
    }

    public function test_an_order_freezes_the_price_of_the_moment(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer(['price' => 120000]);

        $purchase = $this->market->order($listing, $buyer->id);

        $this->market->updateListing($listing, ['title' => $listing->title, 'price' => 999000]);

        $this->assertSame(120000.0, $purchase->fresh()->price);
        $this->assertSame(999000.0, $listing->fresh()->price);
    }

    public function test_a_buyer_cannot_order_the_same_pattern_twice_even_through_a_new_listing(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $pattern = $listing->pattern;

        $this->market->order($listing, $buyer->id);
        $this->market->remove($listing);

        // فروشنده همان الگو را با آگهی تازه‌ای می‌گذارد
        $again = $this->market->publish($pattern, $listing->seller_workshop_id, ['title' => 'دوباره', 'price' => 50000]);

        $this->assertNotNull($this->market->existingPurchase($again, $buyer->id));

        $this->expectException(MarketplaceException::class);

        $this->market->order($again, $buyer->id);
    }

    public function test_a_cancelled_order_frees_the_buyer_to_order_again(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();

        $first = $this->market->order($listing, $buyer->id);
        $this->market->cancel($first);

        $second = $this->market->order($listing, $buyer->id);

        $this->assertSame(PatternPurchase::PENDING, $second->status);
        $this->assertNotSame($first->id, $second->id);
    }

    // ────────────────────────── تأیید، لغو، تحویل ──────────────────────────

    public function test_only_a_pending_order_can_be_confirmed(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $purchase = $this->market->order($listing, $buyer->id);

        $this->market->confirm($purchase, 'وجه را نقدی گرفتم');

        $this->assertSame(PatternPurchase::PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame('وجه را نقدی گرفتم', $purchase->seller_note);

        $this->expectException(MarketplaceException::class);

        $this->market->confirm($purchase);
    }

    public function test_a_delivered_order_cannot_be_cancelled(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $purchase = $this->market->order($listing, $buyer->id);

        $this->market->confirm($purchase);
        $this->market->deliver($purchase);

        $this->expectException(MarketplaceException::class);

        $this->market->cancel($purchase);
    }

    public function test_delivery_needs_a_confirmed_payment(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $purchase = $this->market->order($listing, $buyer->id);

        try {
            $this->market->deliver($purchase);
            $this->fail('سفارش تأییدنشده نباید تحویل شود.');
        } catch (MarketplaceException) {
            // انتظار همین است
        }

        $this->assertSame(0, Pattern::query()->acrossWorkshops()->where('workshop_id', $buyer->id)->count());
    }

    public function test_delivery_puts_an_independent_copy_in_the_buyer_workshop(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $source = $listing->pattern;
        $purchase = $this->market->order($listing, $buyer->id);

        $this->market->confirm($purchase);
        $copy = $this->market->deliver($purchase);

        $this->assertSame($buyer->id, $copy->workshop_id);
        $this->assertSame($source->pieces()->count(), $copy->pieces()->count());
        $this->assertFalse($copy->is_published);
        $this->assertSame(1, $copy->version);
        $this->assertSame(PatternPurchase::DELIVERED, $purchase->fresh()->status);
        $this->assertSame($copy->id, $purchase->fresh()->delivered_pattern_id);
        $this->assertSame(1, (int) $listing->fresh()->sales_count);

        $version = PatternVersion::where('pattern_id', $copy->id)->firstOrFail();
        $this->assertSame($source->pieces()->count(), $version->pieceCount());

        // استقلال: تغییر نسخه خریدار به الگوی فروشنده نمی‌رسد و برعکس
        $copy->pieces()->first()->update(['name' => 'قطعه دستکاری‌شده']);
        $source->update(['name' => 'نام تازه فروشنده']);

        $this->assertNotContains(
            'قطعه دستکاری‌شده',
            Pattern::query()->acrossWorkshops()->find($source->id)->pieces->pluck('name')->all(),
        );
        $this->assertNotSame('نام تازه فروشنده', $copy->fresh()->name);
    }

    public function test_delivering_twice_returns_the_same_copy(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $purchase = $this->market->order($listing, $buyer->id);

        $this->market->confirm($purchase);

        $first = $this->market->deliver($purchase);
        $second = $this->market->deliver($purchase);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Pattern::query()->acrossWorkshops()->where('workshop_id', $buyer->id)->count());
        $this->assertSame(1, (int) $listing->fresh()->sales_count);
    }

    public function test_delivery_fails_loudly_when_the_source_pattern_is_gone(): void
    {
        [$listing, $buyer] = $this->listingAndBuyer();
        $purchase = $this->market->order($listing, $buyer->id);
        $this->market->confirm($purchase);

        $listing->pattern->delete();

        $this->expectException(MarketplaceException::class);

        $this->market->deliver($purchase->fresh());
    }

    // ────────────────────────── پیش‌نمایش و کپی ──────────────────────────

    public function test_the_preview_describes_the_pattern_without_giving_its_geometry(): void
    {
        $pattern = $this->pattern(Workshop::factory()->create());
        $preview = app(ListingPreview::class)->build($pattern);

        $this->assertSame(2, $preview['piece_count']);
        $this->assertSame(2, $preview['cut_count']);
        $this->assertSame('40', $preview['base_size']);
        $this->assertStringContainsString('تا', $preview['size_range']);
        $this->assertNotEmpty($preview['largest_piece']);

        // سایه فقط کادر قطعه‌هاست
        $this->assertStringContainsString('<rect', $preview['silhouette']);
        $this->assertStringNotContainsString('polygon', $preview['silhouette']);
        $this->assertStringNotContainsString('path', $preview['silhouette']);

        // هیچ‌کدام از نقطه‌های واقعی الگو در پیش‌نمایش نیست
        $outline = $pattern->pieces->first()->outline;
        $this->assertNotEmpty($outline);
        $this->assertStringNotContainsString(json_encode($outline), json_encode($preview));
    }

    public function test_the_copy_name_is_unique_inside_the_target_workshop(): void
    {
        $seller = Workshop::factory()->create();
        $buyer = Workshop::factory()->create();
        $source = $this->pattern($seller, ['name' => 'کت تک']);

        $duplicator = app(PatternDuplicator::class);

        $first = $duplicator->duplicate($source, $buyer->id);
        $second = $duplicator->duplicate($source, $buyer->id);

        $this->assertSame('کت تک (کپی)', $first->name);
        $this->assertSame('کت تک (کپی 2)', $second->name);
        $this->assertSame(2, (int) Pattern::query()->acrossWorkshops()->find($source->id)->copies_count);

        // نام تکراری کارگاه دیگر مزاحم نمی‌شود
        $other = Workshop::factory()->create();
        $this->assertSame('کت تک (کپی)', $duplicator->duplicate($source, $other->id)->name);
    }

    // ────────────────────────── کمک‌کننده‌ها ──────────────────────────

    protected function pattern(Workshop $workshop, array $attributes = []): Pattern
    {
        return Pattern::factory()->withSimplePieces()->create(array_merge([
            'workshop_id' => $workshop->id,
        ], $attributes))->load('pieces');
    }

    /** یک آگهی فعال و یک کارگاه خریدار. @return array{0: PatternListing, 1: Workshop} */
    protected function listingAndBuyer(array $attributes = []): array
    {
        $seller = Workshop::factory()->create();
        $listing = PatternListing::factory()->forPattern($this->pattern($seller))->create($attributes);

        return [$listing, Workshop::factory()->create()];
    }
}
