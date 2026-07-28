<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\User;
use App\Support\FabricProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_owner_cannot_reach_the_admin_panel(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->get(route('admin.index'))->assertForbidden();
        $this->get(route('admin.fabric-types.index'))->assertForbidden();
        $this->get(route('admin.garment-types.index'))->assertForbidden();
    }

    public function test_an_admin_sees_the_dashboard_with_system_wide_counts(): void
    {
        $this->actingAsAdmin();

        FabricType::factory()->count(2)->create();
        GarmentType::factory()->create();

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('مدیریت سامانه')
            ->assertSee('انواع پارچه')
            ->assertSee('تازه‌ترین کارگاه‌ها')
            ->assertSee($this->workshop()->name);
    }

    public function test_the_dashboard_counts_patterns_across_workshops(): void
    {
        $this->actingAsAdmin();

        Pattern::factory()->create(['workshop_id' => $this->workshop()->id]);
        Pattern::factory()->create(); // کارگاه دیگر

        $this->assertSame(2, Pattern::acrossWorkshops()->count());
        $this->get(route('admin.index'))->assertOk();
    }

    public function test_every_admin_list_renders_when_empty(): void
    {
        $this->actingAsAdmin();

        foreach ([
            'admin.fabric-types.index',
            'admin.garment-types.index',
            'admin.templates.index',
            'admin.guides.index',
        ] as $route) {
            $this->get(route($route))->assertOk()->assertSee('پیدا نشد');
        }
    }

    public function test_asking_for_a_template_preview_never_breaks_the_form(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.templates.store'), [
            'garment_type_id' => GarmentType::factory()->create()->id,
            'code' => 'preview-block',
            'name_fa' => 'الگوی پیش‌نمایش',
            'generator' => 'bodice',
            'render_preview' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertModelExists(PatternTemplate::where('code', 'preview-block')->firstOrFail());
    }

    public function test_every_admin_form_renders(): void
    {
        $this->actingAsAdmin();
        GarmentType::factory()->create();

        foreach ([
            'admin.fabric-types.create',
            'admin.garment-types.create',
            'admin.templates.create',
            'admin.guides.create',
        ] as $route) {
            $this->get(route($route))->assertOk()->assertSee('نمای کلی');
        }
    }

    public function test_an_admin_can_create_a_fabric_type_with_a_full_profile(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.fabric-types.store'), [
            'code' => 'silk-chiffon',
            'name_fa' => 'حریر ابریشمی',
            'name_en' => 'Silk chiffon',
            'category' => 'natural',
            'family' => 'woven',
            'fibers' => [['name' => 'ابریشم', 'percent' => 100]],
            'profile' => [
                'weight_gsm' => 55,
                'drape' => 0.95,
                'stiffness' => 0.05,
                'transparency' => 0.7,
                'pressing' => 'low',
                'has_nap' => '1',
                'stretch_weft' => 4,
            ],
            'care' => "شست‌وشو با آب سرد\nاتوی ملایم",
            'sewing' => 'سوزن ۶۰',
            'sort' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.fabric-types.index'))->assertSessionHas('status');

        $type = FabricType::where('code', 'silk-chiffon')->firstOrFail();

        $this->assertSame('حریر ابریشمی', $type->name_fa);
        $this->assertSame('ابریشم', $type->fiber_composition[0]['name']);
        $this->assertSame(100.0, (float) $type->fiber_composition[0]['percent']);
        $this->assertSame(['شست‌وشو با آب سرد', 'اتوی ملایم'], $type->care);
        $this->assertSame(['سوزن ۶۰'], $type->sewing);
        $this->assertTrue($type->is_active);

        // شناسنامه از FabricProfile::normalize() می‌گذرد: همه کلیدها هست و مقادیر در بازه‌اند
        $this->assertSame(array_keys(FabricProfile::SCHEMA), array_keys($type->profile));
        $this->assertEquals(FabricProfile::normalize($type->profile), $type->profile);
        $this->assertSame(55.0, (float) $type->profile['weight_gsm']);
        $this->assertSame(0.95, (float) $type->profile['drape']);
        $this->assertSame('low', $type->profile['pressing']);
        $this->assertTrue($type->profile['has_nap']);
        $this->assertFalse($type->profile['directional']);
    }

    public function test_out_of_range_profile_values_are_clamped_by_the_normalizer(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.fabric-types.store'), [
            'code' => 'odd-fabric',
            'name_fa' => 'پارچه آزمون',
            'category' => 'blend',
            'profile' => ['drape' => 4, 'weight_gsm' => 5000],
        ])->assertRedirect();

        $type = FabricType::where('code', 'odd-fabric')->firstOrFail();

        $this->assertSame(1.0, (float) $type->profile['drape']);
        $this->assertSame(900.0, (float) $type->profile['weight_gsm']);
    }

    public function test_a_fabric_composition_that_does_not_add_up_is_refused(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.fabric-types.store'), [
            'code' => 'bad-mix',
            'name_fa' => 'ترکیب نادرست',
            'category' => 'blend',
            'fibers' => [['name' => 'پنبه', 'percent' => 40], ['name' => 'پلی‌استر', 'percent' => 20]],
        ])->assertSessionHasErrors('fibers');

        $this->assertSame(0, FabricType::where('code', 'bad-mix')->count());
    }

    public function test_an_admin_can_edit_and_delete_a_fabric_type(): void
    {
        $this->actingAsAdmin();

        $type = FabricType::factory()->create(['code' => 'linen-basic', 'name_fa' => 'کتان']);

        $this->get(route('admin.fabric-types.edit', $type))->assertOk()->assertSee('کتان');

        $this->put(route('admin.fabric-types.update', $type), [
            'code' => 'linen-basic',
            'name_fa' => 'کتان مرغوب',
            'category' => 'natural',
            'family' => 'woven',
            'profile' => ['weight_gsm' => 200],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('کتان مرغوب', $type->fresh()->name_fa);
        $this->assertSame(200.0, (float) $type->fresh()->profile['weight_gsm']);

        $this->delete(route('admin.fabric-types.destroy', $type))->assertRedirect();
        $this->assertModelMissing($type);
    }

    public function test_a_fabric_type_in_use_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $type = FabricType::factory()->create();
        Fabric::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'fabric_type_id' => $type->id,
        ]);

        $this->delete(route('admin.fabric-types.destroy', $type))->assertSessionHas('error');

        $this->assertModelExists($type);
    }

    public function test_an_admin_can_create_a_garment_type_with_fabric_preferences(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.garment-types.store'), [
            'code' => 'shirt-basic',
            'name_fa' => 'پیراهن ساده',
            'category' => 'top',
            'parts' => ['front_bodice', 'back_bodice', 'sleeve'],
            'ease' => ['bust' => 8, 'waist' => 6, 'hip' => 7, 'bicep' => 5],
            'required_measurements' => ['bust', 'waist', 'shoulder_width'],
            'preferences' => [
                'drape' => ['ideal' => 0.4, 'tolerance' => 0.25],
                'weight_gsm' => ['min' => 90, 'max' => 220],
                'stretch_weft' => ['min' => 0, 'max' => 25],
                'transparency' => ['max' => 0.2],
                'stiffness' => ['ideal' => 0.45, 'tolerance' => 0.3],
            ],
            'is_active' => '1',
        ])->assertRedirect(route('admin.garment-types.index'));

        $type = GarmentType::where('code', 'shirt-basic')->firstOrFail();

        $this->assertSame(['front_bodice', 'back_bodice', 'sleeve'], $type->parts);
        $this->assertEquals(['bust' => 8, 'waist' => 6, 'hip' => 7, 'bicep' => 5], $type->default_ease);
        $this->assertSame(['bust', 'waist', 'shoulder_width'], $type->required_measurements);

        $preferences = $type->fabric_preferences;

        $this->assertEquals(['ideal' => 0.4, 'tolerance' => 0.25], $preferences['drape']);
        $this->assertEquals(['min' => 90, 'max' => 220], $preferences['weight_gsm']);
        $this->assertEquals(['min' => 0, 'max' => 25], $preferences['stretch_weft']);
        $this->assertEquals(['max' => 0.2], $preferences['transparency']);
        $this->assertEquals(['ideal' => 0.45, 'tolerance' => 0.3], $preferences['stiffness']);
    }

    public function test_a_garment_type_needs_at_least_one_part(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.garment-types.store'), [
            'code' => 'empty-type',
            'name_fa' => 'بدون جزء',
            'category' => 'top',
        ])->assertSessionHasErrors('parts');
    }

    public function test_a_garment_type_in_use_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $type = GarmentType::factory()->create();
        Pattern::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'garment_type_id' => $type->id,
        ]);

        $this->delete(route('admin.garment-types.destroy', $type))->assertSessionHas('error');
        $this->assertModelExists($type);
    }

    public function test_an_unused_garment_type_can_be_edited_and_deleted(): void
    {
        $this->actingAsAdmin();

        $type = GarmentType::factory()->create(['code' => 'skirt-a', 'name_fa' => 'دامن']);

        $this->get(route('admin.garment-types.edit', $type))->assertOk()->assertSee('دامن');

        $this->put(route('admin.garment-types.update', $type), [
            'code' => 'skirt-a',
            'name_fa' => 'دامن ترک',
            'category' => 'bottom',
            'parts' => ['skirt_front', 'skirt_back'],
        ])->assertRedirect();

        $this->assertSame('دامن ترک', $type->fresh()->name_fa);

        $this->delete(route('admin.garment-types.destroy', $type))->assertRedirect();
        $this->assertModelMissing($type);
    }

    public function test_an_admin_can_manage_base_pattern_templates(): void
    {
        $this->actingAsAdmin();

        $garmentType = GarmentType::factory()->create();

        $this->get(route('admin.templates.create'))->assertOk();

        $this->post(route('admin.templates.store'), [
            'garment_type_id' => $garmentType->id,
            'code' => 'skirt-block',
            'name_fa' => 'دامن پایه',
            'generator' => 'skirt',
            'params' => [
                ['key' => 'length', 'label' => 'بلندی دامن', 'default' => 60, 'min' => 30, 'max' => 120, 'step' => 1],
                ['key' => '', 'label' => 'بی‌کلید'],
            ],
            'is_public' => '1',
        ])->assertRedirect();

        $template = PatternTemplate::where('code', 'skirt-block')->firstOrFail();

        $this->assertNull($template->workshop_id);
        $this->assertEquals(['length' => 60], $template->default_params);
        $this->assertSame('بلندی دامن', $template->params_schema['length']['label']);
        $this->assertSame(30.0, (float) $template->params_schema['length']['min']);

        $this->get(route('admin.templates.index'))->assertOk()->assertSee('دامن پایه');
        $this->get(route('admin.templates.edit', $template))->assertOk()->assertSee('بلندی دامن');

        $this->delete(route('admin.templates.destroy', $template))->assertRedirect();
        $this->assertModelMissing($template);
    }

    public function test_an_admin_can_manage_guides(): void
    {
        $this->actingAsAdmin();

        $fabricType = FabricType::factory()->create(['name_fa' => 'حریر']);

        $this->post(route('admin.guides.store'), [
            'scope' => 'fabric_type',
            'fabric_type_id' => $fabricType->id,
            'title' => 'برش حریر بدون سرخوردن',
            'summary' => 'کاغذ زیر پارچه بگذارید.',
            'body' => 'حریر زیر قیچی سر می‌خورد.',
            'tips' => ['سوزن ریز بزنید', '', 'کاغذ زیر پارچه بگذارید'],
            'sort' => 1,
        ])->assertRedirect(route('admin.guides.index'));

        $guide = Guide::firstOrFail();

        $this->assertSame('fabric_type', $guide->scope);
        $this->assertSame($fabricType->id, $guide->fabric_type_id);
        $this->assertSame(['سوزن ریز بزنید', 'کاغذ زیر پارچه بگذارید'], $guide->tips);

        $this->get(route('admin.guides.index'))->assertOk()->assertSee('برش حریر بدون سرخوردن');
        $this->get(route('admin.guides.edit', $guide))->assertOk();

        $this->put(route('admin.guides.update', $guide), [
            'scope' => 'general',
            'title' => 'برش پارچه‌های لخت',
        ])->assertRedirect();

        $this->assertNull($guide->fresh()->fabric_type_id);
        $this->assertSame('general', $guide->fresh()->scope);

        $this->delete(route('admin.guides.destroy', $guide))->assertRedirect();
        $this->assertModelMissing($guide);
    }

    public function test_a_fabric_type_guide_needs_a_fabric_type(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.guides.store'), [
            'scope' => 'fabric_type',
            'title' => 'بدون پارچه',
        ])->assertSessionHasErrors('fabric_type_id');
    }

    /** کاربر مدیر سامانه که کارگاه هم دارد (پنل مدیریت داخل محیط کارگاه است). */
    protected function actingAsAdmin(): User
    {
        return $this->actingAsWorkshopUser('owner', ['is_admin' => true]);
    }
}
