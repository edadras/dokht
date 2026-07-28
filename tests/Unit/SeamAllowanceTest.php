<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SeamAllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeamAllowanceTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): SeamAllowanceService
    {
        return app(SeamAllowanceService::class);
    }

    public function test_offset_grows_a_square_by_the_allowance_on_every_side(): void
    {
        $square = [
            ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 10], ['x' => 0, 'y' => 10],
        ];

        $offset = $this->service()->offsetOutline($square, 1.0);

        $this->assertCount(4, $offset);
        $this->assertSame(144.0, Geometry::area($offset)); // ۱۲×۱۲
        $this->assertEqualsWithDelta(-1.0, $offset[0]['x'], 0.001);
        $this->assertEqualsWithDelta(-1.0, $offset[0]['y'], 0.001);
    }

    public function test_offset_handles_a_concave_corner(): void
    {
        // شکل L با یک گوشه فرورفته
        $shape = [
            ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 4],
            ['x' => 4, 'y' => 4], ['x' => 4, 'y' => 10], ['x' => 0, 'y' => 10],
        ];

        $offset = $this->service()->offsetOutline($shape, 1.0);

        $this->assertCount(6, $offset);
        $this->assertGreaterThan(Geometry::area($shape), Geometry::area($offset));
        $this->assertEqualsWithDelta(5.0, $offset[3]['x'], 0.001);
        $this->assertEqualsWithDelta(5.0, $offset[3]['y'], 0.001);
    }

    public function test_offset_of_a_curved_piece_stays_closed_and_grows(): void
    {
        $outline = [
            ['x' => 0, 'y' => 0],
            ['x' => 20, 'y' => 2, 'curve' => true, 'cx' => 10, 'cy' => -4],
            ['x' => 22, 'y' => 30, 'curve' => true, 'cx' => 26, 'cy' => 15],
            ['x' => 0, 'y' => 28],
        ];

        $offset = $this->service()->offsetOutline($outline, 1.5);
        $area = Geometry::area($outline);
        $offsetArea = Geometry::area($offset);

        $this->assertGreaterThan($area, $offsetArea);
        $this->assertGreaterThan(count($outline), count($offset)); // منحنی‌ها به پاره‌خط تبدیل شده‌اند

        // چندضلعی بسته است: هیچ پرش بزرگی بین نقطه‌های پشت سر هم نیست
        $count = count($offset);

        for ($i = 0; $i < $count; $i++) {
            $step = Geometry::distance($offset[$i], $offset[($i + 1) % $count]);
            $this->assertLessThan(40, $step);
        }
    }

    public function test_zero_allowance_keeps_the_polygon(): void
    {
        $square = [['x' => 0, 'y' => 0], ['x' => 8, 'y' => 0], ['x' => 8, 'y' => 8], ['x' => 0, 'y' => 8]];

        $offset = $this->service()->offsetOutline($square, 0.0);

        $this->assertSame(64.0, Geometry::area($offset));
    }

    public function test_edge_tags_come_from_meta_when_available(): void
    {
        $piece = new PatternPiece([
            'outline' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 0, 'y' => 20]],
            'meta' => ['edges' => ['neck', 'side', 'hem', 'default'], 'fold_edges' => [3]],
        ]);

        $this->assertSame(['neck', 'side', 'hem', 'default'], $this->service()->edgeTags($piece));
        $this->assertSame([3], $this->service()->foldEdges($piece));
    }

    public function test_edge_tags_are_guessed_for_hand_made_pieces(): void
    {
        $piece = new PatternPiece([
            'outline' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 0, 'y' => 20]],
            'meta' => [],
        ]);

        $tags = $this->service()->edgeTags($piece);

        $this->assertSame('waist', $tags[0]);  // لبه بالا
        $this->assertSame('side', $tags[1]);
        $this->assertSame('hem', $tags[2]);    // لبه پایین
        $this->assertSame('default', $tags[3]); // خط مرکزی
    }

    public function test_apply_fills_edge_allowances_from_the_pattern_map(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'seam_allowances' => ['default' => 1, 'hem' => 3, 'neck' => 0.7, 'side' => 1.5],
        ]);

        $pattern->pieces()->create([
            'code' => 'front',
            'name' => 'جلو',
            'outline' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 0, 'y' => 20]],
            'meta' => ['edges' => ['neck', 'side', 'hem', 'default'], 'fold_edges' => [3]],
        ]);

        $this->service()->apply($pattern->load('pieces'));

        $piece = $pattern->fresh('pieces')->pieces->first();

        $this->assertSame(0.7, $piece->allowanceFor(0));
        $this->assertSame(1.5, $piece->allowanceFor(1));
        $this->assertSame(3.0, $piece->allowanceFor(2));
        $this->assertSame(0.0, $piece->allowanceFor(3)); // روی تای پارچه
    }

    public function test_cutting_line_uses_each_edge_allowance(): void
    {
        $piece = new PatternPiece([
            'outline' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 10], ['x' => 0, 'y' => 10]],
            'meta' => ['edges' => ['waist', 'side', 'hem', 'default'], 'fold_edges' => [3]],
            'edge_allowances' => [0 => 1.0, 1 => 1.5, 2 => 3.0, 3 => 0.0, 'default' => 1.0],
        ]);

        $line = $this->service()->cuttingLine($piece);

        $this->assertEqualsWithDelta(0.0, $line[0]['x'], 0.001);   // لبه تا جابه‌جا نمی‌شود
        $this->assertEqualsWithDelta(-1.0, $line[0]['y'], 0.001);
        $this->assertEqualsWithDelta(11.5, $line[1]['x'], 0.001);
        $this->assertEqualsWithDelta(13.0, $line[2]['y'], 0.001);
        $this->assertGreaterThan(100, Geometry::area($line));
    }
}
