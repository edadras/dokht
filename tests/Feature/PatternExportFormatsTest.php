<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Workshop;
use App\Services\Export\AamaDxfExporter;
use App\Services\Export\PatternPngExporter;
use App\Services\Pattern\DxfExporter;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * آزمون قالب‌های تازه خروجی الگو از راه مسیر patterns.export.
 *
 * قالب‌های قدیمی (svg، dxf، json) نباید ذره‌ای تغییر کرده باشند، پس بدنه‌شان با
 * خروجی مستقیم همان سرویس مقایسه می‌شود.
 */
class PatternExportFormatsTest extends TestCase
{
    use RefreshDatabase;

    protected function pattern(string $generator = 'bodice_block'): Pattern
    {
        $template = PatternTemplate::factory()->generator($generator)->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'الگوی خروجی',
            'base_size' => '40',
        ], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);
    }

    /** الگوی سبک با دو قطعه مستطیلی؛ برای آزمون‌های تصویری که باید سریع باشند. */
    protected function simplePattern(): Pattern
    {
        $this->workshop();

        return Pattern::factory()
            ->withSimplePieces()
            ->create(['workshop_id' => $this->workshop()->id])
            ->load('pieces');
    }

    /** شمار نقطه‌های غیرسفید یک تصویر (نمونه‌برداری شبکه‌ای). */
    protected function inkPixels(string $png): int
    {
        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image, 'داده PNG خوانده نشد.');

        $width = imagesx($image);
        $height = imagesy($image);
        $ink = 0;

        for ($x = 0; $x < $width; $x += 3) {
            for ($y = 0; $y < $height; $y += 3) {
                $colour = imagecolorat($image, $x, $y);
                $rgb = imagecolorsforindex($image, $colour);

                if ($rgb['red'] < 240 || $rgb['green'] < 240 || $rgb['blue'] < 240) {
                    $ink++;
                }
            }
        }

        imagedestroy($image);

        return $ink;
    }

    // -------------------------------------------------------------------- PDF

    public function test_pdf_export_returns_a_real_multi_page_pdf(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.export', [$pattern, 'pdf']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', $response->headers->get('content-disposition'));

        $body = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertStringEndsWith("%%EOF\n", $body);
        $this->assertStringContainsString("\nxref\n", $body);
        $this->assertStringContainsString('/Type /Catalog', $body);
        $this->assertStringContainsString('startxref', $body);

        // یک برگ نمای کلی به‌علاوه دست‌کم یک کاشی برای هر قطعه
        $pages = substr_count($body, '/Type /Page /Parent 2 0 R');
        $this->assertGreaterThan($pattern->pieces->count(), $pages);
        $this->assertStringContainsString('/Count '.$pages, $body);

        // A4 دقیق و قلم فارسی جاسازی‌شده
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $body);
        $this->assertStringContainsString('/Encoding /Identity-H', $body);
        $this->assertStringContainsString('/FontFile2', $body);
    }

    public function test_pdf_export_can_drop_the_cutting_line(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $full = $this->get(route('patterns.export', [$pattern, 'pdf']))->getContent();
        $plain = $this->get(route('patterns.export', [$pattern, 'pdf', 'no_seam' => 1]))->getContent();

        $this->assertStringStartsWith('%PDF-', $plain);
        $this->assertNotSame($full, $plain);
    }

    // -------------------------------------------------------------------- PNG

    public function test_png_export_returns_an_image_of_the_expected_size(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $response = $this->get(route('patterns.export', [$pattern, 'png', 'dpi' => 72]));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('72dpi.png', $response->headers->get('content-disposition'));

        $body = $response->getContent();

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $body);

        $info = getimagesizefromstring($body);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);

        [$width, $height] = app(PatternPngExporter::class)->dimensions($pattern, ['dpi' => 72]);
        $this->assertSame($width, $info[0]);
        $this->assertSame($height, $info[1]);

        // ۴۸ سانتی‌متر پهنای قطعه در ۷۲ نقطه بر اینچ یعنی تصویر باید پهن باشد
        $this->assertGreaterThan(2000, $info[0]);
        $this->assertGreaterThan(1000, $info[1]);

        $this->assertGreaterThan(500, $this->inkPixels($body), 'تصویر خروجی خالی است.');
    }

    public function test_png_dpi_is_clamped_to_the_allowed_range(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $this->assertSame(72, PatternPngExporter::clampDpi(10));
        $this->assertSame(600, PatternPngExporter::clampDpi(4000));
        $this->assertSame(150, PatternPngExporter::clampDpi(null));
        $this->assertSame(150, PatternPngExporter::clampDpi('abc'));

        $low = $this->get(route('patterns.export', [$pattern, 'png', 'dpi' => 10]));
        $low->assertOk();

        $this->assertStringContainsString('72dpi.png', $low->headers->get('content-disposition'));

        $info = getimagesizefromstring($low->getContent());
        [$width] = app(PatternPngExporter::class)->dimensions($pattern, ['dpi' => 72]);
        $this->assertSame($width, $info[0]);
    }

    public function test_png_with_seam_allowance_is_larger_than_without(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $plain = getimagesizefromstring(
            $this->get(route('patterns.export', [$pattern, 'png', 'dpi' => 72]))->getContent()
        );
        $seam = getimagesizefromstring(
            $this->get(route('patterns.export', [$pattern, 'png', 'dpi' => 72, 'seam' => 1]))->getContent()
        );

        $this->assertGreaterThan($plain[0], $seam[0]);
    }

    public function test_an_oversized_png_is_refused_with_a_persian_explanation(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $response = $this->get(route('patterns.export', [$pattern, 'png', 'dpi' => 600]));

        $response->assertStatus(422);
        $this->assertStringContainsString('text/plain', $response->headers->get('content-type'));

        $body = $response->getContent();

        $this->assertStringContainsString('بیش از اندازه بزرگ است', $body);
        $this->assertStringContainsString('مگاپیکسل', $body);
        $this->assertStringContainsString('نقطه بر اینچ', $body);
    }

    // -------------------------------------------------------------- AAMA/ASTM

    public function test_aama_and_astm_exports_carry_the_industrial_layers(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        foreach (['aama', 'astm'] as $format) {
            $response = $this->get(route('patterns.export', [$pattern, $format]));

            $response->assertOk();
            $this->assertStringContainsString('application/dxf', $response->headers->get('content-type'));
            $this->assertStringContainsString('-'.$format.'.dxf', $response->headers->get('content-disposition'));

            $body = $response->getContent();

            foreach (['HEADER', 'TABLES', 'BLOCKS', 'ENTITIES'] as $section) {
                $this->assertStringContainsString("\n2\n{$section}\n", $body);
            }

            $this->assertStringContainsString('$INSUNITS', $body);
            $this->assertStringEndsWith("EOF\n", $body);
            $this->assertSame($pattern->pieces->count(), substr_count($body, "\nBLOCK\n"));
            $this->assertSame($pattern->pieces->count(), substr_count($body, "\nINSERT\n"));

            // لایه‌های مستند: ۱ خط برش، ۷ راستای پارچه، ۱۴ خط دوخت
            foreach ([AamaDxfExporter::LAYER_BOUNDARY, AamaDxfExporter::LAYER_GRAIN, AamaDxfExporter::LAYER_SEW_LINE] as $layer) {
                $this->assertStringContainsString("\n8\n{$layer}\n", $body);
            }
        }

        $aama = $this->get(route('patterns.export', [$pattern, 'aama']))->getContent();
        $astm = $this->get(route('patterns.export', [$pattern, 'astm']))->getContent();

        $this->assertStringContainsString('ATTDEF', $astm);
        $this->assertStringNotContainsString('ATTDEF', $aama);
        $this->assertStringContainsString("\n8\n".AamaDxfExporter::ASTM_LAYERS['PIECE_NAME']."\n", $astm);
    }

    public function test_graded_sizes_can_be_requested_on_the_industrial_export(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $body = $this->get(route('patterns.export', [$pattern, 'aama', 'sizes' => '38,42']))->getContent();

        $this->assertSame($pattern->pieces->count() * 3, substr_count($body, "\nBLOCK\n"));
        $this->assertStringContainsString("\nBODICE-FRONT-38\n", $body);
        $this->assertStringContainsString("\nBODICE-FRONT-42\n", $body);
    }

    // -------------------------------------------------- قالب‌های قدیمی نخورده‌اند

    public function test_svg_and_dxf_exports_are_unchanged(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $svg = $this->get(route('patterns.export', [$pattern, 'svg']));
        $svg->assertOk();
        $this->assertStringContainsString('image/svg+xml', $svg->headers->get('content-type'));
        $this->assertSame(
            app(SvgRenderer::class)->renderPattern($pattern, ['seam_allowance' => true, 'labels' => true, 'scale' => 4]),
            $svg->getContent(),
        );

        $dxf = $this->get(route('patterns.export', [$pattern, 'dxf']));
        $dxf->assertOk();
        $this->assertStringContainsString('application/dxf', $dxf->headers->get('content-type'));
        $this->assertSame(
            app(DxfExporter::class)->export($pattern, ['seam_allowance' => true]),
            $dxf->getContent(),
        );
    }

    public function test_json_export_is_unchanged(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.export', [$pattern, 'json']));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame('dokht-pattern', $payload['format']);
        $this->assertSame(1, $payload['format_version']);
        $this->assertCount($pattern->pieces->count(), $payload['pieces']);
        $this->assertSame('cm', $payload['geometry']['unit']);
    }

    // ------------------------------------------------------------- دسترسی

    public function test_new_formats_respect_the_workshop_boundary(): void
    {
        $other = Workshop::factory()->create();
        $foreign = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $other->id]);

        $this->actingAsWorkshopUser();

        foreach (['pdf', 'png', 'aama', 'astm'] as $format) {
            $this->get(route('patterns.export', [$foreign, $format]))->assertNotFound();
        }
    }

    public function test_unknown_formats_are_still_rejected_by_the_route(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->simplePattern();

        $this->get('/patterns/'.$pattern->id.'/export/tiff')->assertNotFound();
        $this->get('/patterns/'.$pattern->id.'/export/ai')->assertNotFound();
    }
}
