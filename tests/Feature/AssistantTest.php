<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Project;
use App\Models\Simulation;
use App\Services\Assistant\WorkshopAssistant;
use Database\Seeders\DesignRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function silk(): Fabric
    {
        return Fabric::factory()->for(FabricType::factory()->fluid()->create([
            'name_fa' => 'حریر',
            'care' => ['wash' => 'شست‌وشوی دستی', 'iron' => 'اتوی ملایم ۱۱۰ درجه'],
            'sewing' => ['needle' => 'سوزن ۶۰/۸ میکروتکس', 'stitch' => 'کوک ریز ۲ میلی‌متر', 'seam' => 'درز فرانسوی'],
        ]))->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'حریر مشکی',
            'gsm' => null,
        ]);
    }

    protected function knit(): Fabric
    {
        return Fabric::factory()->for(FabricType::factory()->stretchy()->create(['name_fa' => 'جرسی']))
            ->create(['workshop_id' => $this->workshop()->id, 'name' => 'جرسی طوسی', 'gsm' => null]);
    }

    public function test_the_page_shows_six_example_questions_and_an_empty_state(): void
    {
        $this->actingAsWorkshopUser();

        $response = $this->get(route('assistant.index'))->assertOk();

        $this->assertCount(6, WorkshopAssistant::EXAMPLES);

        foreach (WorkshopAssistant::EXAMPLES as $example) {
            $response->assertSee($example);
        }

        $response->assertSee('هنوز پرسشی نپرسیده‌اید');
    }

    public function test_asking_redirects_back_with_the_answer_in_the_session(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $fabric = $this->silk();
        $garmentType = GarmentType::factory()->create(['name_fa' => 'پیراهن مجلسی', 'category' => 'formal']);

        $response = $this->post(route('assistant.ask'), [
            'question' => 'این پارچه برای این مدل مناسب است؟',
            'fabric_id' => $fabric->id,
            'garment_type_id' => $garmentType->id,
        ]);

        $response->assertRedirect(route('assistant.index'))->assertSessionHas('assistant.answer');

        $answer = session('assistant.answer');

        $this->assertSame('fabric_fit', $answer['topic']);
        $this->assertStringContainsString('حریر مشکی', $answer['headline']);
        $this->assertStringContainsString('پیراهن مجلسی', $answer['headline']);
        $this->assertNotEmpty($answer['reasons']);
    }

    public function test_the_answer_and_its_reasoning_are_rendered(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $fabric = $this->silk();

        $this->post(route('assistant.ask'), [
            'question' => 'هنگام برش مراقب چه چیزی باشم؟',
            'fabric_id' => $fabric->id,
        ]);

        $this->get(route('assistant.index'))
            ->assertOk()
            ->assertSee('برش و چیدمان')
            ->assertSee('با کاغذ زیر پارچه یا سوزن‌های نزدیک برش بزنید.')
            ->assertSee('این پاسخ از کجا آمد؟')
            ->assertSee('قاعده «پارچه لیز؛ روش برش ویژه»');
    }

    public function test_keywords_pick_the_right_topic(): void
    {
        $this->actingAsWorkshopUser();

        $assistant = app(WorkshopAssistant::class);

        $this->assertSame('consumption', $assistant->detectTopic('چند متر پارچه لازم است؟'));
        $this->assertSame('sewing', $assistant->detectTopic('با کدام سوزن و کوک بدوزم؟'));
        $this->assertSame('pressing', $assistant->detectTopic('این را با چه دمایی اتو کنم؟'));
        $this->assertSame('cutting', $assistant->detectTopic('هنگام برش مراقب چه چیزی باشم؟'));
        $this->assertSame('lining', $assistant->detectTopic('آستر لازم دارد؟'));
        $this->assertSame('stretch', $assistant->detectTopic('کشش این پارچه چقدر است؟'));
        $this->assertSame('ease', $assistant->detectTopic('چقدر آزادی بدهم؟'));
        $this->assertSame('fabric_fit', $assistant->detectTopic('این پارچه برای این مدل مناسب است؟'));
        $this->assertSame('overview', $assistant->detectTopic('سلام'));
    }

    public function test_the_lining_answer_follows_the_rule_engine(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $assistant = app(WorkshopAssistant::class);

        $sheer = $assistant->ask('آستر لازم دارد؟', $this->silk());
        $this->assertStringContainsString('بله', $sheer['headline']);
        $this->assertNotEmpty($sheer['reasons']);

        $opaque = Fabric::factory()->for(FabricType::factory()->structured()->create())
            ->create(['workshop_id' => $this->workshop()->id, 'gsm' => null]);

        $this->assertStringContainsString('نه', $assistant->ask('آستر لازم دارد؟', $opaque)['headline']);
    }

    public function test_the_ease_answer_applies_the_negative_ease_rule_for_knits(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $garmentType = GarmentType::factory()->create([
            'name_fa' => 'بلوز',
            'category' => 'top',
            'default_ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);

        $answer = app(WorkshopAssistant::class)->ask('چقدر آزادی بدهم؟', $this->knit(), $garmentType);

        $this->assertSame('ease', $answer['topic']);
        $this->assertTrue(collect($answer['points'])->contains(fn ($point) => str_contains($point, 'دور سینه')));
        $this->assertTrue(collect($answer['reasons'])->contains(fn ($reason) => str_contains($reason['label'], 'پارچه کشی، آزادی منفی می‌خواهد')));
    }

    public function test_the_consumption_answer_adds_extra_for_directional_fabrics(): void
    {
        $this->actingAsWorkshopUser();

        $garmentType = GarmentType::factory()->create(['name_fa' => 'پیراهن بلند', 'category' => 'one_piece']);

        $plain = Fabric::factory()->for(FabricType::factory()->create())
            ->create(['workshop_id' => $this->workshop()->id, 'width_cm' => 140, 'surface_pattern' => 'plain']);

        $plaid = Fabric::factory()->plaid()->for(FabricType::factory()->create())
            ->create(['workshop_id' => $this->workshop()->id, 'width_cm' => 140]);

        $assistant = app(WorkshopAssistant::class);

        $plainAnswer = $assistant->ask('چند متر پارچه لازم است؟', $plain, $garmentType);
        $plaidAnswer = $assistant->ask('چند متر پارچه لازم است؟', $plaid, $garmentType);

        $this->assertSame('consumption', $plainAnswer['topic']);
        $this->assertGreaterThan(
            $assistant->estimate($plain, $garmentType)['meters'] - 0.01,
            $assistant->estimate($plaid, $garmentType)['meters'],
        );
        $this->assertTrue(collect($plaidAnswer['points'])->contains(fn ($point) => str_contains($point, 'تطبیق')));
    }

    public function test_a_project_supplies_the_fabric_the_model_and_the_latest_simulation(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $garmentType = GarmentType::factory()->create(['name_fa' => 'پیراهن', 'category' => 'one_piece']);
        $fabric = $this->silk();

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'garment_type_id' => $garmentType->id,
            'fabric_id' => $fabric->id,
        ]);

        Simulation::create([
            'workshop_id' => $this->workshop()->id,
            'project_id' => $project->id,
            'fabric_id' => $fabric->id,
            'pose' => 'stand',
            'fit_score' => 82,
            'warnings' => ['دور سینه کمی تنگ است'],
        ]);

        $this->post(route('assistant.ask'), [
            'question' => 'این پارچه برای این مدل مناسب است؟',
            'project_id' => $project->id,
        ])->assertRedirect();

        $answer = session('assistant.answer');

        $this->assertStringContainsString('حریر مشکی', $answer['headline']);
        $this->assertStringContainsString('پیراهن', $answer['headline']);
        $this->assertTrue(collect($answer['reasons'])->contains(fn ($reason) => $reason['label'] === 'آخرین شبیه‌سازی پروژه'));
        $this->assertTrue(collect($answer['reasons'])->contains(fn ($reason) => str_contains($reason['text'], 'دور سینه کمی تنگ است')));
    }

    public function test_the_assistant_asks_for_a_fabric_when_none_is_given(): void
    {
        $this->actingAsWorkshopUser();

        $answer = app(WorkshopAssistant::class)->ask('هنگام برش مراقب چه چیزی باشم؟');

        $this->assertStringContainsString('باید یک پارچه انتخاب کنید', $answer['headline']);
    }

    public function test_a_question_is_required(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('assistant.ask'), ['question' => ''])->assertSessionHasErrors('question');
    }
}
