<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Pattern\DxfExporter;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DxfExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_has_the_required_dxf_sections(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $dxf = app(DxfExporter::class)->export($pattern->load('pieces'));

        foreach (['SECTION', 'HEADER', '$ACADVER', 'AC1009', 'TABLES', 'LAYER', 'ENTITIES', 'ENDSEC', 'EOF'] as $marker) {
            $this->assertStringContainsString($marker, $dxf, "بخش {$marker} در فایل DXF نیست.");
        }

        // ساختار «کد گروه بعد مقدار» یعنی تعداد خطوط زوج است
        $lines = preg_split('/\R/', trim($dxf));
        $this->assertSame(0, count($lines) % 2);
        $this->assertSame('0', $lines[0]);
        $this->assertSame('SECTION', $lines[1]);
        $this->assertSame('EOF', $lines[count($lines) - 1]);
    }

    public function test_one_closed_polyline_per_piece(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $dxf = app(DxfExporter::class)->export($pattern->load('pieces'), ['seam_allowance' => false]);

        $this->assertSame(2, substr_count($dxf, "\nPOLYLINE\n"));
        $this->assertSame(2, substr_count($dxf, "\nSEQEND\n"));
        $this->assertStringContainsString("\nFRONT\n", $dxf);
        $this->assertStringContainsString("\nBACK\n", $dxf);
        $this->assertStringContainsString("\nVERTEX\n", $dxf);
    }

    public function test_seam_allowance_adds_a_second_layer_per_piece(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $dxf = app(DxfExporter::class)->export($pattern->load('pieces'), ['seam_allowance' => true]);

        $this->assertSame(4, substr_count($dxf, "\nPOLYLINE\n"));
        $this->assertStringContainsString("\nFRONT_SA\n", $dxf);
    }

    public function test_coordinates_are_in_millimetres_with_y_flipped(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->create(['workshop_id' => $this->workshop()->id]);
        $pattern->pieces()->create([
            'code' => 'block',
            'name' => 'قطعه',
            'outline' => [
                ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 0, 'y' => 20],
            ],
        ]);

        $dxf = app(DxfExporter::class)->export($pattern->load('pieces'), [
            'seam_allowance' => false,
            'grainline' => false,
            'labels' => false,
        ]);

        // ۱۰ سانتی‌متر ⇒ ۱۰۰ میلی‌متر و ۲۰ سانتی‌متر ⇒ ‎-۲۰۰ میلی‌متر (محور y در CAD به بالا است)
        $this->assertStringContainsString("10\n100\n", $dxf);
        $this->assertStringContainsString("20\n-200\n", $dxf);
        $this->assertStringContainsString('$INSUNITS', $dxf);
    }

    public function test_generated_pattern_with_curves_exports_flattened_polylines(): void
    {
        $this->actingAsWorkshopUser();

        $template = PatternTemplate::factory()->generator('bodice_block')->create();
        $pattern = app(PatternBuilder::class)->createPattern($template, ['name' => 'بالاتنه'], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6],
        ]);

        $dxf = app(DxfExporter::class)->export($pattern);

        // دو قطعه × (خط دوخت + خط برش)
        $this->assertSame(4, substr_count($dxf, "\nPOLYLINE\n"));
        $this->assertGreaterThan(20, substr_count($dxf, "\nVERTEX\n")); // منحنی‌ها به نقطه تبدیل شده‌اند
        $this->assertStringContainsString('BODICE-FRONT', $dxf);
        $this->assertStringContainsString('_DART', $dxf);
        $this->assertStringContainsString('_GRAIN', $dxf);
    }
}
