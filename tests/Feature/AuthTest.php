<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_reachable_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('کارگاه خیاطی');
    }

    public function test_registration_creates_a_workshop_for_the_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'مریم احمدی',
            'workshop_name' => 'خیاطی مریم',
            'email' => 'maryam@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'maryam@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('owner', $user->role);
        $this->assertNotNull($user->workshop_id);
        $this->assertSame('خیاطی مریم', $user->workshop->name);
        $this->assertSame($user->id, $user->workshop->owner_id);
    }

    public function test_workshop_name_falls_back_to_the_user_name(): void
    {
        $this->post('/register', [
            'name' => 'زهرا کریمی',
            'email' => 'zahra@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('workshops', ['name' => 'کارگاه زهرا کریمی']);
    }

    public function test_user_can_log_in_and_out(): void
    {
        $workshop = Workshop::factory()->create();
        $user = User::factory()->for($workshop)->create(['email' => 'sara@example.com']);

        $this->post('/login', ['email' => 'sara@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->withWorkshop()->create(['email' => 'sara@example.com']);

        $this->post('/login', ['email' => 'sara@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_the_panel(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = $this->actingAsWorkshopUser();

        $this->patch(route('profile.update'), [
            'name' => 'نام تازه',
            'email' => $user->email,
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
        ])->assertRedirect();

        $this->assertSame('نام تازه', $user->fresh()->name);
        $this->assertSame('09123456789', $user->fresh()->phone);
    }
}
