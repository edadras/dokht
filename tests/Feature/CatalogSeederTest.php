<?php

namespace Tests\Feature;

use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Services\Pattern\GeneratorRegistry;
use App\Support\Measurements;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\PatternTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * آزمون بذرهای کاتالوگ.
 *
 * کاتالوگ فقط وقتی به دست کاربر می‌رسد که این دو بذر درست کار کنند: هر
 * تولیدکننده‌ای که در فهرست باشد باید یک الگوی آماده در کتابخانه داشته باشد، و
 * اجرای دوباره بذر نباید ردیف تکراری بسازد یا داده را خراب کند.
 */
class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    /** کدهایی که پروژه‌های ساخته‌شده به آن‌ها ارجاع می‌دهند و نباید عوض شوند. */
    protected const STABLE_GARMENT_CODES = [
        'shirt', 'blouse', 'shomiz', 'top', 'tshirt', 'blazer', 'cardigan', 'manteau', 'coat',
        'skirt_straight', 'skirt_gored', 'skirt_circle', 'pants', 'shorts', 'jumpsuit',
        'dress', 'evening_dress', 'cocktail_dress', 'bridal_dress',
    ];

    /** کد الگوهای پایه که در کتابخانه لینک شده‌اند. */
    protected const STABLE_TEMPLATE_CODES = [
        'bodice-block', 'sleeve-basic', 'skirt-a-line', 'skirt-pencil', 'pants-straight',
        'pants-wide-leg', 'shirt-classic', 'dress-basic', 'blazer-basic', 'tshirt-knit',
    ];

    protected function seed_catalog(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->seed(PatternTemplateSeeder::class);
    }

    public function test_the_catalog_covers_far_more_than_the_first_nineteen_garments(): void
    {
        $this->seed_catalog();

        $this->assertGreaterThan(40, GarmentType::count());

        foreach (array_keys(GarmentType::CATEGORIES) as $category) {
            $this->assertGreaterThan(
                0,
                GarmentType::where('category', $category)->count(),
                "دسته «{$category}» هیچ نوع لباسی ندارد.",
            );
        }
    }

    public function test_stable_codes_survive_the_seeder(): void
    {
        $this->seed_catalog();

        foreach (static::STABLE_GARMENT_CODES as $code) {
            $this->assertDatabaseHas('garment_types', ['code' => $code, 'is_active' => true]);
        }

        foreach (static::STABLE_TEMPLATE_CODES as $code) {
            $this->assertDatabaseHas('pattern_templates', ['code' => $code, 'workshop_id' => null]);
        }
    }

    public function test_every_garment_type_is_described_with_known_keys(): void
    {
        $this->seed_catalog();

        $parts = array_keys(GarmentType::PART_LABELS);
        $fields = array_keys(Measurements::FIELDS);

        foreach (GarmentType::all() as $garment) {
            $where = "نوع لباس «{$garment->code}»";

            $this->assertArrayHasKey($garment->category, GarmentType::CATEGORIES, "دسته {$where} ناشناخته است.");
            $this->assertNotEmpty($garment->parts, "{$where} هیچ جزئی ندارد.");

            foreach ($garment->parts as $part) {
                $this->assertContains($part, $parts, "جزء «{$part}» در {$where} ناشناخته است.");
            }

            foreach ($garment->required_measurements ?? [] as $field) {
                $this->assertContains($field, $fields, "اندازه «{$field}» در {$where} ناشناخته است.");
            }

            $preferences = $garment->fabric_preferences ?? [];

            foreach (['drape', 'stiffness'] as $key) {
                $this->assertArrayHasKey('ideal', $preferences[$key] ?? [], "{$key} در {$where} مقدار آرمانی ندارد.");
                $this->assertArrayHasKey('tolerance', $preferences[$key] ?? [], "{$key} در {$where} رواداری ندارد.");
            }

            $this->assertArrayHasKey('min', $preferences['weight_gsm'] ?? []);
            $this->assertArrayHasKey('max', $preferences['weight_gsm'] ?? []);
            $this->assertArrayHasKey('max', $preferences['transparency'] ?? []);
            $this->assertArrayHasKey('max', $preferences['stretch_weft'] ?? $preferences['stretch'] ?? []);
            $this->assertLessThanOrEqual(
                $preferences['weight_gsm']['max'],
                $preferences['weight_gsm']['min'],
                "بازه وزن پارچه در {$where} وارونه است.",
            );
        }
    }

    public function test_every_registered_generator_gets_a_template(): void
    {
        $this->seed_catalog();

        $templates = PatternTemplate::whereNull('workshop_id')->get()->keyBy('generator');

        foreach (GeneratorRegistry::keys() as $key) {
            $this->assertTrue($templates->has($key), "تولیدکننده «{$key}» الگویی در کتابخانه ندارد.");

            $template = $templates[$key];
            $generator = GeneratorRegistry::make($key);

            $this->assertNotSame('', (string) $template->name_fa);
            $this->assertNotSame('', (string) $template->description);
            $this->assertNotNull($template->garment_type_id);
            $this->assertSame(
                array_keys($generator->paramsSchema()),
                array_keys($template->params_schema ?? []),
                "توضیح پارامترهای «{$key}» با خود تولیدکننده هم‌خوان نیست.",
            );
            $this->assertSame(
                array_keys($generator->defaultParams()),
                array_keys($template->default_params ?? []),
                "پارامترهای پیش‌فرض «{$key}» ناقص است.",
            );
            $this->assertStringContainsString('<svg', (string) $template->preview_svg, "پیش‌نمایش «{$key}» ساخته نشده است.");
        }
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed_catalog();

        $garments = GarmentType::count();
        $templates = PatternTemplate::count();
        $fingerprint = PatternTemplate::orderBy('code')->pluck('preview_svg', 'code')->toArray();

        $this->seed_catalog();

        $this->assertSame($garments, GarmentType::count());
        $this->assertSame($templates, PatternTemplate::count());
        $this->assertSame($fingerprint, PatternTemplate::orderBy('code')->pluck('preview_svg', 'code')->toArray());
    }

    public function test_templates_are_spread_across_every_generator_group(): void
    {
        $this->seed_catalog();

        foreach (GeneratorRegistry::grouped() as $group => $row) {
            $keys = array_keys($row['generators']);
            $count = PatternTemplate::whereNull('workshop_id')->whereIn('generator', $keys)->count();

            $this->assertSame(
                count($keys),
                $count,
                "گروه «{$group}» ".count($keys)." مدل دارد ولی {$count} الگو ساخته شده است.",
            );
        }
    }
}
