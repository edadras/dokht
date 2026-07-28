<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPersianInput;
use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternListing;
use App\Models\PatternPurchase;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceService;
use App\Support\WorkshopContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بازارچه الگو: خرید و فروش الگو میان کارگاه‌ها.
 *
 * پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید
 * می‌کند و آن‌گاه خریدار نسخه خودش را برمی‌دارد. سامانه هیچ پولی جابه‌جا نمی‌کند و
 * هیچ ضمانتی برای پرداخت نمی‌دهد؛ فقط دفتر روشنی از سفارش‌ها نگه می‌دارد.
 *
 * برخلاف بقیه بخش‌ها، داده‌های اینجا ذاتاً میان‌کارگاهی‌اند؛ پس هر پرس‌وجو صریح
 * می‌گوید طرفِ خریدار است یا فروشنده، و اجازه دسترسی پیش از هر کاری سنجیده می‌شود.
 */
class MarketplaceController extends Controller
{
    use HandlesPersianInput;

    public function __construct(
        protected WorkshopContext $context,
        protected MarketplaceService $market,
    ) {}

    /** ویترین بازارچه: آگهی همه کارگاه‌ها. */
    public function index(Request $request): View
    {
        $workshopId = $this->workshopId();

        $this->normalizeNumbers($request, ['min_price', 'max_price']);

        $term = trim((string) $request->query('q'));
        $garmentTypeId = $request->query('garment_type');
        $min = $request->filled('min_price') ? (float) $request->input('min_price') : null;
        $max = $request->filled('max_price') ? (float) $request->input('max_price') : null;

        $listings = PatternListing::query()
            ->active()
            ->with(['sellerWorkshop', 'garmentType'])
            ->search($term)
            ->priceBetween($min, $max)
            ->when($garmentTypeId, fn ($q) => $q->where('garment_type_id', $garmentTypeId))
            ->orderByDesc('sales_count')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('market.index', [
            'listings' => $listings,
            'term' => $term,
            'garmentTypeId' => $garmentTypeId,
            'minPrice' => $request->input('min_price'),
            'maxPrice' => $request->input('max_price'),
            'garmentTypes' => GarmentType::active()->orderBy('sort')->orderBy('name_fa')->get(),
            'workshopId' => $workshopId,
            'myListings' => PatternListing::query()->ofSeller($workshopId)->active()->count(),
            'myPurchases' => PatternPurchase::query()->ofBuyer($workshopId)->live()->count(),
            'mySales' => PatternPurchase::query()->ofSeller($workshopId)->live()->count(),
        ]);
    }

    /** صفحه یک آگهی؛ پیش‌نمایش، نه خودِ الگو. */
    public function show(PatternListing $listing): View
    {
        $workshopId = $this->workshopId();
        $isMine = $listing->isOwnedBy($workshopId);

        // آگهی برداشته‌شده یا غیرفعال برای دیگران وجود ندارد
        abort_unless($listing->isVisible() || $isMine, 404);

        $listing->load(['sellerWorkshop', 'garmentType']);

        $existing = $isMine ? null : $this->market->existingPurchase($listing, $workshopId);

        return view('market.show', [
            'listing' => $listing,
            'isMine' => $isMine,
            'existing' => $existing,
            'existingCopy' => $existing ? $this->market->deliveredPattern($existing) : null,
        ]);
    }

