<?php

namespace Tests\Feature;

use App\Models\DesignRule;
use App\Models\Workshop;
use Database\Seeders\DesignRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_a_useful_body_of_global_knowledge(): void
    {
        $this->seed(DesignRuleSeeder::class);

        $this->assertGreaterThanOrEqual(18, DesignRule::count());
        $this->assertSame(0, DesignRule::whereNotNull('workshop_id')->count());

        foreach (['fabric', 'pattern', 'cutting', 'production'] as $scope) {
            $this->assertTrue(DesignRule::where('scope', $scope)->exists(), "دامنه {$scope} خالی است");
        }

        foreach ([
            'sheer-needs-lining', 'prewash-high-shrinkage', 'heavy-fabric-flowy-dress',
            'stretch-negative-ease', 'knit-tshirt-no-darts', 'nap-one-direction',
            'match-stripe-plaid', 'slippery-fabric-cutting', 'fraying-seam-allowance',
            'heat-sensitive-low-iron', 'sheer-french-seam', 'hem-allowance-default',
            'hem-allowance-fluid', 'bias-cut-high-stretch', 'knit-overlock-seam',
        ] as $code) {
            $this->assertDatabaseHas('design_rules', ['code' => $code, 'workshop_id' => null]);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(DesignRuleSeeder::class);
        $first = DesignRule::count();

        $this->seed(DesignRuleSeeder::class);

        $this->assertSame($first, DesignRule::count());
    }

    public function test_index_lists_global_and_workshop_rules_with_scope_tabs(): void
    {
        $this->actingAsWorkshopUser();

        DesignRule::factory()->create(['name' => 'قاعده سراسری', 'scope' => 'fabric']);
        DesignRule::factory()->forWorkshop($this->workshop()->id)
            ->create(['name' => 'قاعده کارگاه من', 'scope' => 'cutting']);

        $this->get(route('rules.index'))
            ->assertOk()
            ->assertSee('قاعده سراسری')
            ->assertSee('قاعده کارگاه من')
            ->assertSee('سراسری')
            ->assertSee('قاعده کارگاه شما');

        $this->get(route('rules.index', ['scope' => 'cutting']))
            ->assertOk()
            ->assertSee('قاعده کارگاه من')
            ->assertDontSee('قاعده سراسری');
    }

    public function test_index_shows_an_empty_state(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('rules.index'))->assertOk()->assertSee('قاعده‌ای در این دامنه نیست');
    }

    public function test_a_rule_is_built_from_the_friendly_form_without_any_json(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('rules.create'))->assertOk()->assertSee('چه وقت اجرا شود؟');

        $this->post(route('rules.store'), [
            'name' => 'پارچه لیز را با کاغذ ببر',
            'scope' => 'cutting',
            'severity' => 'warning',
            'priority' => 30,
            'is_active' => '1',
            'match' => 'all',
            'conditions' => [
                ['field' => 'fabric.slippage', 'op' => '>', 'value' => '۰.۷'],
                ['field' => 'fabric.family', 'op' => 'in', 'value' => 'woven، knit'],
                ['field' => '', 'op' => '>', 'value' => ''],
            ],
            'actions' => [
                ['type' => 'note', 'text' => 'زیر پارچه کاغذ بگذار'],
                ['type' => 'seam_allowance', 'edge' => 'all', 'value' => '1.5'],
            ],
        ])->assertRedirect(route('rules.index', ['scope' => 'cutting']));

        $rule = DesignRule::where('workshop_id', $this->workshop()->id)->firstOrFail();

        $this->assertSame('پارچه لیز را با کاغذ ببر', $rule->name);
        $this->assertSame('cutting', $rule->scope);
        $this->assertSame('warning', $rule->severity);
        $this->assertSame(30, $rule->priority);
        $this->assertNotEmpty($rule->code);

        // ردیف خالی حذف و مقادیر فارسی به عدد تبدیل شده‌اند
        $this->assertSame([
            'all' => [
                ['field' => 'fabric.slippage', 'op' => '>', 'value' => 0.7],
                ['field' => 'fabric.family', 'op' => 'in', 'value' => ['woven', 'knit']],
            ],
        ], $rule->conditions);

        $this->assertSame([
            ['type' => 'note', 'text' => 'زیر پارچه کاغذ بگذار'],
            ['type' => 'seam_allowance', 'edge' => 'all', 'value' => 1.5],
        ], $rule->actions);
    }

    public function test_a_bool_condition_and_an_any_match_are_stored_correctly(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('rules.store'), [
            'name' => 'پارچه خواب‌دار',
            'scope' => 'cutting',
            'severity' => 'info',
            'match' => 'any',
            'conditions' => [
                ['field' => 'fabric.has_nap', 'op' => '=', 'value' => '1'],
                ['field' => 'fabric.directional', 'op' => '=', 'value' => '0'],
            ],
            'actions' => [['type' => 'one_direction_cutting']],
        ])->assertRedirect();

        $rule = DesignRule::where('workshop_id', $this->workshop()->id)->firstOrFail();

        $this->assertSame([
            'any' => [
                ['field' => 'fabric.has_nap', 'op' => '=', 'value' => true],
                ['field' => 'fabric.directional', 'op' => '=', 'value' => false],
            ],
        ], $rule->conditions);

        $this->assertSame([['type' => 'one_direction_cutting']], $rule->actions);
    }

    public function test_a_rule_needs_a_name_a_condition_and_an_action(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('rules.store'), [
            'scope' => 'fabric',
            'severity' => 'info',
            'conditions' => [],
            'actions' => [],
        ])->assertSessionHasErrors(['name', 'conditions', 'actions']);

        $this->assertSame(0, DesignRule::count());
    }

    public function test_an_unknown_fact_field_is_rejected(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('rules.store'), [
            'name' => 'قاعده نامعتبر',
            'scope' => 'fabric',
            'severity' => 'info',
            'conditions' => [['field' => 'fabric.made_up', 'op' => '>', 'value' => 1]],
            'actions' => [['type' => 'note', 'text' => 'چیزی']],
        ])->assertSessionHasErrors('conditions.0.field');
    }

    public function test_a_workshop_rule_can_be_edited_and_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $rule = DesignRule::factory()->forWorkshop($this->workshop()->id)->create([
            'name' => 'نام قدیمی',
            'scope' => 'fabric',
        ]);

        $this->get(route('rules.edit', $rule))->assertOk()->assertSee('نام قدیمی');

        $this->put(route('rules.update', $rule), [
            'name' => 'نام تازه',
            'scope' => 'fabric',
            'severity' => 'warning',
            'conditions' => [['field' => 'fabric.drape', 'op' => '>=', 'value' => '0.8']],
            'actions' => [['type' => 'note', 'text' => 'خیلی لخت است']],
        ])->assertRedirect(route('rules.index', ['scope' => 'fabric']));

        $rule->refresh();

        $this->assertSame('نام تازه', $rule->name);
        $this->assertSame('warning', $rule->severity);

        $this->delete(route('rules.destroy', $rule))->assertRedirect(route('rules.index'));
        $this->assertDatabaseMissing('design_rules', ['id' => $rule->id]);
    }

    public function test_the_edit_form_reloads_an_any_match_rule_with_payload_actions(): void
    {
        $this->actingAsWorkshopUser();

        $rule = DesignRule::factory()->forWorkshop($this->workshop()->id)->create([
            'name' => 'قاعده ترکیبی',
            'scope' => 'pattern',
            'conditions' => ['any' => [['field' => 'fabric.family', 'op' => 'in', 'value' => ['knit', 'woven']]]],
            'actions' => [['type' => 'set_ease', 'key' => 'bust', 'value' => -2]],
        ]);

        $this->get(route('rules.edit', $rule))
            ->assertOk()
            ->assertSee('قاعده ترکیبی')
            ->assertSee('حداقل یکی از شرط‌ها برقرار باشد');
    }

    public function test_global_rules_are_read_only_for_a_workshop(): void
    {
        $this->actingAsWorkshopUser();

        $global = DesignRule::factory()->create(['name' => 'قاعده سراسری', 'scope' => 'fabric']);

        $this->get(route('rules.edit', $global))->assertNotFound();
        $this->put(route('rules.update', $global), ['name' => 'دستکاری'])->assertNotFound();
        $this->delete(route('rules.destroy', $global))->assertNotFound();

        $this->assertSame('قاعده سراسری', $global->fresh()->name);
    }

    public function test_a_workshop_cannot_touch_the_rules_of_another_workshop(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $foreign = DesignRule::factory()->forWorkshop($other->id)
            ->create(['name' => 'قاعده کارگاه دیگر', 'scope' => 'fabric']);

        $this->get(route('rules.index'))->assertOk()->assertDontSee('قاعده کارگاه دیگر');
        $this->get(route('rules.edit', $foreign))->assertNotFound();
        $this->put(route('rules.update', $foreign), ['name' => 'دستکاری'])->assertNotFound();
        $this->delete(route('rules.destroy', $foreign))->assertNotFound();

        $this->assertDatabaseHas('design_rules', ['id' => $foreign->id, 'name' => 'قاعده کارگاه دیگر']);
    }

    public function test_a_view_only_member_cannot_reach_the_rules(): void
    {
        $this->actingAsWorkshopUser('viewer');

        $this->get(route('rules.index'))->assertForbidden();
        $this->get(route('rules.create'))->assertForbidden();
    }
}
