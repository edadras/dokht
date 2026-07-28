<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternListing;
use App\Models\PatternPurchase;
use App\Models\PatternVersion;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Marketplace\ListingPreview;
use App\Support\Format;
use App\Support\WorkshopContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بازارچه الگو، از دید هر دو طرف معامله.
 *
 * نکته اصلی این آزمون‌ها مرز کارگاه‌هاست: چه چیزی از کارگاه دیگر دیده می‌شود، چه
 * کسی حق چه کاری دارد، و اینکه نسخه خریداری‌شده دقیقاً در کارگاه خریدار بنشیند.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────── ویترین و دیده‌شدن ──────────────────────────

    public function test_a_listing_of_another_workshop_is_visible_on_the_market(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller, ['title' => 'مانتو جلوباز کلاسیک', 'price' => 250000]);

        $this->actingAsWorkshopUser('designer');

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSee('مانتو جلوباز کلاسیک')
            ->assertSee($seller->name)
            ->assertSee(Format::money(250000));

        $this->get(route('market.show', $listing))
            ->assertOk()
            ->assertSee('مانتو جلوباز کلاسیک')
            ->assertSee('سفارش این الگو')
            ->assertSee('پرداخت بیرون از سامانه انجام می‌شود');
    }

    public function test_an_inactive_or_removed_listing_is_not_on_the_shelf(): void
    {
        [$seller] = $this->workshopWithUser();
        $inactive = $this->listingOf($seller, ['title' => 'آگهی غیرفعال', 'is_active' => false]);
        $removed = $this->listingOf($seller, ['title' => 'آگهی برداشته‌شده']);
        $removed->delete();

        $this->actingAsWorkshopUser('designer');

        $this->get(route('market.index'))
            ->assertOk()
            ->assertDontSee('آگهی غیرفعال')
            ->assertDontSee('آگهی برداشته‌شده');

        $this->get(route('market.show', $inactive))->assertNotFound();
        $this->post(route('market.order', $inactive))->assertNotFound();
        $this->get(route('market.show', $removed->id))->assertNotFound();
        $this->post(route('market.order', $removed->id))->assertNotFound();
    }

    public function test_the_market_can_be_searched_and_filtered_by_price_and_garment_type(): void
    {
        [$seller] = $this->workshopWithUser();
        $shirt = GarmentType::factory()->create(['name_fa' => 'پیراهن', 'category' => 'top']);
        $skirt = GarmentType::factory()->create(['name_fa' => 'دامن', 'category' => 'bottom']);

        $this->listingOf($seller, ['title' => 'پیراهن مردانه', 'price' => 100000, 'garment_type_id' => $shirt->id]);
        $this->listingOf($seller, ['title' => 'دامن پیله‌دار', 'price' => 900000, 'garment_type_id' => $skirt->id]);

        $this->actingAsWorkshopUser('designer');

        $this->get(route('market.index', ['q' => 'پیراهن']))
            ->assertOk()->assertSee('پیراهن مردانه')->assertDontSee('دامن پیله‌دار');

        $this->get(route('market.index', ['garment_type' => $skirt->id]))
            ->assertOk()->assertSee('دامن پیله‌دار')->assertDontSee('پیراهن مردانه');

        $this->get(route('market.index', ['max_price' => '۲۰۰۰۰۰']))
            ->assertOk()->assertSee('پیراهن مردانه')->assertDontSee('دامن پیله‌دار');

        $this->get(route('market.index', ['min_price' => '۵۰۰۰۰۰']))
            ->assertOk()->assertSee('دامن پیله‌دار')->assertDontSee('پیراهن مردانه');
    }

    public function test_the_preview_does_not_hand_over_the_pattern_geometry(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('designer');

        $html = $this->get(route('market.show', $listing))->assertOk()->getContent();

        // سایه قطعه‌ها بله، خط‌های الگو نه
        $this->assertStringContainsString('<rect', $html);
        $this->assertStringNotContainsString('<polygon', $html);
        $this->assertStringNotContainsString('<path', substr($html, strpos($html, 'سایه قطعه‌های الگو') ?: 0, 400));
        $this->assertStringContainsString('۲ قطعه', $html);
    }

    // ────────────────────────── گذاشتن آگهی ──────────────────────────

    public function test_a_workshop_can_list_its_own_pattern(): void
    {
        $this->actingAsWorkshopUser('owner');
        $pattern = Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'کت تک',
        ]);

        $response = $this->post(route('market.listings.store'), [
            'pattern_id' => $pattern->id,
            'title' => 'کت تک آستردار',
            'price' => '۳۵۰,۰۰۰',
            'description' => 'برای پارچه فاستونی.',
        ]);

        $listing = PatternListing::firstOrFail();

        $response->assertRedirect(route('market.show', $listing))->assertSessionHas('status');

        $this->assertSame('کت تک آستردار', $listing->title);
        $this->assertSame(350000.0, $listing->price);
        $this->assertSame($this->workshop()->id, $listing->seller_workshop_id);
        $this->assertTrue($listing->is_active);
        $this->assertSame(2, $listing->previewValue('piece_count'));
        $this->assertNotEmpty($listing->previewValue('silhouette'));

        $this->get(route('market.sales'))->assertOk()->assertSee('کت تک آستردار');
    }

    public function test_listing_a_pattern_of_another_workshop_is_refused(): void
    {
        [$other] = $this->workshopWithUser();
        $foreign = $this->patternOf($other);

        $this->actingAsWorkshopUser('owner');

        $this->post(route('market.listings.store'), [
            'pattern_id' => $foreign->id,
            'title' => 'الگوی مردم',
            'price' => '1000',
        ])->assertForbidden();

        $this->assertSame(0, PatternListing::count());
    }

    public function test_the_same_pattern_cannot_be_listed_twice(): void
    {
        $this->actingAsWorkshopUser('owner');
        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);

        $payload = ['pattern_id' => $pattern->id, 'title' => 'آگهی یکم', 'price' => '1000'];

        $this->post(route('market.listings.store'), $payload)->assertRedirect();
        $this->post(route('market.listings.store'), $payload + ['title' => 'آگهی دوم'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, PatternListing::where('pattern_id', $pattern->id)->count());
    }

    public function test_only_the_seller_can_edit_or_remove_a_listing(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller, ['title' => 'آگهی فروشنده']);

        $this->actingAsWorkshopUser('owner');

        $this->patch(route('market.listings.update', $listing), ['title' => 'دستکاری', 'price' => '1'])
            ->assertForbidden();
        $this->delete(route('market.listings.destroy', $listing))->assertForbidden();

        $this->assertSame('آگهی فروشنده', $listing->fresh()->title);
        $this->assertNull($listing->fresh()->deleted_at);

        // خودِ فروشنده می‌تواند
        $this->enter($seller);
        $this->patch(route('market.listings.update', $listing), [
            'title' => 'عنوان تازه',
            'price' => '۵۰۰۰۰',
            'is_active' => '1',
        ])->assertRedirect(route('market.sales'));

        $this->assertSame('عنوان تازه', $listing->fresh()->title);
        $this->assertSame(50000.0, $listing->fresh()->price);
    }

    public function test_removing_a_listing_keeps_the_purchase_history(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller, ['title' => 'آگهی رفتنی']);

        $buyer = $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing))->assertRedirect(route('market.purchases'));

        $this->enter($seller);
        $this->delete(route('market.listings.destroy', $listing))->assertRedirect(route('market.sales'));

        $this->assertSoftDeleted('pattern_listings', ['id' => $listing->id]);
        $this->assertSame(1, PatternPurchase::count());

        // خریدار همچنان سفارشش را با نام آگهی می‌بیند
        $this->actingAs($buyer);
        $this->get(route('market.purchases'))->assertOk()->assertSee('آگهی رفتنی');

        // و فروشنده هم سفارش را در فهرست فروش دارد
        $this->enter($seller);
        $this->get(route('market.sales'))->assertOk()->assertSee('آگهی رفتنی');
    }

    // ────────────────────────── سفارش ──────────────────────────

    public function test_a_workshop_cannot_buy_its_own_pattern(): void
    {
        $this->actingAsWorkshopUser('owner');
        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $listing = PatternListing::factory()->forPattern($pattern)->create();

        $this->post(route('market.order', $listing))->assertForbidden();

        $this->assertSame(0, PatternPurchase::count());
    }

    public function test_ordering_creates_a_pending_purchase_for_both_sides(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller, ['title' => 'دامن ترک', 'price' => 120000]);

        $buyer = $this->actingAsWorkshopUser('owner');

        $this->post(route('market.order', $listing), ['buyer_note' => 'کارت به کارت می‌کنم'])
            ->assertRedirect(route('market.purchases'))
            ->assertSessionHas('status');

        $purchase = PatternPurchase::firstOrFail();

        $this->assertSame(PatternPurchase::PENDING, $purchase->status);
        $this->assertSame($this->workshop()->id, $purchase->buyer_workshop_id);
        $this->assertSame($seller->id, $purchase->seller_workshop_id);
        $this->assertSame($buyer->id, $purchase->buyer_user_id);
        $this->assertSame(120000.0, $purchase->price);
        $this->assertNotNull($purchase->ordered_at);
        $this->assertNull($purchase->paid_at);
        $this->assertNull($purchase->delivered_pattern_id);

        $this->get(route('market.purchases'))
            ->assertOk()
            ->assertSee('دامن ترک')
            ->assertSee('در انتظار تأیید فروشنده');

        $this->enter($seller);
        $this->get(route('market.sales'))
            ->assertOk()
            ->assertSee('دامن ترک')
            ->assertSee('دریافت وجه را تأیید می‌کنم');
    }

    public function test_the_price_at_order_time_is_kept_even_if_the_listing_changes(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller, ['price' => 100000]);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));

        $this->enter($seller);
        $this->patch(route('market.listings.update', $listing), ['title' => $listing->title, 'price' => '999000', 'is_active' => '1']);

        $this->assertSame(100000.0, PatternPurchase::firstOrFail()->price);
    }

    public function test_a_buyer_cannot_order_the_same_pattern_twice(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');

        $this->post(route('market.order', $listing))->assertRedirect(route('market.purchases'));
        $this->post(route('market.order', $listing))
            ->assertRedirect(route('market.purchases'))
            ->assertSessionHas('error');

        $this->assertSame(1, PatternPurchase::count());
    }

    // ────────────────────────── تأیید و لغو ──────────────────────────

    public function test_only_the_seller_can_confirm_or_cancel_a_purchase(): void
    {
        [$seller] = $this->workshopWithUser();
        [$stranger] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        // خریدار نمی‌تواند پرداخت خودش را تأیید کند
        $this->post(route('market.purchases.confirm', $purchase))->assertForbidden();
        $this->post(route('market.purchases.cancel', $purchase))->assertForbidden();

        // کارگاه بی‌ربط هم نه
        $this->enter($stranger);
        $this->post(route('market.purchases.confirm', $purchase))->assertForbidden();
        $this->post(route('market.purchases.cancel', $purchase))->assertForbidden();

        $this->assertSame(PatternPurchase::PENDING, $purchase->fresh()->status);

        // فروشنده بله
        $this->enter($seller);
        $this->post(route('market.purchases.confirm', $purchase), ['seller_note' => 'کارت به کارت رسید'])
            ->assertRedirect(route('market.sales'));

        $purchase->refresh();
        $this->assertSame(PatternPurchase::PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame('کارت به کارت رسید', $purchase->seller_note);
    }

    public function test_the_seller_can_cancel_a_pending_purchase(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.cancel', $purchase))->assertRedirect(route('market.sales'));

        $purchase->refresh();
        $this->assertSame(PatternPurchase::CANCELLED, $purchase->status);
        $this->assertNotNull($purchase->cancelled_at);
    }

    // ────────────────────────── تحویل ──────────────────────────

    public function test_the_buyer_cannot_copy_before_the_seller_confirms(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->post(route('market.purchases.copy', $purchase))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(PatternPurchase::PENDING, $purchase->fresh()->status);
        $this->assertSame(0, Pattern::where('workshop_id', $this->workshop()->id)->count());
    }

    public function test_a_cancelled_purchase_cannot_be_copied(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.cancel', $purchase));

        $this->actingAs(User::where('workshop_id', $purchase->buyer_workshop_id)->firstOrFail());
        $this->post(route('market.purchases.copy', $purchase))->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Pattern::where('workshop_id', $purchase->buyer_workshop_id)->count());
    }

    public function test_the_whole_flow_delivers_an_independent_copy_into_the_buyer_workshop(): void
    {
        [$seller] = $this->workshopWithUser();
        $source = $this->patternOf($seller, ['name' => 'پالتو بلند']);
        $listing = PatternListing::factory()->forPattern($source)->create(['price' => 400000]);
        $sourcePieces = $source->pieces()->count();

        $this->assertGreaterThan(0, $sourcePieces);

        $buyer = $this->actingAsWorkshopUser('owner');
        $buyerWorkshopId = $this->workshop()->id;

        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.confirm', $purchase));

        $this->actingAs($buyer);
        $copy = null;
        $response = $this->post(route('market.purchases.copy', $purchase));

        $copy = Pattern::where('workshop_id', $buyerWorkshopId)->firstOrFail();
        $response->assertRedirect(route('patterns.show', $copy))->assertSessionHas('status');

        $purchase->refresh();
        $this->assertSame(PatternPurchase::DELIVERED, $purchase->status);
        $this->assertSame($copy->id, $purchase->delivered_pattern_id);
        $this->assertNotNull($purchase->delivered_at);

        // نسخه در کارگاه خریدار، با همه قطعه‌ها
        $this->assertSame($buyerWorkshopId, $copy->workshop_id);
        $this->assertSame($sourcePieces, $copy->pieces()->count());
        $this->assertSame($source->garment_type_id, $copy->garment_type_id);
        $this->assertSame($source->seam_allowances, $copy->seam_allowances);
        $this->assertFalse($copy->is_published);
        $this->assertNull($copy->measurement_set_id);
        $this->assertSame('پالتو بلند (کپی)', $copy->name);

        // نسخه آغازین در تاریخچه
        $version = PatternVersion::where('pattern_id', $copy->id)->firstOrFail();
        $this->assertSame(1, $version->version);
        $this->assertSame($sourcePieces, $version->pieceCount());

        // شمارنده‌های آگهی و الگوی مبدأ
        $this->assertSame(1, (int) $listing->fresh()->sales_count);
        $this->assertSame(1, (int) Pattern::acrossWorkshops()->find($source->id)->copies_count);

        // آگهیِ فروشنده روی نسخه خریدار نمی‌نشیند
        $this->assertSame(0, PatternListing::where('pattern_id', $copy->id)->count());

        // و از این لحظه دو الگو مستقل‌اند
        $copy->update(['name' => 'پالتو بلند من', 'base_size' => '44']);
        $copy->pieces()->first()->update(['name' => 'جلوی تغییرکرده']);

        $freshSource = Pattern::acrossWorkshops()->with('pieces')->find($source->id);
        $this->assertSame('پالتو بلند', $freshSource->name);
        $this->assertNotSame('44', $freshSource->base_size);
        $this->assertNotContains('جلوی تغییرکرده', $freshSource->pieces->pluck('name')->all());
    }

    public function test_copying_twice_does_not_duplicate_the_pattern(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $buyer = $this->actingAsWorkshopUser('owner');
        $buyerWorkshopId = $this->workshop()->id;

        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.confirm', $purchase));

        $this->actingAs($buyer);
        $this->post(route('market.purchases.copy', $purchase));
        $first = Pattern::where('workshop_id', $buyerWorkshopId)->firstOrFail();

        $this->post(route('market.purchases.copy', $purchase))
            ->assertRedirect(route('patterns.show', $first));

        $this->assertSame(1, Pattern::where('workshop_id', $buyerWorkshopId)->count());
        $this->assertSame($first->id, $purchase->fresh()->delivered_pattern_id);
        $this->assertSame(1, (int) $listing->fresh()->sales_count);
    }

    public function test_a_buyer_never_reaches_another_buyers_purchase(): void
    {
        [$seller] = $this->workshopWithUser();
        [$otherBuyer] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.confirm', $purchase));

        // کارگاه دیگری با همان نقش، سراغ سفارش این خریدار می‌رود
        $this->enter($otherBuyer);
        $this->post(route('market.purchases.copy', $purchase))->assertForbidden();
        $this->get(route('market.purchases'))->assertOk()->assertDontSee($listing->title);

        $this->assertSame(PatternPurchase::PAID, $purchase->fresh()->status);
        $this->assertSame(0, Pattern::where('workshop_id', $otherBuyer->id)->count());
    }

    public function test_even_the_seller_cannot_copy_the_buyers_purchase(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->actingAsWorkshopUser('owner');
        $this->post(route('market.order', $listing));
        $purchase = PatternPurchase::firstOrFail();

        $this->enter($seller);
        $this->post(route('market.purchases.confirm', $purchase));
        $this->post(route('market.purchases.copy', $purchase))->assertForbidden();

        $this->assertSame(PatternPurchase::PAID, $purchase->fresh()->status);
    }

    public function test_market_pages_need_a_signed_in_workshop_user(): void
    {
        [$seller] = $this->workshopWithUser();
        $listing = $this->listingOf($seller);

        $this->get(route('market.index'))->assertRedirect(route('login'));
        $this->get(route('market.purchases'))->assertRedirect(route('login'));
        $this->get(route('market.sales'))->assertRedirect(route('login'));
        $this->post(route('market.order', $listing))->assertRedirect(route('login'));

        $this->assertSame(0, PatternPurchase::count());
    }

    // ────────────────────────── کمک‌کننده‌ها ──────────────────────────

    /** کارگاهی تازه با مالکش. @return array{0: Workshop, 1: User} */
    protected function workshopWithUser(string $role = 'owner'): array
    {
        $workshop = Workshop::factory()->create();
        $user = User::factory()->for($workshop)->create(['role' => $role]);
        $workshop->update(['owner_id' => $user->id]);

        return [$workshop, $user];
    }

    /** وارد‌شدن به‌عنوان کاربر یک کارگاه (برای جابه‌جایی میان دو طرف معامله). */
    protected function enter(Workshop $workshop): User
    {
        $user = User::where('workshop_id', $workshop->id)->firstOrFail();

        app(WorkshopContext::class)->set($workshop);
        $this->actingAs($user);

        return $user;
    }

    /** الگویی متعلق به کارگاه دیگر، بیرون از محدودیت کارگاه فعال. */
    protected function patternOf(Workshop $workshop, array $attributes = []): Pattern
    {
        return app(WorkshopContext::class)->withoutScope(
            fn () => Pattern::factory()->withSimplePieces()->create(array_merge([
                'workshop_id' => $workshop->id,
            ], $attributes))->load('pieces')
        );
    }

    /** آگهی فعال یک کارگاه دیگر. */
    protected function listingOf(Workshop $workshop, array $attributes = []): PatternListing
    {
        $pattern = $this->patternOf($workshop);

        return PatternListing::factory()->forPattern($pattern)->create(array_merge([
            'preview' => app(ListingPreview::class)->build($pattern),
        ], $attributes));
    }
}
