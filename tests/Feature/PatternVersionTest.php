<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Workshop;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternVersionService;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function pattern(): Pattern
    {
        $template = PatternTemplate::factory()->generator('bodice_block')->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'بالاتنه نسخه‌دار',
            'base_size' => '40',
        ], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6],
        ]);
    }

    public function test_initial_version_is_recorded_on_creation(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $version = $pattern->versions()->first();

        $this->assertSame(1, $pattern->versions()->count());
        $this->assertSame(2, $version->pieceCount());
        $this->assertSame(2, count($version->snapshot['pieces']));
        $this->assertNotEmpty($version->snapshot['pattern']['measurements']);
        $this->assertSame(auth()->id(), $version->created_by);
    }

    public function test_index_shows_the_timeline_with_authors_and_notes(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        app(PatternBuilder::class)->regenerate($pattern, ['ease' => ['bust' => 12], 'note' => 'گشادتر شد']);

        $this->get(route('patterns.versions', $pattern))
            ->assertOk()
            ->assertSee('تاریخچه نسخه‌ها')
            ->assertSee('گشادتر شد')
            ->assertSee($user->name)
            ->assertSee('وضعیت فعلی');
    }

    public function test_index_compares_area_changes_between_versions(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        app(PatternBuilder::class)->regenerate($pattern, ['ease' => ['bust' => 24]]);

        $this->get(route('patterns.versions', $pattern))
            ->assertOk()
            ->assertSee('مساحت این نسخه')
            ->assertSee('بالاتنه جلو');
    }

    public function test_store_snapshots_the_current_state(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->post(route('patterns.versions.store', $pattern), ['note' => 'قبل از تغییر یقه'])
            ->assertRedirect();

        $pattern->refresh();

        $this->assertSame(1, $pattern->versions()->count());
        $this->assertSame(2, (int) $pattern->version);
        $this->assertSame('قبل از تغییر یقه', $pattern->versions()->first()->note);

        $this->post(route('patterns.versions.store', $pattern), [])->assertRedirect();

        $pattern->refresh();

        $this->assertSame(2, $pattern->versions()->count());
        $this->assertSame(3, (int) $pattern->version);
    }

    public function test_restore_brings_back_the_stored_geometry(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $originalWidth = $pattern->pieces->first()->width();

        app(PatternBuilder::class)->regenerate($pattern, ['ease' => ['bust' => 26]]);
        $pattern->refresh()->load('pieces');
        $wideWidth = $pattern->pieces->first()->width();

        $this->assertGreaterThan($originalWidth, $wideWidth);

        $version = $pattern->versions()->where('version', 1)->firstOrFail();

        $this->post(route('patterns.versions.restore', [$pattern, $version]))
            ->assertRedirect(route('patterns.show', $pattern));

        $pattern->refresh()->load('pieces');

        $this->assertEqualsWithDelta($originalWidth, $pattern->pieces->first()->width(), 0.02);
        $this->assertCount(2, $pattern->pieces);
        $this->assertSame(6, (int) $pattern->ease['bust']);
        // وضعیت پیش از بازگردانی هم نگه داشته می‌شود
        $this->assertSame(2, $pattern->versions()->count());
    }

    public function test_restore_rejects_a_version_of_another_pattern(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = $this->pattern();
        $other = $this->pattern();

        $this->post(route('patterns.versions.restore', [$pattern, $other->versions()->first()]))
            ->assertNotFound();
    }

    public function test_geometry_save_creates_a_restorable_version(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $piece = $pattern->pieces->first();

        $this->putJson(route('patterns.geometry', $pattern), [
            'pieces' => [[
                'id' => $piece->id,
                'outline' => [
                    ['x' => 0, 'y' => 0], ['x' => 12, 'y' => 0], ['x' => 12, 'y' => 12], ['x' => 0, 'y' => 12],
                ],
            ]],
            'note' => 'ویرایش دستی',
        ])->assertOk();

        $version = $pattern->versions()->where('version', 1)->firstOrFail();
        $this->assertSame('ویرایش دستی', $version->note);

        app(PatternVersionService::class)->restore($pattern->refresh()->load('pieces'), $version);

        $this->assertGreaterThan(20, $pattern->refresh()->load('pieces')->pieces->first()->width());
    }

    public function test_versions_of_another_workshop_are_not_reachable(): void
    {
        $other = Workshop::factory()->create();
        $foreign = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $other->id]);

        $this->actingAsWorkshopUser();

        $this->get(route('patterns.versions', $foreign))->assertNotFound();
        $this->post(route('patterns.versions.store', $foreign), [])->assertNotFound();
    }

    public function test_diff_reports_added_and_removed_pieces(): void
    {
        $this->actingAsWorkshopUser();
        $service = app(PatternVersionService::class);

        $from = ['pieces' => [
            ['code' => 'front', 'name' => 'جلو', 'outline' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 10], ['x' => 0, 'y' => 10]]],
            ['code' => 'old', 'name' => 'قدیمی', 'outline' => [['x' => 0, 'y' => 0], ['x' => 5, 'y' => 0], ['x' => 5, 'y' => 5], ['x' => 0, 'y' => 5]]],
        ]];

        $to = ['pieces' => [
            ['code' => 'front', 'name' => 'جلو', 'outline' => [['x' => 0, 'y' => 0], ['x' => 12, 'y' => 0], ['x' => 12, 'y' => 10], ['x' => 0, 'y' => 10]]],
            ['code' => 'sleeve', 'name' => 'آستین', 'outline' => [['x' => 0, 'y' => 0], ['x' => 8, 'y' => 0], ['x' => 8, 'y' => 8], ['x' => 0, 'y' => 8]]],
        ]];

        $diff = $service->diff($from, $to);

        $this->assertSame(['sleeve'], $diff['added']);
        $this->assertSame(['old'], $diff['removed']);
        $this->assertSame('front', $diff['changed'][0]['code']);
        $this->assertSame(20.0, $diff['changed'][0]['delta']);
    }
}
