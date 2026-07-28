<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Export\AamaDxfExporter;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * آزمون خروجی DXF صنعتی در دو گویش AAMA و ASTM.
 *
 * نقشه لایه‌ها در docblock کلاس AamaDxfExporter آمده است؛ اینجا همان نقشه
 * سنجیده می‌شود: خط برش روی لایه ۱، خط دوخت روی ۱۴، راستای پارچه روی ۷،
 * علامت جفت‌شدن روی ۴، سوراخ نشانه روی ۱۲ و یادداشت روی ۱۳ (یا لایه‌های
 * ۸۰ تا ۸۵ در گویش ASTM).
 */
class AamaDxfExporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * خواندن فایل DXF به شکل زوج‌های [کد گروه، مقدار].
     *
     * @return array<int, array{0: int, 1: string}>
     */
    protected function pairs(string $dxf): array
    {
        $lines = preg_split('/\r\n|\n|\r/', rtrim($dxf, "\n")) ?: [];
        $pairs = [];

        for ($i = 0; $i + 1 < count($lines); $i += 2) {
            $pairs[] = [(int) $lines[$i], $lines[$i + 1]];
        }

        return $pairs;
    }

    /**
     * نهادها با لایه‌شان: [نوع، لایه].
     *
     * @return array<int, array{type: string, layer: string, section: string}>
     */
    protected function entities(string $dxf): array
    {
        $entities = [];
        $section = '';
        $current = null;

        foreach ($this->pairs($dxf) as [$code, $value]) {
            if ($code === 0) {
                if ($current !== null) {
                    $entities[] = $current;
                    $current = null;
                }

                if ($value === 'SECTION') {
                    $section = 'pending';

                    continue;
                }

                if ($value === 'ENDSEC') {
                    $section = '';

                    continue;
                }

                $current = ['type' => $value, 'layer' => '', 'section' => $section];

                continue;
            }

            if ($code === 2 && $section === 'pending') {
                $section = $value;

                continue;
            }

            if ($code === 8 && $current !== null && $current['layer'] === '') {
                $current['layer'] = $value;
            }
        }

        if ($current !== null) {
            $entities[] = $current;
        }

        return $entities;
    }

    /** @return array<int, array{type: string, layer: string, section: string}> */
    protected function ofType(string $dxf, string $type, ?string $section = null): array
    {
        return array_values(array_filter(
            $this->entities($dxf),
            fn (array $entity) => $entity['type'] === $type
                && ($section === null || $entity['section'] === $section),
        ));
    }

    protected function pattern(string $generator = 'bodice_block'): Pattern
    {
        $template = PatternTemplate::factory()->generator($generator)->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'بالاتنه صنعتی',
            'base_size' => '40',
        ], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);
    }

    // ------------------------------------------------------------ ساختار فایل

    public function test_file_has_every_required_section(): void
    {
        $this->actingAsWorkshopUser();
        $dxf = app(AamaDxfExporter::class)->aama($this->pattern());

        foreach (['HEADER', 'TABLES', 'BLOCKS', 'ENTITIES'] as $section) {
            $this->assertStringContainsString("\n2\n{$section}\n", $dxf, "بخش {$section} در فایل نیست.");
        }

        $this->assertStringContainsString('$ACADVER', $dxf);
        $this->assertStringContainsString("\nAC1009\n", $dxf);
        $this->assertStringContainsString('$INSUNITS', $dxf);
        $this->assertStringContainsString('$EXTMIN', $dxf);
        $this->assertStringContainsString('$EXTMAX', $dxf);
        $this->assertStringEndsWith("EOF\n", $dxf);

        // ساختار «کد گروه بعد مقدار» یعنی شمار خط‌ها زوج است
        $lines = preg_split('/\r\n|\n|\r/', trim($dxf)) ?: [];
        $this->assertSame(0, count($lines) % 2);

        $this->assertSame(4, $this->headerValue($dxf, '$INSUNITS'), 'واحد فایل باید میلی‌متر باشد.');
    }

    public function test_header_comment_names_the_software_the_pattern_and_the_convention(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $aama = app(AamaDxfExporter::class)->aama($pattern);
        $astm = app(AamaDxfExporter::class)->astm($pattern);

        // یادداشت‌های ۹۹۹ پیش از نخستین SECTION می‌آیند
        $this->assertStringStartsWith("999\n", $aama);
        $this->assertStringContainsString('Dokht pattern studio', $aama);
        $this->assertStringContainsString('id '.$pattern->id, $aama);

        // نام فارسی با گریز یونیکد استاندارد DXF می‌آید تا فایل اَسکی محض بماند
        $this->assertStringContainsString('\U+0628\U+0627\U+0644\U+0627\U+062A\U+0646\U+0647', $aama);
        $this->assertSame(0, preg_match('/[\x80-\xFF]/', $aama), 'فایل DXF باید اَسکی محض باشد.');
        $this->assertStringContainsString('Units: millimetre', $aama);
        $this->assertStringContainsString('AAMA', $aama);
        $this->assertStringContainsString('ASTM D6673', $astm);
    }

    public function test_layer_table_declares_every_documented_layer(): void
    {
        $this->actingAsWorkshopUser();
        $exporter = app(AamaDxfExporter::class);
        $pattern = $this->pattern();

        // نام لایه در جدول LAYER با کد گروه ۲ می‌آید
        $names = $this->layerNames($exporter->aama($pattern));

        foreach (AamaDxfExporter::GEOMETRY_LAYERS as $layer) {
            $this->assertContains($layer, $names, "لایه {$layer} در جدول LAYER نیست.");
        }

        $this->assertContains('0', $names);
        $this->assertStringContainsString('CONTINUOUS', $exporter->aama($pattern));

        // لایه‌های شناسنامه فقط در گویش ASTM
        $astmNames = $this->layerNames($exporter->astm($pattern));

        foreach (AamaDxfExporter::ASTM_LAYERS as $layer) {
            $this->assertContains($layer, $astmNames, "لایه ASTM شماره {$layer} نیست.");
            $this->assertNotContains($layer, $names, "لایه ASTM شماره {$layer} نباید در گویش AAMA باشد.");
        }
    }

    // ---------------------------------------------------------- بلوک هر قطعه

    public function test_one_block_and_one_insert_per_piece(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern('shirt_classic');
        $count = $pattern->pieces->count();

        foreach (['aama', 'astm'] as $flavour) {
            $dxf = app(AamaDxfExporter::class)->{$flavour}($pattern);

            $this->assertCount($count, $this->ofType($dxf, 'BLOCK', 'BLOCKS'), "شمار BLOCK در {$flavour} درست نیست.");
            $this->assertCount($count, $this->ofType($dxf, 'ENDBLK', 'BLOCKS'));
            $this->assertCount($count, $this->ofType($dxf, 'INSERT', 'ENTITIES'), "شمار INSERT در {$flavour} درست نیست.");

            // هر INSERT به یک بلوک موجود اشاره می‌کند
            foreach ($this->blockNames($dxf) as $name) {
                $this->assertStringContainsString("\n2\n{$name}\n", $dxf);
            }
        }
    }

    public function test_graded_sizes_become_separate_blocks(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $count = $pattern->pieces->count();

        $dxf = app(AamaDxfExporter::class)->astm($pattern, ['sizes' => ['38', '42']]);

        // سایز پایه ۴۰ به‌علاوه دو سایز خواسته‌شده
        $this->assertCount($count * 3, $this->ofType($dxf, 'BLOCK', 'BLOCKS'));
        $this->assertCount($count * 3, $this->ofType($dxf, 'INSERT', 'ENTITIES'));

        $names = $this->blockNames($dxf);
        $this->assertContains('BODICE-FRONT-40', $names);
        $this->assertContains('BODICE-FRONT-38', $names);
        $this->assertContains('BODICE-FRONT-42', $names);

        $this->assertSame(count($names), count(array_unique($names)));
    }

    // ----------------------------------------------------------- معنای لایه‌ها

    public function test_entities_sit_on_the_documented_layers(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $dxf = app(AamaDxfExporter::class)->aama($pattern);
        $count = $pattern->pieces->count();

        $polylines = $this->ofType($dxf, 'POLYLINE', 'BLOCKS');
        $layers = array_count_values(array_column($polylines, 'layer'));

        // خط برش روی لایه ۱ و خط دوخت روی لایه ۱۴؛ یکی از هرکدام برای هر قطعه
        $this->assertSame($count, $layers[AamaDxfExporter::LAYER_BOUNDARY] ?? 0);
        $this->assertSame($count, $layers[AamaDxfExporter::LAYER_SEW_LINE] ?? 0);

        $lineLayers = array_column($this->ofType($dxf, 'LINE', 'BLOCKS'), 'layer');
        $this->assertContains(AamaDxfExporter::LAYER_GRAIN, $lineLayers);          // راستای پارچه
        $this->assertContains(AamaDxfExporter::LAYER_INTERNAL_LINE, $lineLayers);  // ساسون و خط نشانه

        $pointLayers = array_column($this->ofType($dxf, 'POINT', 'BLOCKS'), 'layer');
        $this->assertContains(AamaDxfExporter::LAYER_TURN_POINTS, $pointLayers);
        $this->assertContains(AamaDxfExporter::LAYER_CURVE_POINTS, $pointLayers);
        $this->assertContains(AamaDxfExporter::LAYER_NOTCH, $pointLayers);
        $this->assertContains(AamaDxfExporter::LAYER_GRADE_REFERENCE, $pointLayers);

        // در گویش AAMA همه یادداشت‌ها روی لایه ۱۳ می‌نشینند
        foreach ($this->ofType($dxf, 'TEXT', 'BLOCKS') as $text) {
            $this->assertSame(AamaDxfExporter::LAYER_ANNOTATION, $text['layer']);
        }
    }

    public function test_astm_writes_piece_metadata_as_attributes_on_its_own_layers(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();
        $count = $pattern->pieces->count();

        $astm = app(AamaDxfExporter::class)->astm($pattern);
        $aama = app(AamaDxfExporter::class)->aama($pattern);

        $fields = count(AamaDxfExporter::ASTM_LAYERS);

        $this->assertCount($count * $fields, $this->ofType($astm, 'ATTDEF', 'BLOCKS'));
        $this->assertCount($count * $fields, $this->ofType($astm, 'ATTRIB', 'ENTITIES'));
        $this->assertCount($count, $this->ofType($astm, 'SEQEND', 'ENTITIES')); // پایان صفت‌های هر INSERT

        // گویش AAMA صفت ندارد
        $this->assertCount(0, $this->ofType($aama, 'ATTDEF'));
        $this->assertCount(0, $this->ofType($aama, 'ATTRIB'));

        foreach (AamaDxfExporter::ASTM_LAYERS as $tag => $layer) {
            $this->assertStringContainsString("\n2\n{$tag}\n", $astm, "صفت {$tag} در فایل ASTM نیست.");

            $onLayer = array_filter(
                $this->ofType($astm, 'ATTRIB', 'ENTITIES'),
                fn (array $entity) => $entity['layer'] === $layer,
            );

            $this->assertCount($count, $onLayer, "صفت {$tag} روی لایه {$layer} ننشسته است.");
        }

        $this->assertStringContainsString("\n1\nQUANTITY: ", $astm);
        $this->assertMatchesRegularExpression('/\n1\npattern \d+ rev \d+ \/ [\d.]+ x [\d.]+ cm/', $astm);
    }

    public function test_astm_adds_a_slit_line_to_every_notch(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $notchLines = fn (string $dxf) => count(array_filter(
            $this->ofType($dxf, 'LINE', 'BLOCKS'),
            fn (array $entity) => $entity['layer'] === AamaDxfExporter::LAYER_NOTCH,
        ));

        $notchPoints = fn (string $dxf) => count(array_filter(
            $this->ofType($dxf, 'POINT', 'BLOCKS'),
            fn (array $entity) => $entity['layer'] === AamaDxfExporter::LAYER_NOTCH,
        ));

        $aama = app(AamaDxfExporter::class)->aama($pattern);
        $astm = app(AamaDxfExporter::class)->astm($pattern);

        $this->assertGreaterThan(0, $notchPoints($aama));
        $this->assertSame($notchPoints($aama), $notchPoints($astm));
        $this->assertSame(0, $notchLines($aama));
        $this->assertSame($notchPoints($astm), $notchLines($astm));
    }

    // ------------------------------------------------------------ مختصات‌ها

    public function test_coordinates_are_millimetres_with_the_y_axis_flipped(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'seam_allowances' => ['default' => 0.0],
        ]);
        $pattern->pieces()->create([
            'code' => 'block',
            'name' => 'قطعه',
            'cut_quantity' => 2,
            'edge_allowances' => ['default' => 0.0],
            'outline' => [
                ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 10, 'y' => 20], ['x' => 0, 'y' => 20],
            ],
            'grainline' => ['from' => ['x' => 5, 'y' => 2], 'to' => ['x' => 5, 'y' => 18]],
        ]);

        $dxf = app(AamaDxfExporter::class)->aama($pattern->load('pieces'));

        // ۱۰ سانتی‌متر ⇒ ۱۰۰ میلی‌متر و ۲۰ سانتی‌متر ⇒ ‎-۲۰۰ میلی‌متر
        $this->assertStringContainsString("10\n100\n", $dxf);
        $this->assertStringContainsString("20\n-200\n", $dxf);
        $this->assertStringContainsString("10\n50\n", $dxf);   // راستای پارچه در x = ۵ سانتی‌متر
        $this->assertStringContainsString("20\n-20\n", $dxf);  // و y = ۲ سانتی‌متر رو به پایین

        // بلوک در مبدأ تعریف و با INSERT در فضای مدل چیده می‌شود
        $this->assertCount(1, $this->ofType($dxf, 'BLOCK', 'BLOCKS'));
        $this->assertCount(1, $this->ofType($dxf, 'INSERT', 'ENTITIES'));
        $this->assertSame(['BLOCK'], $this->blockNames($dxf));
    }

    public function test_pieces_without_geometry_do_not_break_the_file(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = Pattern::factory()->create(['workshop_id' => $this->workshop()->id]);
        $pattern->pieces()->create(['code' => 'empty', 'name' => 'خالی', 'outline' => []]);

        $dxf = app(AamaDxfExporter::class)->astm($pattern->load('pieces'));

        $this->assertStringEndsWith("EOF\n", $dxf);
        $lines = preg_split('/\r\n|\n|\r/', trim($dxf)) ?: [];
        $this->assertSame(0, count($lines) % 2);
        $this->assertCount(1, $this->ofType($dxf, 'BLOCK', 'BLOCKS'));
    }

    // -------------------------------------------------------------- کمکی‌ها

    /** مقدار یک متغیر عددی سرآیند. */
    protected function headerValue(string $dxf, string $name): ?int
    {
        $pairs = $this->pairs($dxf);

        foreach ($pairs as $index => [$code, $value]) {
            if ($code === 9 && $value === $name) {
                return (int) ($pairs[$index + 1][1] ?? 0);
            }
        }

        return null;
    }

    /**
     * نام لایه‌های اعلام‌شده در جدول LAYER.
     *
     * @return array<int, string>
     */
    protected function layerNames(string $dxf): array
    {
        $names = [];
        $inLayer = false;

        foreach ($this->pairs($dxf) as [$code, $value]) {
            if ($code === 0) {
                $inLayer = $value === 'LAYER';

                continue;
            }

            if ($inLayer && $code === 2) {
                $names[] = $value;
                $inLayer = false;
            }
        }

        return $names;
    }

    /**
     * نام بلوک‌ها.
     *
     * @return array<int, string>
     */
    protected function blockNames(string $dxf): array
    {
        $names = [];
        $inBlock = false;

        foreach ($this->pairs($dxf) as [$code, $value]) {
            if ($code === 0) {
                $inBlock = $value === 'BLOCK';

                continue;
            }

            if ($inBlock && $code === 2) {
                $names[] = $value;
                $inBlock = false;
            }
        }

        return $names;
    }
}
