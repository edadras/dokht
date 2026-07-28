<?php

namespace Tests;

use App\Models\User;
use App\Models\Workshop;
use App\Support\WorkshopContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?Workshop $workshop = null;

    /** یک کارگاه با کاربر مدیر می‌سازد و کاربر را وارد می‌کند. */
    protected function actingAsWorkshopUser(string $role = 'owner', array $userAttributes = []): User
    {
        $workshop = Workshop::factory()->create();

        $user = User::factory()->for($workshop)->create(array_merge([
            'role' => $role,
        ], $userAttributes));

        $workshop->update(['owner_id' => $user->id]);

        $this->workshop = $workshop;
        app(WorkshopContext::class)->set($workshop);

        $this->actingAs($user);

        return $user;
    }

    /** کارگاه فعال آزمون. */
    protected function workshop(): Workshop
    {
        if ($this->workshop === null) {
            $this->actingAsWorkshopUser();
        }

        return $this->workshop;
    }

    protected function tearDown(): void
    {
        app(WorkshopContext::class)->forget();

        parent::tearDown();
    }
}
