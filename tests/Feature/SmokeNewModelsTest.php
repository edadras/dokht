<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\ModestRangeCatalog;
use App\Services\Pattern\Generators\SuitRangeCatalog;
use App\Services\Pattern\Generators\SwimRangeCatalog;
use App\Services\Pattern\Generators\TopRangeCatalog;
use App\Services\Pattern\Generators\UnderwearRangeCatalog;
use App\Services\Pattern\Generators\UniformRangeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** یک الگو از هر خانوادهٔ تازه، از فرم تا قطعه‌های ذخیره‌شده. */
class SmokeNewModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * یک مدل از هر خانواده، *خوانده‌شده از خودِ خانواده*.
     *
     * کلیدها این‌جا دستی نوشته نمی‌شوند: با هر محورِ تازه‌ای که به یک جدول
     * اضافه شود کلیدها عوض می‌شوند و آزمون بی‌آنکه ایرادی در کار باشد می‌افتد.
     * پس نامِ خانواده را می‌دهیم و ردیفِ اولش را خودش برمی‌دارد.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function newModels(): array
    {
        return [
            'تاپ' => [TopRangeCatalog::class],
            'مایو' => [SwimRangeCatalog::class],
            'پیراهن پوشیده' => [ModestRangeCatalog::class],
            'یونیفرم' => [UniformRangeCatalog::class],
            'کت‌وشلوار' => [SuitRangeCatalog::class],
            'لباس زیر' => [UnderwearRangeCatalog::class],
        ];
    }

    #[DataProvider('newModels')]
    public function test_a_pattern_can_be_made_from_a_new_family_model(string $family): void
    {
        $key = (string) array_key_first($family::variants());

        $this->assertNotSame('', $key, "«{$family}» هیچ ردیفی ندارد.");
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
