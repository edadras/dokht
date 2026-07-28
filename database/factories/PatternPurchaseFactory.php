<?php

namespace Database\Factories;

use App\Models\PatternListing;
use App\Models\PatternPurchase;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatternPurchase>
 */
class PatternPurchaseFactory extends Factory
{
    public function definition(): array
    {
        $listing = PatternListing::factory();

        return [
            'pattern_listing_id' => $listing,
            'seller_workshop_id' => fn (array $attributes) => PatternListing::withTrashed()
                ->find($attributes['pattern_listing_id'])?->seller_workshop_id,
            'buyer_workshop_id' => Workshop::factory(),
            'buyer_user_id' => null,
            'price' => fn (array $attributes) => PatternListing::withTrashed()
                ->find($attributes['pattern_listing_id'])?->price ?? 0,
            'currency' => 'تومان',
            'status' => PatternPurchase::PENDING,
            'ordered_at' => now(),
        ];
    }

    /** سفارشی برای یک آگهی مشخص. */
    public function forListing(PatternListing $listing): static
    {
        return $this->state(fn () => [
            'pattern_listing_id' => $listing->id,
            'seller_workshop_id' => $listing->seller_workshop_id,
            'price' => $listing->price,
            'currency' => $listing->currency,
        ]);
    }

    /** فروشنده دریافت وجه را (بیرون از سامانه) تأیید کرده است. */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PatternPurchase::PAID,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PatternPurchase::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
