<?php

namespace Tests\Unit;

use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PatternBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function builder(): PatternBuilder
    {
        return app(PatternBuilder::class);
    }

    protected function template(string $generator = 'bodice_block'): PatternTemplate
    {
        return PatternTemplate::factory()->generator($generator)->create();
    }

    public function test_bodice_block_produces_front_and_back_with_sane_width(): void
    {
        $measurements = Measurements::fromSize('40');
        $ease = ['bust' => 6, 'waist' => 4, 'hip' => 6];

        $pieces = $this->builder()->buildFromTemplate($this->template(), $measurements, $ease);

        $this->assertCount(2, $pieces);

        $expected = ($measurements['bust'] + 6) / 4; // پهنای نیم‌بالاتنه = (سینه + آزادی) ÷ ۴
        $frontWidth = Geometry::width($pieces[0]['outline']);

        $this->assertGreaterThan($expected - 2.5, $frontWidth);
        $this->assertLessThan($expected + 2.5, $frontWidth);

        foreach ($pieces as $piece) {
            $this->assertSame(0.0, (float) Geometry::bounds($piece['outline'])[0]);
            $this->assertSame(0.0, (float) Geometry::bounds($piece['outline'])[1]);
            $this->assertNotEmpty($piece['meta']['edges']);
            $this->assertCount(count($piece['outline']), $piece['meta']['edges']);
            $this->assertNotEmpty($piece['grainline']);
            $this->assertNotEmpty($piece['markers']);
        }
    }

    public function test_more_ease_makes_a_wider_piece(): void
    {
        $measurements = Measurements::fromSize('40');
        $template = $this->template();

        $narrow = $this->builder()->buildFromTemplate($template, $measurements, ['bust' => 4]);
        $wide = $this->builder()->buildFromTemplate($template, $measurements, ['bust' => 16]);

        $this->assertGreaterThan(
            Geometry::width($narrow[0]['outline']) + 2,
            Geometry::width($wide[0]['outline']),
        );
    }

    public function test_every_generator_builds_closed_pieces_with_tags(): void
    {
        $measurements = Measurements::fromSize('40');

        foreach (GeneratorRegistry::keys() as $key) {
            $pieces = $this->builder()->buildFromTemplate(
                $this->template($key),
                $measurements,
                ['bust' => 8, 'waist' => 6, 'hip' => 8, 'bicep' => 5],
            );

            $this->assertNotEmpty($pieces, "تولیدکننده {$key} قطعه‌ای نساخت.");

            foreach ($pieces as $piece) {
                $this->assertGreaterThanOrEqual(3, count($piece['outline']), $key.' — '.$piece['code']);
                $this->assertGreaterThan(1, Geometry::area($piece['outline']), $key.' — '.$piece['code']);
                $this->assertArrayHasKey('edges', $piece['meta']);
                $this->assertCount(count($piece['outline']), $piece['meta']['edges'], $key.' — '.$piece['code']);
                $this->assertArrayHasKey('default', $piece['edge_allowances']);
            }
        }
    }

    public function test_knit_generator_accepts_negative_ease(): void
    {
        $measurements = Measurements::fromSize('40');
        $template = $this->template('tshirt');

        $normal = $this->builder()->buildFromTemplate($template, $measurements, ['bust' => 4]);
        $negative = $this->builder()->buildFromTemplate($template, $measurements, ['bust' => -4]);

        $this->assertLessThan(
            Geometry::width($normal[0]['outline']),
            Geometry::width($negative[0]['outline']),
        );
    }

    public function test_fold_edges_get_zero_allowance(): void
    {
        $pieces = $this->builder()->buildFromTemplate(
            $this->template(),
            Measurements::fromSize('38'),
            ['bust' => 6],
            [],
            ['default' => 1, 'hem' => 3, 'neck' => 0.7],
        );

        $front = $pieces[0];
        $foldEdge = $front['meta']['fold_edges'][0];

        $this->assertSame(0.0, $front['edge_allowances'][$foldEdge]);
        $this->assertSame(0.7, $front['edge_allowances'][array_search('neck', $front['meta']['edges'], true)]);
    }

    public function test_unknown_generator_throws_a_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeneratorRegistry::make('no_such_generator');
    }

    public function test_create_for_project_uses_project_measurements_and_leaves_project_untouched(): void
    {
        $this->actingAsWorkshopUser();

        $garmentType = GarmentType::factory()->create(['default_ease' => ['bust' => 9, 'waist' => 7]]);
        $set = MeasurementSet::factory()->forSize('44')->create(['workshop_id' => $this->workshop()->id]);

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'garment_type_id' => $garmentType->id,
            'measurement_set_id' => $set->id,
            'size' => '44',
            'name' => 'کار خانم احمدی',
        ]);

        $template = PatternTemplate::factory()->generator('bodice_block')->create([
            'garment_type_id' => $garmentType->id,
        ]);

        $pattern = $this->builder()->createForProject($project, $template);

        $this->assertSame('44', $pattern->base_size);
        $this->assertSame(9, (int) $pattern->ease['bust']);
        $this->assertSame(100.0, (float) $pattern->measurements['bust']);
        $this->assertSame($this->workshop()->id, $pattern->workshop_id);
        $this->assertCount(2, $pattern->pieces);
        $this->assertNotEmpty($pattern->sewing_relations);
        $this->assertSame(1, $pattern->versions()->count());

        $this->assertNull($project->fresh()->pattern_id);
    }

    public function test_create_for_project_falls_back_to_size_table(): void
    {
        $this->actingAsWorkshopUser();

        $project = Project::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'measurement_set_id' => null,
            'size' => '46',
        ]);

        $pattern = $this->builder()->createForProject($project, $this->template());

        $this->assertSame('46', $pattern->base_size);
        $this->assertSame(104.0, (float) $pattern->measurements['bust']);
    }

    public function test_regenerate_snapshots_and_rebuilds_pieces(): void
    {
        $this->actingAsWorkshopUser();

        $template = $this->template();
        $pattern = $this->builder()->createPattern($template, ['name' => 'بالاتنه آزمون'], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 4],
        ]);

        $before = $pattern->pieces->first()->width();
        $firstIds = $pattern->pieces->pluck('id');

        $updated = $this->builder()->regenerate($pattern, ['ease' => ['bust' => 14]]);

        $this->assertGreaterThan($before, $updated->pieces->first()->width());
        $this->assertCount(2, $updated->pieces);
        // نسخه ۱ همان وضعیت پیش از بازتولید است و نسخه جاری یک شماره جلو می‌رود
        $this->assertSame(1, $updated->versions()->count());
        $this->assertSame(2, (int) $updated->version);
        $this->assertSame(1, (int) $updated->versions()->first()->version);
        $this->assertEmpty($firstIds->intersect($updated->pieces->pluck('id')));
    }

    public function test_duplicate_copies_pieces(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $copy = app(PatternBuilder::class)->duplicate($pattern->load('pieces'));

        $this->assertNotSame($pattern->id, $copy->id);
        $this->assertCount(2, $copy->pieces);
        $this->assertStringContainsString('رونوشت', $copy->name);
    }
}
