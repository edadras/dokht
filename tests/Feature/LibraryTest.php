<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\PatternVersion;
use App\Models\Workshop;
use App\Services\Pattern\SvgRenderer;
use App\Support\WorkshopContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_library_lists_global_base_patterns(): void
    {
        $this->actingAsWorkshopUser('designer');

        $template = PatternTemplate::create([
            'workshop_id' => null,
            'garment_type_id' => GarmentType::factory()->create(['name_fa' => 'پیراهن'])->id,
            'code' => 'bodice-block',
            'name_fa' => 'بالاتنه پایه',
            'generator' => 'bodice',
            'description' => 'نقطه شروع هر بالاتنه',
            'is_public' => true,
        ]);

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('بالاتنه پایه')
            ->assertSee('نقطه شروع هر بالاتنه')
            ->assertSee(route('patterns.create', ['template' => $template->id]), false);
    }

    public function test_a_private_template_of_another_workshop_is_hidden(): void
    {
        $this->actingAsWorkshopUser('designer');

        PatternTemplate::create([
            'workshop_id' => Workshop::factory()->create()->id,
            'garment_type_id' => GarmentType::factory()->create()->id,
            'code' => 'secret-block',
            'name_fa' => 'الگوی خصوصی دیگران',
            'generator' => 'bodice',
            'is_public' => false,
        ]);

        $this->get(route('library.index'))->assertOk()->assertDontSee('الگوی خصوصی دیگران');
    }

    public function test_the_library_lists_a_published_pattern_of_another_workshop(): void
    {
        $this->actingAsWorkshopUser('designer');

        $foreign = $this->foreignPattern(['name' => 'پیراهن آستین‌بلند', 'is_published' => true]);

        $index = $this->get(route('library.index', ['tab' => 'published']))
            ->assertOk()
            ->assertSee('پیراهن آستین‌بلند')
            ->assertSee($foreign->workshop->name);

        $show = $this->get(route('library.show', $foreign->id))
            ->assertOk()
            ->assertSee('پیراهن آستین‌بلند')
            ->assertSee('کپی به کارگاه من')
            ->assertSee('جلو');

        // اگر موتور ترسیم در دسترس باشد، بندانگشتی هم رسم می‌شود
        if (class_exists(SvgRenderer::class)) {
            $index->assertSee('<svg', false);
            $show->assertSee('<svg', false);
        }
    }

    public function test_an_unpublished_pattern_of_another_workshop_is_not_visible(): void
    {
        $this->actingAsWorkshopUser('designer');

        $foreign = $this->foreignPattern(['name' => 'الگوی منتشرنشده', 'is_published' => false]);

        $this->get(route('library.index', ['tab' => 'published']))->assertOk()->assertDontSee('الگوی منتشرنشده');
        $this->get(route('library.show', $foreign->id))->assertNotFound();
        $this->post(route('library.copy', $foreign->id))->assertNotFound();
    }

    public function test_copying_a_published_pattern_clones_every_piece_into_my_workshop(): void
    {
        $this->actingAsWorkshopUser('designer');
        $workshopId = $this->workshop()->id;

        $foreign = $this->foreignPattern(['name' => 'دامن ترک', 'is_published' => true]);
        $sourcePieces = $foreign->pieces->count();

        $this->assertGreaterThan(0, $sourcePieces);

        $response = $this->post(route('library.copy', $foreign->id));

        $copy = Pattern::where('workshop_id', $workshopId)->firstOrFail();

        $response->assertRedirect(route('patterns.show', $copy))->assertSessionHas('status');

        $this->assertSame('دامن ترک (کپی)', $copy->name);
        $this->assertFalse($copy->is_published);
        $this->assertSame(1, $copy->version);
        $this->assertSame($workshopId, $copy->workshop_id);
        $this->assertNull($copy->measurement_set_id);
        $this->assertSame($sourcePieces, $copy->pieces()->count());
        $this->assertSame($foreign->garment_type_id, $copy->garment_type_id);
        $this->assertSame($foreign->seam_allowances, $copy->seam_allowances);

        // شمارنده کپی روی الگوی مبدأ بالا می‌رود
        $this->assertSame(1, (int) Pattern::acrossWorkshops()->find($foreign->id)->copies_count);

        // نسخه آغازین ثبت می‌شود
        $version = PatternVersion::where('pattern_id', $copy->id)->firstOrFail();
        $this->assertSame(1, $version->version);
        $this->assertSame($sourcePieces, $version->pieceCount());
    }

    public function test_copying_my_own_published_pattern_gets_a_clear_name(): void
    {
        $this->actingAsWorkshopUser('designer');

        $mine = Pattern::factory()->withSimplePieces()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'مانتو جلوباز',
            'is_published' => true,
        ]);

        $this->post(route('library.copy', $mine->id))->assertRedirect();
        $this->post(route('library.copy', $mine->id))->assertRedirect();

        $names = Pattern::where('workshop_id', $this->workshop()->id)->pluck('name')->all();

        $this->assertContains('مانتو جلوباز (کپی)', $names);
        $this->assertContains('مانتو جلوباز (کپی 2)', $names);
        $this->assertSame(2, (int) $mine->fresh()->copies_count);
    }

    public function test_the_library_can_be_searched_and_filtered(): void
    {
        $this->actingAsWorkshopUser('designer');

        $shirt = GarmentType::factory()->create(['name_fa' => 'پیراهن', 'category' => 'top']);
        $skirt = GarmentType::factory()->create(['name_fa' => 'دامن', 'category' => 'bottom']);

        $this->foreignPattern(['name' => 'پیراهن مردانه', 'is_published' => true, 'garment_type_id' => $shirt->id]);
        $this->foreignPattern(['name' => 'دامن پیله‌دار', 'is_published' => true, 'garment_type_id' => $skirt->id]);

        $this->get(route('library.index', ['tab' => 'published', 'q' => 'پیراهن']))
            ->assertOk()
            ->assertSee('پیراهن مردانه')
            ->assertDontSee('دامن پیله‌دار');

        $this->get(route('library.index', ['tab' => 'published', 'category' => 'bottom']))
            ->assertOk()
            ->assertSee('دامن پیله‌دار')
            ->assertDontSee('پیراهن مردانه');
    }

    /** الگویی که به کارگاه دیگری تعلق دارد. */
    protected function foreignPattern(array $attributes = []): Pattern
    {
        $workshop = Workshop::factory()->create();

        return app(WorkshopContext::class)->withoutScope(
            fn () => Pattern::factory()->withSimplePieces()->create(array_merge([
                'workshop_id' => $workshop->id,
            ], $attributes))->load(['pieces', 'workshop'])
        );
    }
}
