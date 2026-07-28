<?php

namespace Tests\Feature;

use App\Models\FabricType;
use App\Models\Guide;
use Database\Seeders\FabricTypeSeeder;
use Database\Seeders\GuideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    protected function seedGuides(): void
    {
        $this->seed(FabricTypeSeeder::class);
        $this->seed(GuideSeeder::class);
    }

    public function test_the_seeder_creates_fabric_lessons_and_general_lessons(): void
    {
        $this->seedGuides();

        $this->assertGreaterThanOrEqual(12, Guide::where('scope', 'fabric_type')->count());
        $this->assertSame(4, Guide::where('scope', 'general')->count());

        foreach (['اندازه‌گیری درست بدن', 'جای دوخت چقدر باشد؟', 'انتخاب لایی مناسب', 'مراقبت از پارچه و لباس دوخته‌شده'] as $title) {
            $this->assertDatabaseHas('guides', ['title' => $title, 'scope' => 'general']);
        }

        foreach (Guide::all() as $guide) {
            $this->assertNotEmpty($guide->summary, $guide->title);
            $this->assertNotEmpty($guide->body, $guide->title);
            $this->assertNotEmpty($guide->tips, $guide->title);
        }

        // هر درس پارچه به همان نوع پارچه در بانک وصل است
        foreach (Guide::where('scope', 'fabric_type')->get() as $guide) {
            $this->assertNotNull($guide->fabric_type_id);
            $this->assertInstanceOf(FabricType::class, $guide->fabricType);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seedGuides();
        $first = Guide::count();

        $this->seed(GuideSeeder::class);

        $this->assertSame($first, Guide::count());
    }

    public function test_index_shows_an_empty_state(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('guides.index'))->assertOk()->assertSee('راهنمایی پیدا نشد');
    }

    public function test_index_groups_guides_and_can_search_and_filter(): void
    {
        $this->actingAsWorkshopUser();
        $this->seedGuides();

        $this->get(route('guides.index'))
            ->assertOk()
            ->assertSee('راهنمای پارچه')
            ->assertSee('راهنمای عمومی')
            ->assertSee('کار با حریر (ابریشم)');

        $this->get(route('guides.index', ['q' => 'حریر']))
            ->assertOk()
            ->assertSee('کار با حریر (ابریشم)')
            ->assertDontSee('کار با جین');

        $this->get(route('guides.index', ['scope' => 'general']))
            ->assertOk()
            ->assertSee('اندازه‌گیری درست بدن')
            ->assertDontSee('کار با حریر (ابریشم)');
    }

    public function test_show_renders_the_body_paragraphs_and_the_tips(): void
    {
        $this->actingAsWorkshopUser();

        $type = FabricType::factory()->create(['name_fa' => 'حریر']);

        $guide = Guide::factory()->forFabricType($type)->create([
            'title' => 'کار با حریر',
            'summary' => 'خلاصه درس',
            'body' => "بند اول درس.\n\nبند دوم درس.",
            'tips' => ['اتوی بخار نزنید', 'سوزن ریز بردارید'],
        ]);

        $this->get(route('guides.show', $guide))
            ->assertOk()
            ->assertSee('خلاصه درس')
            ->assertSee('بند اول درس.')
            ->assertSee('بند دوم درس.')
            ->assertSee('اشتباه‌های رایج')
            ->assertSee('اتوی بخار نزنید')
            ->assertSee('حریر');
    }

    public function test_show_lists_related_guides_of_the_same_fabric(): void
    {
        $this->actingAsWorkshopUser();

        $type = FabricType::factory()->create();

        $first = Guide::factory()->forFabricType($type)->create(['title' => 'درس اول']);
        Guide::factory()->forFabricType($type)->create(['title' => 'درس دوم']);

        $this->get(route('guides.show', $first))->assertOk()->assertSee('درس دوم');
    }
}
