<?php

namespace Database\Factories;

use App\Models\Workshop;
use App\Models\WorkshopInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopInvite>
 */
class WorkshopInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'tailor',
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    /** دعوتی که پذیرفته شده است. */
    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
