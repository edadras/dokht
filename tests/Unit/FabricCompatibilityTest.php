<?php

namespace Tests\Unit;

use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Services\Fabric\FabricCompatibilityService;
use App\Support\FabricProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): FabricCompatibilityService
    {
        return app(FabricCompatibilityService::class);
    }

    /** پیراهن ریزشی: لخت، سبک و نرم. */
    protected function flowyDress(): GarmentType
    {
        return GarmentType::factory()->create([
            'code' => 'dress-a-line',
            'name_fa' => 'پیراهن ریزشی',
            'category' => 'one_piece',
            'fabric_preferences' => [
                'drape' => ['ideal' => 0.85, 'tolerance' => 0.25],
                'stiffness' => ['ideal' => 0.15, 'tolerance' => 0.3],
                'weight_gsm' => ['min' => 60, 'max' => 200],
                'transparency' => ['max' => 0.35],
            ],
        ]);
    }

    protected function silk(): Fabric
    {
        return Fabric::factory()->for(FabricType::factory()->fluid()->create([
            'name_fa' => 'حریر',
            'family' => 'woven',
        ]))->create(['name' => 'حریر مشکی', 'gsm' => null]);
    }

    protected function denim(): Fabric
    {
        return Fabric::factory()->for(FabricType::factory()->structured()->create([
            'name_fa' => 'جین',
            'family' => 'woven',
        ]))->create(['name' => 'جین آبی', 'gsm' => null]);
    }

    public function test_score_shape_is_complete(): void
    {
        $this->actingAsWorkshopUser();

        $score = $this->service()->score($this->silk(), $this->flowyDress());

        $this->assertIsInt($score['overall']);
        $this->assertGreaterThanOrEqual(0, $score['overall']);
        $this->assertLessThanOrEqual(100, $score['overall']);
        $this->assertContains($score['verdict'], ['suitable', 'acceptable', 'unsuitable']);
        $this->assertNotEmpty($score['verdict_label']);

        $keys = collect($score['criteria'])->pluck('key')->all();

        foreach (['drape', 'weight', 'stretch', 'transparency', 'structure', 'care'] as $expected) {
            $this->assertContains($expected, $keys);
        }

        foreach ($score['criteria'] as $criterion) {
            $this->assertNotEmpty($criterion['label']);
            $this->assertNotEmpty($criterion['reason']);
            $this->assertGreaterThanOrEqual(0, $criterion['score']);
            $this->assertLessThanOrEqual(100, $criterion['score']);
        }
    }

    public function test_silk_scores_higher_than_heavy_denim_for_a_flowy_dress(): void
    {
        $this->actingAsWorkshopUser();

        $dress = $this->flowyDress();

        $silk = $this->service()->score($this->silk(), $dress);
        $denim = $this->service()->score($this->denim(), $dress);

        $this->assertGreaterThan($denim['overall'], $silk['overall']);
        $this->assertSame('unsuitable', $denim['verdict']);
    }

    public function test_rank_sorts_fabrics_from_best_to_worst(): void
    {
        $this->actingAsWorkshopUser();

        $dress = $this->flowyDress();
        $silk = $this->silk();
        $denim = $this->denim();

        $ranked = $this->service()->rank($dress, collect([$denim, $silk]));

        $this->assertCount(2, $ranked);
        $this->assertSame($silk->id, $ranked->first()['fabric']->id);
        $this->assertGreaterThanOrEqual($ranked->last()['score']['overall'], $ranked->first()['score']['overall']);
    }

    public function test_denim_beats_silk_for_a_pair_of_trousers(): void
    {
        $this->actingAsWorkshopUser();

        $trousers = GarmentType::factory()->create([
            'code' => 'trousers',
            'name_fa' => 'شلوار',
            'category' => 'bottom',
            'fabric_preferences' => null,
        ]);

        $silk = $this->service()->score($this->silk(), $trousers);
        $denim = $this->service()->score($this->denim(), $trousers);

        $this->assertGreaterThan($silk['overall'], $denim['overall']);
    }

    public function test_suggest_fabric_types_returns_the_best_bank_entries(): void
    {
        $this->actingAsWorkshopUser();

        FabricType::factory()->fluid()->create(['name_fa' => 'حریر', 'family' => 'woven']);
        FabricType::factory()->structured()->create(['name_fa' => 'جین', 'family' => 'woven']);
        FabricType::factory()->stretchy()->create(['name_fa' => 'جرسی']);

        $suggestions = $this->service()->suggestFabricTypes($this->flowyDress(), 2);

        $this->assertCount(2, $suggestions);
        $this->assertInstanceOf(FabricType::class, $suggestions->first()['fabric_type']);

        // پارچه‌های لخت پیشنهاد می‌شوند و جین سنگین از فهرست بیرون می‌ماند
        $names = $suggestions->map(fn (array $row) => $row['fabric_type']->name_fa)->all();

        $this->assertContains('حریر', $names);
        $this->assertNotContains('جین', $names);
    }

    public function test_suggest_garment_types_ranks_models_for_a_fabric(): void
    {
        $this->actingAsWorkshopUser();

        $this->flowyDress();
        GarmentType::factory()->create(['code' => 'coat-heavy', 'name_fa' => 'پالتو', 'category' => 'outer']);

        $suggestions = $this->service()->suggestGarmentTypes($this->silk(), 5);

        $this->assertSame('پیراهن ریزشی', $suggestions->first()['garment_type']->name_fa);
    }

    public function test_sheer_fabrics_get_a_lining_note(): void
    {
        $this->actingAsWorkshopUser();

        $score = $this->service()->score($this->silk(), $this->flowyDress());

        $this->assertNotEmpty($score['notes']);
        $this->assertTrue(collect($score['notes'])->contains(fn ($note) => str_contains($note, 'آستر')));
    }

    public function test_defaults_are_used_when_the_garment_has_no_preferences(): void
    {
        $this->actingAsWorkshopUser();

        $garmentType = GarmentType::factory()->create(['category' => 'top', 'fabric_preferences' => null]);

        $preferences = $this->service()->preferences($garmentType);

        $this->assertSame(FabricCompatibilityService::CATEGORY_DEFAULTS['top'], $preferences);
    }

    public function test_a_perfect_match_is_marked_suitable(): void
    {
        $this->actingAsWorkshopUser();

        $garmentType = GarmentType::factory()->create([
            'category' => 'top',
            'fabric_preferences' => [
                'drape' => ['ideal' => 0.4, 'tolerance' => 0.25],
                'stiffness' => ['ideal' => 0.45, 'tolerance' => 0.3],
                'weight_gsm' => ['min' => 100, 'max' => 180],
                'transparency' => ['max' => 0.2],
            ],
        ]);

        $poplin = Fabric::factory()->for(FabricType::factory()->create([
            'family' => 'woven',
            'profile' => FabricProfile::make([
                'weight_gsm' => 120,
                'thickness_mm' => 0.35,
                'drape' => 0.4,
                'stiffness' => 0.45,
                'transparency' => 0.08,
                'slippage' => 0.15,
                'fraying' => 0.35,
            ])->toArray(),
        ]))->create(['gsm' => null]);

        $score = $this->service()->score($poplin, $garmentType);

        $this->assertSame('suitable', $score['verdict']);
        $this->assertGreaterThan(85, $score['overall']);
    }
}