    /** خریدهای کارگاه من. */
    public function purchases(): View
    {
        $workshopId = $this->workshopId();

        return view('market.purchases', [
            'purchases' => PatternPurchase::query()
                ->ofBuyer($workshopId)
                ->with(['listing.sellerWorkshop', 'sellerWorkshop', 'deliveredPattern'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /** آگهی‌ها و سفارش‌های فروش کارگاه من. */
    public function sales(): View
    {
        $workshopId = $this->workshopId();

        $listings = PatternListing::query()
            ->ofSeller($workshopId)
            ->with(['pattern', 'garmentType'])
            ->withCount('purchases')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        // الگوهای کارگاه که هنوز آگهی فعالی ندارند (پرس‌وجو خودبه‌خود به کارگاه فعال محدود است)
        $listedPatternIds = PatternListing::query()->active()->ofSeller($workshopId)->pluck('pattern_id')->all();

        return view('market.sales', [
            'listings' => $listings,
            'orders' => PatternPurchase::query()
                ->ofSeller($workshopId)
                ->with(['listing', 'buyerWorkshop', 'buyer'])
                ->orderByDesc('id')
                ->get(),
            'sellablePatterns' => Pattern::query()
                ->whereNotIn('id', $listedPatternIds)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** گذاشتن الگوی کارگاه برای فروش. */
    public function store(Request $request): RedirectResponse
    {
        $workshopId = $this->workshopId();

        $this->normalizeNumbers($request, ['price', 'pattern_id']);

        $data = $request->validate([
            'pattern_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'pattern_id' => 'الگو',
            'title' => 'عنوان',
            'price' => 'قیمت',
            'description' => 'توضیح',
        ]);

        $pattern = Pattern::query()->acrossWorkshops()->find($data['pattern_id']);

        abort_if($pattern === null, 404);
        abort_unless((int) $pattern->workshop_id === $workshopId, 403);

        try {
            $listing = $this->market->publish($pattern, $workshopId, $data);
        } catch (MarketplaceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('market.show', $listing)
            ->with('status', 'آگهی «'.$listing->title.'» روی ویترین بازارچه رفت.');
    }

    /** ویرایش آگهی؛ فقط فروشنده. */
    public function update(Request $request, PatternListing $listing): RedirectResponse
    {
        $this->authorizeSeller($listing);

        $this->normalizeNumbers($request, ['price']);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'title' => 'عنوان',
            'price' => 'قیمت',
            'description' => 'توضیح',
            'is_active' => 'وضعیت آگهی',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        try {
            $this->market->updateListing($listing, $data);
        } catch (MarketplaceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('market.sales')->with('status', 'آگهی به‌روز شد.');
    }

    /** برداشتن آگهی از ویترین؛ سفارش‌های ثبت‌شده می‌مانند. */
    public function destroy(PatternListing $listing): RedirectResponse
    {
        $this->authorizeSeller($listing);

        $this->market->remove($listing);

        return redirect()->route('market.sales')
            ->with('status', 'آگهی برداشته شد. سفارش‌های ثبت‌شده و نسخه‌های تحویل‌شده دست‌نخورده می‌مانند.');
    }

    /** ثبت سفارش خرید. */
    public function order(Request $request, PatternListing $listing): RedirectResponse
    {
        $workshopId = $this->workshopId();

        abort_unless($listing->isVisible(), 404);
        abort_if($listing->isOwnedBy($workshopId), 403, 'الگوی کارگاه خودتان را نمی‌توانید بخرید.');

        if (($existing = $this->market->existingPurchase($listing, $workshopId)) !== null) {
            return redirect()->route('market.purchases')->with('error', $existing->isDelivered()
                ? 'نسخه این الگو پیش‌تر به کارگاه شما تحویل شده است؛ همان را در الگوهایتان دارید.'
                : 'برای این الگو سفارش بازی دارید؛ همین سفارش را دنبال کنید.');
        }

        try {
            $this->market->order(
                $listing,
                $workshopId,
                auth()->id(),
                $this->note($request, 'buyer_note'),
            );
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('market.purchases')->with('status',
            'سفارش شما ثبت شد. پرداخت بیرون از سامانه انجام می‌شود؛ پس از دریافت وجه، فروشنده سفارش را تأیید می‌کند.');
    }

    /** تأیید دریافت وجه توسط فروشنده. */
    public function confirm(Request $request, PatternPurchase $purchase): RedirectResponse
    {
        $this->authorizeSalesSide($purchase);

        try {
            $this->market->confirm($purchase, $this->note($request, 'seller_note'));
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('market.sales')
            ->with('status', 'دریافت وجه ثبت شد. خریدار حالا می‌تواند نسخه الگو را بردارد.');
    }

    /** لغو سفارش توسط فروشنده. */
    public function cancel(Request $request, PatternPurchase $purchase): RedirectResponse
    {
        $this->authorizeSalesSide($purchase);

        try {
            $this->market->cancel($purchase, $this->note($request, 'seller_note'));
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('market.sales')->with('status', 'سفارش لغو شد.');
    }

    /** برداشتن نسخه الگو توسط خریدار. */
    public function copy(PatternPurchase $purchase): RedirectResponse
    {
        $workshopId = $this->workshopId();

        abort_unless($purchase->isBuyer($workshopId), 403);

        try {
            $copy = $this->market->deliver($purchase);
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('patterns.show', $copy)
            ->with('status', 'نسخه این الگو در کارگاه شما ساخته شد؛ آزادانه تغییرش دهید.');
    }

    /** یادداشت اختیاری کاربر روی سفارش. */
    protected function note(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 500);
    }

    /** فقط فروشنده آگهی حق ویرایش و حذف دارد. */
    protected function authorizeSeller(PatternListing $listing): void
    {
        abort_unless($listing->isOwnedBy($this->workshopId()), 403);
    }

    /** فقط کارگاه فروشنده سفارش حق تأیید و لغو دارد. */
    protected function authorizeSalesSide(PatternPurchase $purchase): void
    {
        abort_unless($purchase->isSeller($this->workshopId()), 403);
    }

    /** کارگاه فعال کاربر؛ بدون کارگاه، بازارچه معنا ندارد. */
    protected function workshopId(): int
    {
        $id = $this->context->id();

        abort_if($id === null, 403, 'برای کار با بازارچه باید عضو یک کارگاه باشید.');

        return $id;
    }
}
