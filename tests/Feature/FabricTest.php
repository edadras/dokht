<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Workshop;
use App\Support\FabricProfile;
use Database\Seeders\DesignRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FabricTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_an_empty_state_before_any_fabric_exists(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('fabrics.index'))
            ->assertOk()
            ->assertSee('هنوز پارچه‌ای ثبت نکرده‌اید');
    }

    public function test_index_lists_the_workshop_fabrics_and_can_search(): void
    {
        $this->actingAsWorkshopUser();

        Fabric::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'کرپ مشکی']);
        Fabric::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'جین آبی']);

        $this->get(route('fabrics.index'))->assertOk()->assertSee('کرپ مشکی')->assertSee('جین آبی');

        $this->get(route('fabrics.index', ['q' => 'کرپ']))
            ->assertOk()
            ->assertSee('کرپ مشکی')
            ->assertDontSee('جین آبی');
    }

    public function test_create_form_is_reachable(): void
    {
        $this->actingAsWorkshopUser();
        FabricType::factory()->create(['name_fa' => 'پوپلین نخی']);

        $this->get(route('fabrics.create'))
            ->assertOk()
            ->assertSee('پارچه پایه')
            ->assertSee('پوپلین نخی');
    }

    public function test_a_fabric_can_be_created_with_only_three_fields(): void
    {
        $this->actingAsWorkshopUser();
        $type = FabricType::factory()->fluid()->create(['name_fa' => 'حریر']);

        $response = $this->post(route('fabrics.store'), [
            'fabric_type_id' => $type->id,
            'name' => 'حریر سرمه‌ای',
            'color' => 'سرمه‌ای',
        ]);

        $fabric = Fabric::firstOrFail();

        $response->assertRedirect(route('fabrics.show', $fabric));

        $this->assertSame('حریر سرمه‌ای', $fabric->name);
        $this->assertSame($this->workshop()->id, $fabric->workshop_id);
        $this->assertNull($fabric->profile_overrides);
    }

    public function test_the_name_falls_back_to_the_base_fabric_name(): void
    {
        $this->actingAsWorkshopUser();
        $type = FabricType::factory()->create(['name_fa' => 'گاباردین']);

        $this->post(route('fabrics.store'), ['fabric_type_id' => $type->id]);

        $this->assertSame('گاباردین', Fabric::firstOrFail()->name);
    }

    public function test_a_fabric_inherits_the_full_profile_of_its_base_type(): void
    {
        $this->actingAsWorkshopUser();
        $type = FabricType::factory()->fluid()->create();

        $this->post(route('fabrics.store'), ['fabric_type_id' => $type->id, 'name' => 'حریر']);

        $profile = Fabric::firstOrFail()->profile();

        $this->assertEqualsWithDelta(0.92, $profile->get('drape'), 0.001);
        $this->assertEqualsWithDelta(0.65, $profile->get('transparency'), 0.001);
        $this->assertEqualsWithDelta(0.9, $profile->get('slippage'), 0.001);
        $this->assertSame($type->fabricProfile()->toArray(), $profile->toArray());
    }

    public function test_only_profile_values_that_differ_from_the_base_are_stored_as_overrides(): void
    {
        $this->actingAsWorkshopUser();
        $type = FabricType::factory()->create([
            'profile' => FabricProfile::make(['drape' => 0.4, 'stiffness' => 0.5, 'has_nap' => false])->toArray(),
        ]);

        $this->post(route('fabrics.store'), [
            'fabric_type_id' => $type->id,
            'name' => 'پارچه من',
            'profile' => [
                'drape' => 0.4,       // برابر پایه؛ نباید ذخیره شود
                'stiffness' => 0.8,   // متفاوت؛ باید ذخیره شود
                'has_nap' => '1',     // متفاوت؛ باید ذخیره شود
            ],
        ]);

        $fabric = Fabric::firstOrFail();

        $this->assertSame(['stiffness' => 0.8, 'has_nap' => true], $fabric->profile_overrides);
        $this->assertEqualsWithDelta(0.8, $fabric->profile()->get('stiffness'), 0.001);
        $this->assertEqualsWithDelta(0.4, $fabric->profile()->get('drape'), 0.001);
    }

    public function test_texture_images_are_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->actingAsWorkshopUser();
        $type = FabricType::factory()->create();

        $this->post(route('fabrics.store'), [
            'fabric_type_id' => $type->id,
            'name' => 'پارچه بافت‌دار',
            'texture' => UploadedFile::fake()->image('texture.jpg', 64, 64),
        ]);

        $fabric = Fabric::firstOrFail();

        $this->assertNotNull($fabric->texture_path);
        Storage::disk('public')->assertExists($fabric->texture_path);
    }

    public function test_show_page_renders_the_full_identity_card(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        GarmentType::factory()->create(['name_fa' => 'پیراهن', 'category' => 'one_piece']);

        $fabric = Fabric::factory()
            ->for(FabricType::factory()->fluid()->create(['name_fa' => 'حریر']))
            ->create(['workshop_id' => $this->workshop()->id, 'name' => 'حریر مشکی', 'gsm' => null]);

        $this->get(route('fabrics.show', $fabric))
            ->assertOk()
            ->assertSee('حریر مشکی')
            ->assertSee('شناسنامه پارچه')
            ->assertSee('هشدارهای برش')
            ->assertSee('کدام لباس‌ها مناسب است؟')
            ->assertSee('پیراهن')
            ->assertSee('با کاغذ زیر پارچه یا سوزن‌های نزدیک برش بزنید.');
    }

    public function test_a_fabric_can_be_updated(): void
    {
        $this->actingAsWorkshopUser();

        $fabric = Fabric::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'نام قدیمی']);

        $this->get(route('fabrics.edit', $fabric))->assertOk()->assertSee('نام قدیمی');

        $this->put(route('fabrics.update', $fabric), [
            'fabric_type_id' => $fabric->fabric_type_id,
            'name' => 'نام تازه',
            'color' => 'زیتونی',
            'stock_meters' => 12.5,
        ])->assertRedirect(route('fabrics.show', $fabric));

        $fabric->refresh();

        $this->assertSame('نام تازه', $fabric->name);
        $this->assertSame('زیتونی', $fabric->color);
        $this->assertSame(12.5, $fabric->stock_meters);
    }

    public function test_the_edit_form_shows_stored_overrides(): void
    {
        $this->actingAsWorkshopUser();

        $fabric = Fabric::factory()->for(FabricType::factory()->fluid()->create(['name_fa' => 'حریر']))->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'حریر دستکاری‌شده',
            'profile_overrides' => ['drape' => 0.5, 'has_nap' => true, 'pressing' => 'high'],
        ]);

        $this->get(route('fabrics.edit', $fabric))
            ->assertOk()
            ->assertSee('حریر دستکاری‌شده')
            ->assertSee('مشخصات پیشرفته پارچه');

        $this->assertEqualsWithDelta(0.5, $fabric->profile()->get('drape'), 0.001);
        $this->assertTrue((bool) $fabric->profile()->get('has_nap'));
        $this->assertSame('high', $fabric->profile()->get('pressing'));
    }

    public function test_a_fabric_can_be_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $fabric = Fabric::factory()->create(['workshop_id' => $this->workshop()->id]);

        $this->delete(route('fabrics.destroy', $fabric))->assertRedirect(route('fabrics.index'));

        $this->assertSoftDeleted($fabric);
    }

    public function test_the_base_fabric_type_is_required(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('fabrics.store'), ['name' => 'بدون پایه'])
            ->assertSessionHasErrors('fabric_type_id');

        $this->assertSame(0, Fabric::count());
    }

    public function test_a_user_cannot_see_a_fabric_of_another_workshop(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $foreign = Fabric::factory()->create(['workshop_id' => $other->id, 'name' => 'پارچه کارگاه دیگر']);

        $this->get(route('fabrics.index'))->assertOk()->assertDontSee('پارچه کارگاه دیگر');
        $this->get(route('fabrics.show', $foreign))->assertNotFound();
        $this->get(route('fabrics.edit', $foreign))->assertNotFound();
        $this->put(route('fabrics.update', $foreign), ['fabric_type_id' => $foreign->fabric_type_id])->assertNotFound();
        $this->delete(route('fabrics.destroy', $foreign))->assertNotFound();
    }
}
