<?php

namespace Tests\Feature;

use App\Models\Fitting;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Services\Fit\AlterationService;
use App\Services\Pattern\PatternBuilder;
use App\Support\Alterations;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حلقه پرو: از آنچه خیاط روی تن مشتری دید تا الگوی اصلاح‌شده.
 */
class FittingLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function projectWithPattern(): Project
    {
        $template = PatternTemplate::factory()->create([
            'generator' => 'shirt_classic',
            'name_fa' => 'پیراهن کلاسیک',
        ]);

        $pattern = app(PatternBuilder::class)->createPattern($template, ['name' => 'پیراهن آزمون'], [
            'measurements' => Measurements::complete([]),
        ]);

        return Project::create([
            'name' => 'پروژه آزمون',
            'pattern_id' => $pattern->id,
            'garment_type_id' => $template->garment_type_id,
            'status' => 'draft',
        ]);
    }

    public function test_an_alteration_moves_the_right_dial_and_leaves_the_others_alone(): void
    {
        $result = Alterations::apply(
            [['key' => 'sleeve_length', 'value' => -2], ['key' => 'waist_ease', 'value' => 3]],
            ['arm_length' => 58, 'bust' => 92, 'waist' => 74, 'hip' => 98, 'height' => 165, 'shoulder_width' => 39],
            ['waist' => 4, 'bust' => 6],
            ['bodice_length_extra' => 0],
        );

        $this->assertSame(56.0, $result['measurements']['arm_length'], 'قد آستین دو سانتی کوتاه می‌شود.');
        $this->assertSame(7.0, $result['ease']['waist'], 'آزادی کمر سه سانتی بازتر می‌شود.');
        $this->assertEquals(6, $result['ease']['bust'], 'آزادی سینه دست‌نخورده می‌ماند.');
        $this->assertCount(2, $result['applied']);
    }

    public function test_an_unknown_or_zero_alteration_is_ignored(): void
    {
        $result = Alterations::apply(
            [['key' => 'قد_دم', 'value' => 5], ['key' => 'sleeve_length', 'value' => 0]],
            ['arm_length' => 58],
            [],
            [],
        );

        $this->assertSame([], $result['applied']);
    }

    public function test_an_alteration_is_clamped_to_its_own_range(): void
    {
        $this->assertSame(15.0, Alterations::clamp('sleeve_length', 40));
        $this->assertSame(-15.0, Alterations::clamp('sleeve_length', -40));
        $this->assertSame(0.0, Alterations::clamp('back_curve', -3), 'قوز پشت منفی معنا ندارد.');
    }

    public function test_recording_a_fitting_and_applying_it_rebuilds_the_pattern(): void
    {
        $user = $this->actingAsWorkshopUser();
        $project = $this->projectWithPattern();
        $before = $project->pattern->measurements['arm_length'];
        $easeBefore = (float) ($project->pattern->ease['waist'] ?? 0);

        $response = $this->actingAs($user)->post(route('projects.fittings.store', $project), [
            'notes' => 'آستین بلند بود',
            'values' => ['sleeve_length' => -2, 'waist_ease' => 2],
        ]);

        $response->assertRedirect(route('projects.fittings', $project));

        $fitting = Fitting::query()->firstOrFail();
        $this->assertSame(1, $fitting->round);
        $this->assertCount(2, $fitting->adjustments);
        $this->assertFalse($fitting->isApplied());

        // تا وقتی اعمال نشده، الگو دست‌نخورده است
        $this->assertSame($before, $project->pattern->fresh()->measurements['arm_length']);

        $this->actingAs($user)
            ->post(route('projects.fittings.apply', [$project, $fitting]))
            ->assertRedirect(route('projects.fittings', $project));

        $pattern = $project->pattern->fresh();

        $this->assertSame($before - 2, $pattern->measurements['arm_length']);
        $this->assertSame($easeBefore + 2, (float) ($pattern->ease['waist'] ?? 0));
        $this->assertTrue($fitting->fresh()->isApplied());
    }

    public function test_applying_a_fitting_leaves_a_version_behind_so_it_can_be_undone(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->projectWithPattern();
        $versionBefore = (int) $project->pattern->version;
        $shoulderBefore = (float) $project->pattern->measurements['shoulder_width'];

        $fitting = Fitting::create([
            'project_id' => $project->id,
            'pattern_id' => $project->pattern_id,
            'fitted_on' => now(),
            'round' => 1,
            'adjustments' => [['key' => 'shoulder_width', 'value' => -1.5]],
        ]);

        app(AlterationService::class)->apply($fitting);

        $pattern = $project->pattern->fresh();

        $this->assertGreaterThan($versionBefore, (int) $pattern->version, 'اصلاح باید شماره نسخه را جلو ببرد.');

        // عکسِ نسخه پیشین باید وضعیت پیش از اصلاح را نگه داشته باشد
        $snapshot = $pattern->versions()->where('version', $versionBefore)->firstOrFail();

        $this->assertSame(
            $shoulderBefore,
            (float) $snapshot->snapshot['pattern']['measurements']['shoulder_width'],
            'برای برگشت از اصلاح، وضعیت پیشین باید در نسخه بماند.',
        );
    }

    public function test_a_fitting_cannot_be_applied_twice(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->projectWithPattern();

        $fitting = Fitting::create([
            'project_id' => $project->id,
            'pattern_id' => $project->pattern_id,
            'fitted_on' => now(),
            'round' => 1,
            'adjustments' => [['key' => 'sleeve_length', 'value' => -1]],
        ]);

        $service = app(AlterationService::class);
        $service->apply($fitting);

        $this->expectExceptionMessage('یک بار اعمال شده');
        $service->apply($fitting->fresh());
    }

    public function test_two_fittings_in_a_row_stack_on_each_other(): void
    {
        $this->actingAsWorkshopUser();
        $project = $this->projectWithPattern();
        $before = $project->pattern->measurements['arm_length'];
        $service = app(AlterationService::class);

        foreach ([1, 2] as $round) {
            $service->apply(Fitting::create([
                'project_id' => $project->id,
                'pattern_id' => $project->pattern_id,
                'fitted_on' => now(),
                'round' => $round,
                'adjustments' => [['key' => 'sleeve_length', 'value' => -1]],
            ]));
        }

        $this->assertSame(
            $before - 2,
            $project->pattern->fresh()->measurements['arm_length'],
            'دو پروی پیاپی باید روی هم جمع شوند، همان‌طور که خیاط دو بار اصلاح می‌کند.',
        );
    }

    public function test_the_fit_report_suggests_ready_made_alterations(): void
    {
        $suggested = Alterations::suggestFromFit([
            'zones' => [
                ['key' => 'waist', 'label' => 'دور کمر', 'level' => 'tight', 'ease_cm' => -3.0, 'note' => ''],
                ['key' => 'bust', 'label' => 'دور سینه', 'level' => 'good', 'ease_cm' => 6.0, 'note' => ''],
            ],
        ]);

        $this->assertCount(1, $suggested, 'ناحیه مناسب پیشنهادی نمی‌سازد.');
        $this->assertSame('waist_ease', $suggested[0]['key']);
        $this->assertGreaterThan(0, $suggested[0]['value'], 'ناحیه تنگ باید بازتر شود.');
    }

    public function test_the_fitting_page_opens_and_lists_the_alteration_catalogue(): void
    {
        $user = $this->actingAsWorkshopUser();
        $project = $this->projectWithPattern();

        $response = $this->actingAs($user)->get(route('projects.fittings', $project));

        $response->assertOk();
        $response->assertSee('پرو روی تن مشتری');
        $response->assertSee(Alterations::label('sleeve_length'));
        $response->assertSee('values[sleeve_length]', false);
    }
}
