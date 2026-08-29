<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** یک الگو از هر خانوادهٔ تازه، از فرم تا قطعه‌های ذخیره‌شده. */
class SmokeNewModelsTest extends TestCase
{
    use RefreshDatabase;

    public static function newModels(): array
    {
        return [
            'تاپ' => ['top_range_tank_waist_regular_knit'],
            'مایو' => ['swim_range_onepiece_standard_full'],
            'پیراهن پوشیده' => ['modest_kurta_mid_long_regular_stand'],
            'یونیفرم' => ['uni_lab_coat_mid_long_pocket_button_turn'],
            'کت‌وشلوار' => ['suit_set_jacket_mid_b2_regular_lined'],
        ];
    }

    #[DataProvider('newModels')]
    public function test_a_pattern_can_be_made_from_a_new_family_model(string $key): void
    {
        $this->assertTrue(GeneratorRegistry::has($key), "«{$key}» در رجیستری نیست.");

        $this->actingAsWorkshopUser();
        $template = PatternTemplate::factory()->generator($key)->create(['name_fa' => 'مدل آزمون']);

        $response = $this->post(route('patterns.store'), [
            'pattern_template_id' => $template->id,
            'base_size' => '40',
            'name' => 'الگوی آزمون '.$key,
        ]);

        $response->assertRedirect();

        $pattern = Pattern::query()->latest('id')->with('pieces')->first();

        $this->assertNotNull($pattern, "«{$key}» الگویی نساخت.");
        $this->assertGreaterThan(0, $pattern->pieces->count(), "«{$key}» هیچ قطعه‌ای نداد.");

        foreach ($pattern->pieces as $piece) {
            $this->assertNotEmpty($piece->outline, 'قطعه «'.$piece->code.'» مسیر ندارد.');
        }

        // صفحهٔ الگو، ویرایشگر، چاپ و خروجی‌ها همه باز می‌شوند
        $this->get(route('patterns.show', $pattern))->assertOk();
        $this->get(route('patterns.editor', $pattern))->assertOk();
        $this->get(route('patterns.print', $pattern))->assertOk();

        foreach (['svg', 'dxf'] as $format) {
            $this->get(route('patterns.export', ['pattern' => $pattern, 'format' => $format]))
                ->assertOk();
        }
    }
}
