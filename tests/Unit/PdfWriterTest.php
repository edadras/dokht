<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Export\ArabicShaper;
use App\Services\Export\PatternPdfExporter;
use App\Services\Export\PdfWriter;
use App\Services\Export\TrueTypeFont;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * آزمون نویسنده PDF و خروجی PDF الگو.
 *
 * چیزهایی که اینجا سنجیده می‌شود: ساختار فایل (سرآیند، xref، trailer، %%EOF)،
 * شمار صفحه‌ها، شمار دقیق دستورهای مسیر در جریان محتوا، درستی مقیاس یک‌به‌یک
 * (۱۰ سانتی‌متر = ۲۸۳٫۴۶ واحد کاربر) و جاسازی قلم برای متن فارسی.
 */
class PdfWriterTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ ابزار آزمون

    /**
     * شیءهای PDF: شماره ⇒ بدنه.
     *
     * @return array<int, string>
     */
    protected function objects(string $pdf): array
    {
        preg_match_all('/(\d+) 0 obj\n(.*?)\nendobj\n/s', $pdf, $matches, PREG_SET_ORDER);

        $objects = [];

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * جریان محتوای هر صفحه، به ترتیب صفحه‌ها و در صورت نیاز از حالت فشرده درآمده.
     *
     * @return array<int, string>
     */
    protected function streams(string $pdf): array
    {
        $objects = $this->objects($pdf);
        $streams = [];

        preg_match_all('/\/Contents (\d+) 0 R/', $pdf, $matches);

        foreach ($matches[1] as $number) {
            $body = $objects[(int) $number] ?? '';

            if (! str_contains($body, "\nstream\n")) {
                continue;
            }

            [$dictionary, $rest] = explode("\nstream\n", $body, 2);
            $data = substr($rest, 0, (int) strrpos($rest, "\nendstream"));

            if (str_contains($dictionary, '/FlateDecode')) {
                $inflated = @gzuncompress($data);

                if ($inflated === false) {
                    continue;
                }

                $data = $inflated;
            }

            $streams[] = $data;
        }

        return $streams;
    }

    /**
     * شمار دستورها در یک جریان محتوا (آخرین واژه هر خط، نام دستور است).
     *
     * @return array<string, int>
     */
    protected function operators(string $stream): array
    {
        $counts = [];

        foreach (preg_split('/\R/', trim($stream)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $operator = end($parts);

            if ($operator === false) {
                continue;
            }

            $counts[$operator] = ($counts[$operator] ?? 0) + 1;
        }

        return $counts;
    }

    protected function pattern(string $generator = 'bodice_block'): Pattern
    {
        $template = PatternTemplate::factory()->generator($generator)->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'بالاتنه چاپی',
            'base_size' => '40',
        ], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);
    }

    // ------------------------------------------------------- ساختار فایل PDF

    public function test_document_has_a_header_xref_and_trailer(): void
    {
        $pdf = (new PdfWriter)->addPage()->line(10, 10, 100, 100)->render();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString("\nxref\n", $pdf);
        $this->assertStringContainsString('/Type /Catalog', $pdf);
        $this->assertStringContainsString('/Type /Pages', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $pdf);
        $this->assertMatchesRegularExpression('/trailer\n<< \/Size \d+ \/Root 1 0 R/', $pdf);
    }

    public function test_the_cross_reference_table_points_at_every_object(): void
    {
        $pdf = (new PdfWriter)->addPage()->rect(0, 0, 10, 10)->stroke()->addPage()->render();

        $this->assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $start));

        $xrefOffset = (int) $start[1];
        $this->assertSame('xref', substr($pdf, $xrefOffset, 4));

        $this->assertSame(1, preg_match('/xref\n0 (\d+)\n/', $pdf, $size));
        $count = (int) $size[1];

        // ورودی آزاد صفر به‌علاوه یک ورودی برای هر شیء
        $this->assertSame($count, count($this->objects($pdf)) + 1);

        preg_match_all('/^(\d{10}) (\d{5}) ([nf]) $/m', substr($pdf, $xrefOffset), $rows);
        $this->assertCount($count, $rows[0]);
        $this->assertSame('f', $rows[3][0]); // ورودی صفر همیشه آزاد است

        // هر آفست باید دقیقاً روی «شماره 0 obj» بنشیند
        foreach ($rows[1] as $index => $offset) {
            if ($rows[3][$index] !== 'n') {
                continue;
            }

            $this->assertSame($index.' 0 obj', substr($pdf, (int) $offset, strlen($index) + 6));
        }
    }

    public function test_page_count_matches_the_pages_added(): void
    {
        $writer = new PdfWriter;

        for ($i = 0; $i < 5; $i++) {
            $writer->addPage()->line(0, 0, 10, 10);
        }

        $pdf = $writer->render();

        $this->assertSame(5, $writer->pageCount());
        $this->assertSame(5, substr_count($pdf, '/Type /Page /Parent 2 0 R'));
        $this->assertStringContainsString('/Count 5', $pdf);
        $this->assertSame(5, count($this->streams($pdf)));
    }

    // ------------------------------------------------ دستورهای جریان محتوا

    public function test_content_stream_holds_exactly_the_path_operators_we_asked_for(): void
    {
        $writer = new PdfWriter;

        $writer->addPage();
        $writer->save();
        $writer->setLineWidth(1.5)->setDash([3, 2])->setStrokeColor(0, 0, 0);
        $writer->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10)->closePath()->stroke();
        $writer->rect(1, 1, 5, 5)->fill();
        $writer->circle(20, 20, 5, 'S');
        $writer->restore();

        $writer->addPage();
        $writer->line(0, 0, 100, 100);
        $writer->polygon([[0, 0], [5, 0], [5, 5]], true, 'B');

        $streams = array_values($this->streams($writer->render()));
        $this->assertCount(2, $streams);

        $first = $this->operators($streams[0]);
        // یک m برای مسیر سه‌خطی + یک m برای دایره
        $this->assertSame(2, $first['m']);
        $this->assertSame(2, $first['l']);
        $this->assertSame(4, $first['c']); // دایره = چهار کمان بزیه
        $this->assertSame(1, $first['h']);
        $this->assertSame(2, $first['S']); // مسیر سه‌خطی + دایره
        $this->assertSame(1, $first['re']);
        $this->assertSame(1, $first['f']);
        $this->assertSame(1, $first['w']);
        $this->assertSame(1, $first['d']);
        $this->assertSame(1, $first['q']);
        $this->assertSame(1, $first['Q']);
        $this->assertArrayNotHasKey('B', $first);

        $second = $this->operators($streams[1]);
        $this->assertSame(2, $second['m']); // خط + چندضلعی
        $this->assertSame(3, $second['l']);
        $this->assertSame(1, $second['S']);
        $this->assertSame(1, $second['h']);
        $this->assertSame(1, $second['B']);
    }

    public function test_clipping_and_quadratic_curves_are_written_as_pdf_operators(): void
    {
        $writer = new PdfWriter;
        $writer->addPage();
        $writer->rect(0, 0, 100, 100)->clip();
        $writer->moveTo(0, 0)->quadraticTo(0, 0, 10, 20, 20, 0)->stroke();

        $stream = array_values($this->streams($writer->render()))[0];

        $this->assertStringContainsString('0 0 100 100 re', $stream);
        $this->assertStringContainsString('W n', $stream);
        // نقطه‌های کنترل درجه‌سه: دو سومِ راه از هر سر به نقطه کنترل درجه‌دو
        $this->assertStringContainsString('6.67 13.33 13.33 13.33 20 0 c', $stream);
    }

    // -------------------------------------------------------- مقیاس یک‌به‌یک

    public function test_ten_centimetres_is_exactly_283_46_pdf_units(): void
    {
        $this->assertSame(283.46, round(PdfWriter::cm(10), 2));
        $this->assertSame(28.35, round(PdfWriter::cm(1), 2));
        $this->assertSame(595.28, round(PdfWriter::cm(21.0), 2));
        $this->assertSame(841.89, round(PdfWriter::cm(29.7), 2));

        $writer = new PdfWriter;
        $writer->addPage()->line(0, 100, PdfWriter::cm(10), 100);

        $stream = array_values($this->streams($writer->render()))[0];

        $this->assertStringContainsString("0 100 m\n283.46 100 l", $stream);
    }

    public function test_every_pattern_page_prints_a_ten_centimetre_ruler_at_true_size(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $pdf = app(PatternPdfExporter::class)->export($pattern);
        $streams = $this->streams($pdf);

        $this->assertNotEmpty($streams);

        // خط‌کش از حاشیه یک سانتی‌متری (۲۸٫۳۵) تا ۱۱ سانتی‌متر (۳۱۱٫۸۱) می‌رود
        foreach ($streams as $stream) {
            $this->assertMatchesRegularExpression('/28\.35 \d+(\.\d+)? m\n311\.81 /', $stream,
                'خط‌کش ۱۰ سانتی‌متری روی همه صفحه‌ها نیست.');
        }

        $this->assertSame(283.46, round(311.81 - 28.35, 2));
    }

    // ------------------------------------------------------------ جاسازی قلم

    public function test_the_persian_font_is_embedded_as_an_identity_h_cid_font(): void
    {
        $font = TrueTypeFont::persian();
        $this->assertNotNull($font, 'قلم فارسی در resources/fonts نیست.');

        $writer = (new PdfWriter)->useFont($font);
        $writer->addPage()->text('بالاتنه جلو', 50, 700, 12);
        $pdf = $writer->render();

        $this->assertStringContainsString('/Subtype /Type0', $pdf);
        $this->assertStringContainsString('/Encoding /Identity-H', $pdf);
        $this->assertStringContainsString('/Subtype /CIDFontType2', $pdf);
        $this->assertStringContainsString('/CIDToGIDMap /Identity', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
        $this->assertStringContainsString('/ToUnicode', $pdf);
        $this->assertMatchesRegularExpression('/\/Length1 \d{4,}/', $pdf);

        $stream = array_values($this->streams($pdf))[0];
        $this->assertMatchesRegularExpression('/BT \/F1 12 Tf .* <[0-9A-F]{8,}> Tj ET/', $stream);
    }

    public function test_the_font_reader_finds_persian_glyphs_and_widths(): void
    {
        $font = TrueTypeFont::persian();
        $this->assertNotNull($font);

        $this->assertGreaterThan(0, $font->numGlyphs);
        $this->assertGreaterThan(0, $font->unitsPerEm);

        // حرف‌های فارسی و شکل‌های نمایشی‌شان هر دو در قلم هستند
        foreach ([0x0628, 0x06CC, 0x06AF, 0x067E, 0x0686, 0xFEDF, 0xFB95, 0xFEFB] as $codepoint) {
            $this->assertTrue($font->hasGlyph($codepoint), sprintf('گلیف U+%04X در قلم نیست.', $codepoint));
        }

        $glyph = $font->glyph(0x0628);
        $this->assertNotNull($glyph);
        $this->assertGreaterThan(0, $font->advance($glyph));
        $this->assertLessThanOrEqual(2000, $font->advance($glyph));
    }

    // ----------------------------------------------------- شکل‌دهی متن فارسی

    public function test_arabic_letters_take_their_contextual_forms(): void
    {
        $shaper = new ArabicShaper;

        // «بالاتنه»: ب آغازین، ا پایانی، لام‌الف، ت آغازین، ن میانی، ه پایانی
        $this->assertSame(
            [0xFE91, 0xFE8E, 0xFEFB, 0xFE97, 0xFEE8, 0xFEEA],
            $shaper->shape($shaper->codepoints('بالاتنه')),
        );

        // نیم‌فاصله پیوند را می‌شکند: «سانتی‌متر» ⇒ ی پایانی و م آغازین
        $shaped = $shaper->shape($shaper->codepoints('سانتی‌متر'));
        $this->assertSame([0xFEB3, 0xFE8E, 0xFEE7, 0xFE98, 0xFBFD, 0xFEE3, 0xFE98, 0xFEAE], $shaped);

        // حرف تنها
        $this->assertSame([0xFEED], $shaper->shape($shaper->codepoints('و')));
    }

    public function test_text_is_reordered_right_to_left_but_numbers_stay_left_to_right(): void
    {
        $shaper = new ArabicShaper;

        $this->assertSame('ﻮﻠﺟ ﻪﻨﺗﻻﺎﺑ', $shaper->visual('بالاتنه جلو'));

        // عددها وارونه نمی‌شوند
        $visual = $shaper->visual('صفحه ۱۲ از ۳۴');
        $this->assertStringStartsWith('۳۴', $visual);
        $this->assertStringContainsString('۱۲', $visual);

        // تکه لاتین سرجای خودش می‌ماند
        $this->assertStringContainsString('front', $shaper->visual('قطعه front'));

        // جفت‌های آینه‌ای در بازه راست‌به‌چپ عوض می‌شوند
        $this->assertStringStartsWith('(', $shaper->visual('(دوخت)'));
        $this->assertStringEndsWith(')', $shaper->visual('(دوخت)'));

        $this->assertTrue($shaper->hasRtl('نام قطعه'));
        $this->assertFalse($shaper->hasRtl('piece name 12'));
    }

    // ------------------------------------------------------- خروجی PDF الگو

    public function test_pattern_pdf_has_one_page_per_planned_tile_plus_the_overview(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);
        $pattern->load('pieces');

        $exporter = app(PatternPdfExporter::class);
        $plan = $exporter->plan($pattern->pieces->all());

        $this->assertSame('overview', $plan[0]['type']);
        $this->assertGreaterThan(1, count($plan));

        $pdf = $exporter->export($pattern);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertSame(count($plan), substr_count($pdf, '/Type /Page /Parent 2 0 R'));
        $this->assertStringContainsString('/Count '.count($plan), $pdf);
        $this->assertSame(count($plan), count($this->streams($pdf)));
    }

    public function test_tiles_overlap_by_one_centimetre_and_cover_the_whole_piece(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = Pattern::factory()->create(['workshop_id' => $this->workshop()->id]);
        $pattern->pieces()->create([
            'code' => 'block',
            'name' => 'قطعه بزرگ',
            'outline' => [
                ['x' => 0, 'y' => 0], ['x' => 40, 'y' => 0], ['x' => 40, 'y' => 50], ['x' => 0, 'y' => 50],
            ],
        ]);

        $plan = app(PatternPdfExporter::class)->plan($pattern->load('pieces')->pieces->all(), false, false);
        $tiles = array_values(array_filter($plan, fn (array $page) => $page['type'] === 'tile'));

        $this->assertNotEmpty($tiles);

        $stepX = PatternPdfExporter::WINDOW_WIDTH - PatternPdfExporter::OVERLAP;
        $this->assertSame((int) ceil(41.2 / $stepX), $tiles[0]['columns']);

        // گام کاشی‌های همسایه = پنجره منهای یک سانتی‌متر هم‌پوشانی
        $sameRow = array_values(array_filter($tiles, fn (array $tile) => $tile['row'] === 1));
        $this->assertGreaterThan(1, count($sameRow));
        $this->assertEqualsWithDelta($stepX, $sameRow[1]['x'] - $sameRow[0]['x'], 0.001);

        // آخرین کاشی باید لبه دور قطعه را پوشش دهد
        $last = end($sameRow);
        $this->assertGreaterThanOrEqual(40.6, $last['x'] + PatternPdfExporter::WINDOW_WIDTH);
    }

    public function test_pattern_pdf_draws_curves_and_carries_the_pattern_name(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $pdf = app(PatternPdfExporter::class)->export($pattern);
        $streams = $this->streams($pdf);
        $all = implode("\n", $streams);
        $operators = $this->operators($all);

        // خط دوخت با منحنی درجه‌دو کشیده می‌شود ⇒ دستور c در جریان هست
        $this->assertGreaterThan(0, $operators['c'] ?? 0);
        $this->assertGreaterThan(0, $operators['B'] ?? 0);  // پرکردن و کشیدن خط دوخت
        $this->assertGreaterThan(0, $operators['n'] ?? 0);  // بریدن به پنجره کاشی
        $this->assertGreaterThan(0, $operators['ET'] ?? 0); // متن برچسب‌ها

        // نام فارسی الگو در اطلاعات سند به شکل UTF-16BE می‌آید
        $this->assertStringContainsString('/Title <FEFF', $pdf);
    }

    public function test_pdf_can_be_produced_without_the_cutting_line(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $this->workshop()->id]);

        $exporter = app(PatternPdfExporter::class);

        $withSeam = $this->operators(implode("\n", $this->streams($exporter->export($pattern->load('pieces')))));
        $withoutSeam = $this->operators(implode("\n", $this->streams(
            $exporter->export($pattern->load('pieces'), ['seam_allowance' => false])
        )));

        $this->assertGreaterThan($withoutSeam['S'] ?? 0, $withSeam['S'] ?? 0);
    }
}
