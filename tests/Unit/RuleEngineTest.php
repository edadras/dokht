<?php

namespace Tests\Unit;

use App\Models\DesignRule;
use App\Models\Fabric;
use App\Models\FabricType;
use App\Models\GarmentType;
use App\Models\Project;
use App\Models\Workshop;
use App\Services\Rules\RuleEngine;
use Database\Seeders\DesignRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function engine(): RuleEngine
    {
        return app(RuleEngine::class);
    }

    /** واقعیت‌های ثابتی که همه عملگرها روی آن آزمون می‌شوند. */
    protected function facts(): array
    {
        return [
            'fabric' => [
                'transparency' => 0.7,
                'weight_gsm' => 200,
                'family' => 'knit',
                'has_nap' => true,
            ],
            'garment' => [
                'category' => 'one_piece',
                'code' => 'dress-a-line',
                'parts' => ['sleeve', 'collar'],
            ],
        ];
    }

    protected function rule(string $code, array $condition): void
    {
        DesignRule::factory()->create([
            'code' => $code,
            'name' => 'قاعده '.$code,
            'scope' => 'fabric',
            'conditions' => ['all' => [$condition]],
            'actions' => [['type' => 'note', 'text' => 'متن '.$code]],
        ]);
    }

    public function test_every_operator_is_evaluated(): void
    {
        $this->actingAsWorkshopUser();

        $this->rule('gt', ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.6]);
        $this->rule('gt-fail', ['field' => 'fabric.transparency', 'op' => '>', 'value' => 0.9]);
        $this->rule('gte', ['field' => 'fabric.weight_gsm', 'op' => '>=', 'value' => 200]);
        $this->rule('lt-fail', ['field' => 'fabric.transparency', 'op' => '<', 'value' => 0.6]);
        $this->rule('lte', ['field' => 'fabric.weight_gsm', 'op' => '<=', 'value' => 200]);
        $this->rule('eq', ['field' => 'fabric.family', 'op' => '=', 'value' => 'knit']);
        $this->rule('eq-bool', ['field' => 'fabric.has_nap', 'op' => '=', 'value' => true]);
        $this->rule('neq', ['field' => 'fabric.family', 'op' => '!=', 'value' => 'woven']);
        $this->rule('in', ['field' => 'garment.category', 'op' => 'in', 'value' => ['one_piece', 'top']]);
        $this->rule('in-fail', ['field' => 'garment.category', 'op' => 'in', 'value' => ['bottom']]);
        $this->rule('contains-string', ['field' => 'garment.code', 'op' => 'contains', 'value' => 'a-line']);
        $this->rule('contains-list', ['field' => 'garment.parts', 'op' => 'contains', 'value' => 'sleeve']);
        $this->rule('between', ['field' => 'fabric.weight_gsm', 'op' => 'between', 'value' => [150, 250]]);
        $this->rule('between-fail', ['field' => 'fabric.weight_gsm', 'op' => 'between', 'value' => [300, 400]]);

        $codes = collect($this->engine()->evaluate('fabric', $this->facts()))->pluck('code')->all();

        $this->assertEqualsCanonicalizing([
            'gt', 'gte', 'lte', 'eq', 'eq-bool', 'neq', 'in',
            'contains-string', 'contains-list', 'between',
        ], $codes);
    }

    public function test_unknown_fields_never_throw_and_never_match(): void
    {
        $this->actingAsWorkshopUser();

        $this->rule('unknown', ['field' => 'fabric.does_not_exist', 'op' => '>', 'value' => 1]);
        $this->rule('missing-field', ['op' => '>', 'value' => 1]);
        $this->rule('broken-op', ['field' => 'fabric.transparency', 'op' => '~~', 'value' => 1]);

        $this->assertSame([], $this->engine()->evaluate('fabric', $this->facts()));
    }

    public function test_any_and_none_blocks_work(): void
    {
        $this->actingAsWorkshopUser();

        DesignRule::factory()->create([
            'code' => 'any-rule',
            'scope' => 'fabric',
            'conditions' => ['any' => [
                ['field' => 'fabric.family', 'op' => '=', 'value' => 'woven'],
                ['field' => 'fabric.family', 'op' => '=', 'value' => 'knit'],
            ]],
            'actions' => [['type' => 'note', 'text' => 'یکی برقرار شد']],
        ]);

        DesignRule::factory()->create([
            'code' => 'none-rule',
            'scope' => 'fabric',
            'conditions' => [
                'all' => [['field' => 'fabric.weight_gsm', 'op' => '>', 'value' => 100]],
                'none' => [['field' => 'fabric.family', 'op' => '=', 'value' => 'knit']],
            ],
            'actions' => [['type' => 'note', 'text' => 'نباید برقرار شود']],
        ]);

        $codes = collect($this->engine()->evaluate('fabric', $this->facts()))->pluck('code')->all();

        $this->assertSame(['any-rule'], $codes);
    }

    public function test_workshop_rules_and_global_rules_are_both_loaded_but_other_workshops_are_not(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();

        DesignRule::factory()->create(['code' => 'global-one', 'scope' => 'fabric']);
        DesignRule::factory()->forWorkshop($this->workshop()->id)->create(['code' => 'mine', 'scope' => 'fabric']);
        DesignRule::factory()->forWorkshop($other->id)->create(['code' => 'theirs', 'scope' => 'fabric']);
        DesignRule::factory()->inactive()->create(['code' => 'off', 'scope' => 'fabric']);

        $codes = collect($this->engine()->evaluate('fabric', $this->facts()))->pluck('code')->all();

        $this->assertEqualsCanonicalizing(['global-one', 'mine'], $codes);
    }

    public function test_a_sheer_fabric_triggers_the_lining_rule(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $fabric = Fabric::factory()
            ->for(FabricType::factory()->fluid())
            ->create(['workshop_id' => $this->workshop()->id, 'gsm' => null]);

        $results = $this->engine()->evaluate('fabric', $this->engine()->factsFor($fabric));

        $this->assertTrue($this->engine()->hasAction($results, 'suggest_lining'));
        $this->assertContains('sheer-needs-lining', collect($results)->pluck('code')->all());
    }

    public function test_a_stretchy_knit_gets_negative_ease(): void
    {
        $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $fabric = Fabric::factory()
            ->for(FabricType::factory()->stretchy())
            ->create(['workshop_id' => $this->workshop()->id, 'gsm' => null]);

        $garmentType = GarmentType::factory()->create([
            'code' => 'blouse-knit',
            'category' => 'top',
            'default_ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);

        $engine = $this->engine();
        $results = $engine->evaluate('pattern', $engine->factsFor($fabric, $garmentType));
        $ease = $engine->applyEase($garmentType->ease(), $results);

        $this->assertContains('stretch-negative-ease', collect($results)->pluck('code')->all());
        $this->assertSame(4.0, $ease['bust']);
        $this->assertSame(2.0, $ease['waist']);
        $this->assertLessThan(6, $ease['hip']);
    }

    public function test_seam_allowance_overrides_are_collected(): void
    {
        $this->actingAsWorkshopUser();

        DesignRule::factory()->create([
            'code' => 'wide-seam',
            'scope' => 'cutting',
            'conditions' => ['all' => [['field' => 'fabric.weight_gsm', 'op' => '>', 'value' => 100]]],
            'actions' => [
                ['type' => 'seam_allowance', 'edge' => 'side', 'value' => 1.5],
                ['type' => 'hem_allowance', 'value' => 3],
            ],
        ]);

        $results = $this->engine()->evaluate('cutting', $this->facts());

        $this->assertSame(['side' => 1.5, 'hem' => 3.0], $this->engine()->seamAllowanceOverrides($results));
    }

    public function test_actions_get_a_persian_message_even_without_one(): void
    {
        $this->actingAsWorkshopUser();

        DesignRule::factory()->create([
            'code' => 'auto-message',
            'name' => 'قاعده بدون پیام',
            'scope' => 'fabric',
            'conditions' => [],
            'actions' => [['type' => 'suggest_lining'], ['type' => 'prewash']],
        ]);

        $results = $this->engine()->evaluate('fabric', []);

        $this->assertCount(1, $results);
        $this->assertSame('برای این پارچه آستر لازم است.', $results[0]['actions'][0]['message']);
        $this->assertSame('پارچه را پیش از برش بشویید و اتو کنید.', $results[0]['actions'][1]['message']);
    }

    public function test_for_project_is_null_safe_without_fabric_or_pattern(): void
    {
        $user = $this->actingAsWorkshopUser();
        $this->seed(DesignRuleSeeder::class);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'fabric_id' => null,
            'pattern_id' => null,
        ]);

        $results = $this->engine()->forProject($project);

        $this->assertIsArray($results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('scope', $result);
            $this->assertArrayHasKey('actions', $result);
        }
    }

    public function test_for_project_covers_every_project_scope(): void
    {
        $this->actingAsWorkshopUser();

        foreach (RuleEngine::PROJECT_SCOPES as $scope) {
            DesignRule::factory()->create([
                'code' => 'always-'.$scope,
                'scope' => $scope,
                'conditions' => [],
                'actions' => [['type' => 'note', 'text' => 'همیشه']],
            ]);
        }

        $project = Project::factory()->create(['workshop_id' => $this->workshop()->id]);

        $scopes = collect($this->engine()->forProject($project))->pluck('scope')->all();

        $this->assertEqualsCanonicalizing(RuleEngine::PROJECT_SCOPES, $scopes);
    }
}
