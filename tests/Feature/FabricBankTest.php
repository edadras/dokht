<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Guide;
use Database\Seeders\FabricTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_bank_covers_the_real_world_fabrics_a_tailor_asks_for(): void
    {
        $this->seed(FabricTypeSeeder::class);

        $this->assertGreaterThanOrEqual(30, FabricType::count());

        foreach (['حریر (ابریشم)', 'ساتن (پلی‌استر)', 'شیفون', 'کرپ', 'ارگانزا', 'تور', 'گیپور', 'دانتل',
            'مخمل', 'جیر', 'جین', 'فوتر', 'جرسی (تریکو)', 'کشمیر', 'کتان (لینن)', 'پوپلین نخی',
            'توییل نخی', 'گاباردین', 'مازراتی', 'لمه', 'ژرسه', 'نخ‌پنبه', 'کرپ مازراتی', 'ملحفه‌ای', 'آستری'] as $name) {
            $this->assertDatabaseHas('fabric_types', ['name_fa' => $name]);
        }

        foreach (array_keys(FabricType::CATEGORIES) as $category) {
            $this->assertTrue(FabricType::where('category', $category)->exists(), "دسته {$category} خالی است");
        }

        foreach (array_keys(FabricType::FAMILIES) as $family) {
            $this->assertTrue(FabricType::where('family', $family)->exists(), "خانواده {$family} خالی است");
        }
    }

    public function test_the_seeded_physics_are_realistic(): void
    {
        $this->seed(FabricTypeSeeder::class);

        $silk = FabricType::where('code', 'silk-habotai')->firstOrFail()->fabricProfile();
        $this->assertGreaterThan(0.9, $silk->get('drape'));
        $this->assertLessThan(0.1, $silk->get('stiffness'));
        $this->assertGreaterThan(0.9, $silk->get('slippage'));
        $this->assertGreaterThanOrEqual(0.6, $silk->get('transparency'));

        $denim = FabricType::where('code', 'denim')->firstOrFail()->fabricProfile();
        $this->assertLessThan(0.2, $denim->get('drape'));
        $this->assertGreaterThan(0.8, $denim->get('stiffness'));
        $this->assertGreaterThanOrEqual(330, $denim->get('weight_gsm'));

        $terry = FabricType::where('code', 'french-terry')->firstOrFail()->fabricProfile();
        $this->assertLessThan(0.25, $terry->get('drape'));

        $jersey = FabricType::where('code', 'jersey')->firstOrFail()->fabricProfile();
        $this->assertGreaterThanOrEqual(40, $jersey->get('stretch_weft'));
        $this->assertLessThanOrEqual(60, $jersey->get('stretch_weft'));
        $this->assertGreaterThanOrEqual(88, $jersey->get('recovery'));
        $this->assertGreaterThan(0.5, $jersey->get('curling'));

        $velvet = FabricType::where('code', 'velvet')->firstOrFail()->fabricProfile();
        $this->assertTrue((bool) $velvet->get('has_nap'));
    }

    public function test_every_seeded_type_has_care_and_sewing_advice(): void
    {
        $this->seed(FabricTypeSeeder::class);

        foreach (FabricType::all() as $type) {
            $this->assertNotEmpty($type->care['wash'] ?? null, $type->code);
            $this->assertNotEmpty($type->care['iron'] ?? null, $type->code);
            $this->assertNotEmpty($type->sewing['needle'] ?? null, $type->code);
            $this->assertNotEmpty($type->sewing['stitch'] ?? null, $type->code);
            $this->assertNotEmpty($type->description, $type->code);
            $this->assertNotEmpty($type->fiber_composition, $type->code);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(FabricTypeSeeder::class);
        $first = FabricType::count();

        $this->seed(FabricTypeSeeder::class);

        $this->assertSame($first, FabricType::count());
    }

    public function test_the_bank_can_be_browsed_and_filtered(): void
    {
        $this->actingAsWorkshopUser();

        FabricType::factory()->create(['name_fa' => 'حریر ابریشم', 'category' => 'natural', 'family' => 'woven']);
        FabricType::factory()->create(['name_fa' => 'جرسی پلی', 'category' => 'synthetic', 'family' => 'knit']);

        $this->get(route('fabric-bank.index'))->assertOk()->assertSee('حریر ابریشم')->assertSee('جرسی پلی');

        $this->get(route('fabric-bank.index', ['q' => 'حریر']))
            ->assertOk()
            ->assertSee('حریر ابریشم')
            ->assertDontSee('جرسی پلی');

        $this->get(route('fabric-bank.index', ['family' => 'knit']))
            ->assertOk()
            ->assertSee('جرسی پلی')
            ->assertDontSee('حریر ابریشم');

        $this->get(route('fabric-bank.index', ['category' => 'natural']))
            ->assertOk()
            ->assertSee('حریر ابریشم')
            ->assertDontSee('جرسی پلی');
    }

    public function test_an_empty_search_shows_the_empty_state(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('fabric-bank.index', ['q' => 'چیزی-که-نیست']))
            ->assertOk()
            ->assertSee('پارچه‌ای پیدا نشد');
    }

    public function test_a_type_page_shows_the_full_profile(): void
    {
        $this->actingAsWorkshopUser();

        $type = FabricType::factory()->fluid()->create([
            'name_fa' => 'حریر',
            'description' => 'ابریشم نازک و لخت',
            'care' => ['wash' => 'شست‌وشوی دستی', 'iron' => 'اتوی ملایم'],
            'sewing' => ['needle' => 'سوزن ۶۰/۸'],
        ]);

        Guide::factory()->forFabricType($type)->create(['title' => 'کار با حریر']);
        GarmentType::factory()->create(['name_fa' => 'پیراهن مجلسی', 'category' => 'formal']);

        $this->get(route('fabric-bank.show', $type))
            ->assertOk()
            ->assertSee('ابریشم نازک و لخت')
            ->assertSee('شست‌وشوی دستی')
            ->assertSee('سوزن ۶۰/۸')
            ->assertSee('کار با حریر')
            ->assertSee('پیراهن مجلسی')
            ->assertSee('خیلی لخت');
    }

    public function test_a_bank_type_is_added_to_the_workshop_in_one_click_with_the_full_profile(): void
    {
        $this->actingAsWorkshopUser();

        $type = FabricType::factory()->fluid()->create(['name_fa' => 'حریر']);

        $response = $this->post(route('fabric-bank.add', $type));

        $fabric = Fabric::firstOrFail();

        $response->assertRedirect(route('fabrics.show', $fabric));

        $this->assertSame('حریر', $fabric->name);
        $this->assertSame($type->id, $fabric->fabric_type_id);
        $this->assertSame($this->workshop()->id, $fabric->workshop_id);
        $this->assertSame(60, $fabric->gsm);

        // شناسنامه کامل پارچه پایه به ارث می‌رسد
        $this->assertSame($type->fabricProfile()->toArray(), $fabric->profile()->toArray());
    }

    public function test_name_and_colour_are_optional_when_adding_from_the_bank(): void
    {
        $this->actingAsWorkshopUser();

        $type = FabricType::factory()->create(['name_fa' => 'کرپ']);

        $this->post(route('fabric-bank.add', $type), [
            'name' => 'کرپ مشکی',
            'color' => 'مشکی',
            'color_hex' => '#111111',
            'width_cm' => 150,
            'stock_meters' => 8.5,
        ])->assertRedirect();

        $fabric = Fabric::firstOrFail();

        $this->assertSame('کرپ مشکی', $fabric->name);
        $this->assertSame('مشکی', $fabric->color);
        $this->assertSame('#111111', $fabric->color_hex);
        $this->assertSame(150.0, $fabric->width_cm);
        $this->assertSame(8.5, $fabric->stock_meters);
    }
}
