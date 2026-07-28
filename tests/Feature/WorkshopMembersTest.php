<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WorkshopMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_members_page_lists_members_invites_and_role_notes(): void
    {
        $owner = $this->actingAsWorkshopUser('owner');

        $tailor = User::factory()->for($this->workshop())->role('tailor')->create(['name' => 'رضا خیاط']);
        $invite = WorkshopInvite::factory()->for($this->workshop())->create(['email' => 'new@example.com']);

        $this->get(route('workshop.members'))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertSee('رضا خیاط')
            ->assertSee('مدیر اصلی')
            ->assertSee('new@example.com')
            ->assertSee($invite->url())
            ->assertSee('فقط مشاهده');

        $this->assertTrue($tailor->exists);
    }

    public function test_a_tailor_cannot_reach_the_members_page(): void
    {
        $this->actingAsWorkshopUser('tailor');

        $this->get(route('workshop.members'))->assertForbidden();
    }

    public function test_inviting_a_colleague_creates_an_invite_and_shows_its_link(): void
    {
        $this->actingAsWorkshopUser('owner');

        $response = $this->post(route('workshop.invites.store'), [
            'email' => 'Zahra@Example.com',
            'role' => 'designer',
        ]);

        $invite = WorkshopInvite::firstOrFail();

        $response->assertRedirect(route('workshop.members'))
            ->assertSessionHas('invite_link', $invite->url());

        $this->assertSame('zahra@example.com', $invite->email);
        $this->assertSame('designer', $invite->role);
        $this->assertSame($this->workshop()->id, $invite->workshop_id);
        $this->assertNotEmpty($invite->token);
    }

    public function test_the_invite_link_accepts_the_colleague_into_the_right_workshop_with_the_right_role(): void
    {
        $this->actingAsWorkshopUser('owner');
        $workshopId = $this->workshop()->id;

        $this->post(route('workshop.invites.store'), ['email' => 'zahra@example.com', 'role' => 'designer']);

        $invite = WorkshopInvite::firstOrFail();

        Auth::logout();

        $this->get(route('invites.show', $invite->token))->assertOk();

        $this->post(route('invites.accept', $invite->token), [
            'name' => 'زهرا الگوساز',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $member = User::where('email', 'zahra@example.com')->firstOrFail();

        $this->assertSame($workshopId, $member->workshop_id);
        $this->assertSame('designer', $member->role);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_you_cannot_invite_someone_who_is_already_a_member(): void
    {
        $this->actingAsWorkshopUser('owner');

        $member = User::factory()->for($this->workshop())->role('tailor')->create(['email' => 'member@example.com']);

        $this->post(route('workshop.invites.store'), [
            'email' => 'MEMBER@example.com',
            'role' => 'tailor',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, WorkshopInvite::count());
        $this->assertSame($this->workshop()->id, $member->fresh()->workshop_id);
    }

    public function test_the_owner_role_cannot_be_handed_out_through_an_invite(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->post(route('workshop.invites.store'), [
            'email' => 'someone@example.com',
            'role' => 'owner',
        ])->assertSessionHasErrors('role');

        $this->assertSame(0, WorkshopInvite::count());
    }

    public function test_inviting_the_same_email_twice_reuses_the_open_invite(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->post(route('workshop.invites.store'), ['email' => 'again@example.com', 'role' => 'tailor']);
        $this->post(route('workshop.invites.store'), ['email' => 'again@example.com', 'role' => 'viewer']);

        $this->assertSame(1, WorkshopInvite::count());
        $this->assertSame('viewer', WorkshopInvite::firstOrFail()->role);
    }

    public function test_an_invite_of_another_workshop_cannot_be_cancelled(): void
    {
        $this->actingAsWorkshopUser('owner');

        $foreign = WorkshopInvite::factory()->for(Workshop::factory())->create();

        $this->delete(route('workshop.invites.destroy', $foreign))->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_the_owner_can_cancel_an_invite_of_their_own_workshop(): void
    {
        $this->actingAsWorkshopUser('owner');

        $invite = WorkshopInvite::factory()->for($this->workshop())->create();

        $this->delete(route('workshop.invites.destroy', $invite))
            ->assertRedirect(route('workshop.members'))
            ->assertSessionHas('status');

        $this->assertModelMissing($invite);
    }

    public function test_a_member_role_can_be_changed(): void
    {
        $this->actingAsWorkshopUser('owner');

        $member = User::factory()->for($this->workshop())->role('viewer')->create();

        $this->patch(route('workshop.members.update', $member), ['role' => 'designer'])
            ->assertRedirect(route('workshop.members'))
            ->assertSessionHas('status');

        $this->assertSame('designer', $member->fresh()->role);
    }

    public function test_the_workshop_cannot_be_left_without_an_owner(): void
    {
        $owner = $this->actingAsWorkshopUser('owner');
        $second = User::factory()->for($this->workshop())->role('owner')->create();

        // خودت را پایین نمی‌آوری
        $this->patch(route('workshop.members.update', $owner), ['role' => 'tailor'])
            ->assertSessionHas('error');
        $this->assertSame('owner', $owner->fresh()->role);

        // مدیر اصلی کارگاه هم پایین نمی‌آید، حتی اگر مدیر دیگری باشد
        $this->assertSame($owner->id, $this->workshop()->owner_id);

        // مدیر دوم می‌تواند نقشش عوض شود
        $this->patch(route('workshop.members.update', $second), ['role' => 'tailor'])->assertRedirect();
        $this->assertSame('tailor', $second->fresh()->role);
    }

    public function test_the_main_owner_cannot_be_demoted_by_another_owner(): void
    {
        $mainOwner = $this->actingAsWorkshopUser('owner');
        $second = User::factory()->for($this->workshop())->role('owner')->create();

        $this->actingAs($second);

        $this->patch(route('workshop.members.update', $mainOwner), ['role' => 'viewer'])
            ->assertSessionHas('error');

        $this->assertSame('owner', $mainOwner->fresh()->role);
    }

    public function test_the_owner_cannot_be_removed_from_the_workshop(): void
    {
        $owner = $this->actingAsWorkshopUser('owner');
        $second = User::factory()->for($this->workshop())->role('owner')->create();

        $this->delete(route('workshop.members.destroy', $second))->assertSessionHas('error');
        $this->assertSame($this->workshop()->id, $second->fresh()->workshop_id);

        $this->delete(route('workshop.members.destroy', $owner))->assertSessionHas('error');
        $this->assertSame($this->workshop()->id, $owner->fresh()->workshop_id);
    }

    public function test_a_member_is_detached_from_the_workshop_when_removed(): void
    {
        $this->actingAsWorkshopUser('owner');

        $member = User::factory()->for($this->workshop())->role('tailor')->create();

        $this->delete(route('workshop.members.destroy', $member))
            ->assertRedirect(route('workshop.members'))
            ->assertSessionHas('status');

        $this->assertNull($member->fresh()->workshop_id);
    }

    public function test_a_member_of_another_workshop_cannot_be_touched(): void
    {
        $this->actingAsWorkshopUser('owner');

        $foreign = User::factory()->withWorkshop()->role('tailor')->create();

        $this->patch(route('workshop.members.update', $foreign), ['role' => 'viewer'])->assertNotFound();
        $this->delete(route('workshop.members.destroy', $foreign))->assertNotFound();

        $this->assertSame('tailor', $foreign->fresh()->role);
        $this->assertNotNull($foreign->fresh()->workshop_id);
    }
}
