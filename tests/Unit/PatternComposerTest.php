<?php

namespace Tests\Unit;

use App\Models\PatternPiece;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\PatternComposer;
use App\Services\Pattern\SvgRenderer;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PatternComposerTest extends TestCase
{
    use RefreshDatabase;

    protected function composer(): PatternComposer
    {
        return app(PatternComposer::class);
    }

    protected function measurements(string $size = '40'): array
    {
        return Measurements::fromSize($size);
    }

    /** @return array<int, array<string, mixed>> */
    protected function group(array $result, string $group): array
    {
        return array_values(array_filter(
            $result['pieces'],
            fn (array $piece) => ($piece['meta']['group'] ?? null) === $group,
        ));
    }

    protected function piece(array $result, string $code): array
    {
        foreach ($result['pieces'] as $piece) {
            if ($piece['code'] === $code) {
                return $piece;
            }
        }

        $this->fail("قطعه «{$code}» در ترکیب نیست.");
    }

    public function test_bodice_sleeve_and_skirt_compose_into_one_coherent_piece_list(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line', 'collar' => 'shirt'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $codes = array_column($result['pieces'], 'code');

        $this->assertSame($codes, array_unique($codes), 'کد قطعه‌ها نباید تکراری باشد.');
        $this->assertSame(['bodice-front', 'bodice-back', 'skirt-front', 'skirt-back', 'sleeve', 'collar'], $codes);

        $this->assertCount(2, $this->group($result, 'bodice'));
        $this->assertCount(2, $this->group($result, 'lower'));
        $this->assertCount(1, $this->group($result, 'sleeve'));
        $this->assertCount(1, $this->group($result, 'collar'));

        $sorts = array_column($result['pieces'], 'sort');
        $sorted = $sorts;
        sort($sorted);
        $this->assertSame($sorted, $sorts);

        foreach ($result['pieces'] as $piece) {
            $this->assertGreaterThanOrEqual(3, count($piece['outline']), $piece['code']);
            $this->assertCount(count($piece['outline']), $piece['meta']['edges'], $piece['code'].': برچسب هر لبه');
            $this->assertNotEmpty($piece['grainline'], $piece['code'].': راستای پارچه');
            $this->assertNotEmpty($piece['edge_allowances'], $piece['code'].': جای دوخت');
            $this->assertSame([0.0, 0.0], [
                round(Geometry::bounds($piece['outline'])[0], 2),
                round(Geometry::bounds($piece['outline'])[1], 2),
            ], $piece['code'].': مبدأ گوشه بالا-چپ');
        }
    }

    public function test_darts_notches_and_markers_survive_the_composition(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_pencil'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $front = $this->piece($result, 'bodice-front');
        $this->assertNotEmpty($front['darts']);
        $this->assertNotEmpty($front['notches']);
        $this->assertNotEmpty($front['markers']);

        foreach ($front['darts'] as $dart) {
            if ($dart['edge'] === null) {
                continue;
            }

            $this->assertArrayHasKey((int) $dart['edge'], $front['meta']['edges'], 'شماره لبه ساسون باید معتبر باشد.');
        }

        $sleeve = $this->piece($result, 'sleeve');
        $this->assertNotEmpty($sleeve['notches']);
        $this->assertNotEmpty($this->piece($result, 'skirt-back')['darts']);
    }

    public function test_the_bodice_is_cropped_at_the_waist_when_a_lower_part_is_attached(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();

        $alone = $composer->compose(['bodice' => 'tshirt'], $measurements);
        $joined = $composer->compose(['bodice' => 'tshirt', 'skirt' => 'skirt_a_line'], $measurements);

        $long = $this->piece($alone, 'tshirt-front');
        $short = $this->piece($joined, 'tshirt-front');

        $this->assertGreaterThan(Geometry::height($short['outline']) + 10, Geometry::height($long['outline']));
        $this->assertContains('hem', $long['meta']['edges'], 'بالاتنه تنها باید لبه پایین داشته باشد.');
        $this->assertContains('waist', $short['meta']['edges'], 'بالاتنه بریده‌شده باید لبه کمر داشته باشد.');
        $this->assertNotContains('hem', $short['meta']['edges']);
        $this->assertTrue($short['meta']['cropped_at_waist']);

        // خط کمر همان جایی است که بلوک اعلام کرده بود
        $waistEdge = $composer->edgeWithTag($short, 'waist');
        $waistY = Geometry::pointOnEdge($short['outline'], $waistEdge, 0.5)['y'];
        $this->assertEqualsWithDelta(Geometry::height($short['outline']), $waistY, 0.6);
    }

    public function test_waist_difference_is_eased_into_gathers_and_the_seams_end_up_equal(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'bodice_block', 'skirt' => 'skirt_a_line'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6, 'lower' => ['waist' => 16]],
        );

        $waist = $result['metrics']['waist'];

        $this->assertSame('gather', $waist['method']);
        $this->assertEqualsWithDelta(12.0, $waist['difference'], 0.2, 'دامن باید ۱۲ سانتی‌متر گشادتر باشد.');
        $this->assertEqualsWithDelta(12.0, $waist['gathered'], 0.2);

        // بعد از چین‌دادن، دو لبه کمر دقیقاً هم‌اندازه دوخته می‌شوند
        $this->assertEqualsWithDelta(
            $composer->waistGirth($this->group($result, 'bodice')),
            $composer->waistGirth($this->group($result, 'lower')),
            0.05,
        );
        $this->assertEqualsWithDelta($waist['bodice_after'], $waist['lower_after'], 0.05);

        $gathers = $this->piece($result, 'skirt-front')['meta']['gathers'];
        $this->assertNotEmpty($gathers);
        $this->assertEqualsWithDelta(3.0, $gathers[0]['amount'], 0.2, 'چین بین چهار ربع کمر پخش می‌شود.');

        $note = collect($result['notes'])->firstWhere('type', 'tip');
        $this->assertStringContainsString('چین', $note['text']);
        $this->assertStringContainsString('سانتی‌متر', $note['text']);
    }

    public function test_waist_difference_is_trued_on_the_side_seams_and_the_seams_end_up_equal(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'tshirt', 'skirt' => 'skirt_pencil'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $waist = $result['metrics']['waist'];

        $this->assertSame('true_side_seams', $waist['method']);
        $this->assertLessThan(0, $waist['difference'], 'تی‌شرت در کمر گشادتر از دامن راسته است.');
        $this->assertGreaterThan(5, $waist['trued']);

        $bodice = $composer->waistGirth($this->group($result, 'bodice'));
        $lower = $composer->waistGirth($this->group($result, 'lower'));

        $this->assertEqualsWithDelta($bodice, $lower, 0.1, 'کمر بالاتنه و دامن باید هم‌اندازه شود.');
        $this->assertEqualsWithDelta($waist['lower'], $lower, 0.1, 'دامن نباید تغییر کند؛ فقط بالاتنه تنگ می‌شود.');

        // هندسه واقعاً عوض شده است: لبه کمر بالاتنه کوتاه‌تر شده
        $front = $this->piece($result, 'tshirt-front');
        $this->assertGreaterThan(0, $front['meta']['trued_waist']);
    }

    public function test_the_sleeve_cap_is_fitted_to_the_composed_armhole(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $sleeve = $result['metrics']['sleeve'];

        $this->assertSame('fitted', $sleeve['status']);
        $this->assertEqualsWithDelta(
            $composer->armholeLength($this->group($result, 'bodice')),
            $sleeve['armhole'],
            0.05,
            'طول حلقه گزارش‌شده باید همان حلقه قطعه‌های نهایی باشد.',
        );
        $this->assertEqualsWithDelta(
            $sleeve['armhole'] + 1.5,
            $composer->capLength($this->piece($result, 'sleeve')),
            PatternComposer::CAP_TOLERANCE,
            'سرآستین باید به اندازه حلقه + آزادی سرآستین باشد.',
        );

        // حلقه آستین برای جادادن سرآستین گودتر شده و این کار ثبت شده است
        $this->assertGreaterThan(0, $sleeve['armhole_drop']);
        $this->assertGreaterThan($sleeve['armhole_before'], $sleeve['armhole']);
        $this->assertNotEmpty(collect($result['notes'])->filter(
            fn (array $note) => str_contains($note['text'], 'حلقه آستین'),
        ));
    }

    public function test_an_impossible_sleeve_cap_is_reported_instead_of_faked(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve'],
            ['bust' => 130, 'waist' => 110, 'hip' => 130, 'bicep' => 60, 'height' => 170],
            ['bust' => 10],
            ['bodice' => ['armhole_depth_extra' => -2]],
        );

        $sleeve = $result['metrics']['sleeve'];

        $this->assertSame('mismatch', $sleeve['status']);
        $this->assertGreaterThan(PatternComposer::CAP_TOLERANCE, abs($sleeve['difference']));

        $warning = collect($result['notes'])->firstWhere('type', 'warning');
        $this->assertNotNull($warning, 'اختلاف باید به کاربر گزارش شود.');
        $this->assertStringContainsString('سرآستین', $warning['text']);
    }

    public function test_the_collar_is_cut_to_the_composed_neckline(): void
    {
        $composer = $this->composer();

        foreach (['shirt', 'stand', 'flat'] as $style) {
            $result = $composer->compose(
                ['bodice' => 'bodice_block', 'collar' => $style],
                $this->measurements(),
                ['bust' => 6],
            );

            $neckline = $composer->necklineLength($this->group($result, 'bodice'));
            $collar = $this->piece($result, 'collar');
            $neckEdge = $composer->edgeWithTag($collar, 'neck');

            $this->assertNotNull($neckEdge, $style.': یقه باید لبه یقه داشته باشد.');
            $this->assertEqualsWithDelta(
                $neckline,
                Geometry::edgeLength($collar['outline'], $neckEdge),
                0.4,
                $style.': لبه یقه باید هم‌اندازه خط یقه باشد.',
            );
            $this->assertSame('fitted', $result['metrics']['collar']['status']);
        }
    }

    public function test_a_knit_neckband_is_cut_shorter_on_purpose_and_says_so(): void
    {
        $composer = $this->composer();

        $result = $composer->compose(
            ['bodice' => 'tshirt', 'collar' => 'band'],
            $this->measurements(),
            ['bust' => 2],
        );

        $band = $this->piece($result, 'neckband');
        $neckline = $composer->necklineLength($this->group($result, 'bodice')) * 2;

        $this->assertEqualsWithDelta($neckline * 0.85, Geometry::width($band['outline']), 0.2);
        $this->assertStringContainsString('کوتاه‌تر', collect($result['notes'])->last()['text']);
    }

    public function test_sewing_relations_join_the_waist_the_armhole_and_the_neckline(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'bodice_block', 'sleeve' => 'sleeve', 'skirt' => 'skirt_a_line', 'collar' => 'stand'],
            $this->measurements(),
            ['bust' => 6, 'waist' => 4, 'hip' => 6],
        );

        $labels = array_column($result['sewing_relations'], 'label');

        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'کمر')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'حلقه')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'یقه')));
        $this->assertNotEmpty(array_filter($labels, fn ($label) => str_contains($label, 'پهلو')));

        $codes = array_column($result['pieces'], 'code');

        foreach ($result['sewing_relations'] as $relation) {
            $this->assertContains($relation['from']['piece'], $codes);
            $this->assertContains($relation['to']['piece'], $codes);
        }
    }

    public function test_invalid_selections_are_refused_in_persian(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements();

        $cases = [
            [[], 'بالاتنه'],
            [['sleeve' => 'sleeve'], 'بالاتنه'],
            [['collar' => 'shirt'], 'یقه بدون بالاتنه'],
            [['bodice' => 'bodice_block', 'skirt' => 'skirt_a_line', 'pants' => 'pants_straight'], 'دامن و شلوار'],
            [['bodice' => 'skirt_a_line'], 'بالاتنه نیست'],
            [['bodice' => 'bodice_block', 'collar' => 'کج'], 'شناخته نشد'],
            [['bodice' => 'bodice_block', 'sleeve' => 'dress'], 'شناخته نشد'],
        ];

        foreach ($cases as [$selection, $expected]) {
            try {
                $composer->compose($selection, $measurements);
                $this->fail('این ترکیب باید رد می‌شد: '.json_encode($selection));
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }
    }

    public function test_composed_pieces_render_through_the_svg_renderer(): void
    {
        $result = $this->composer()->compose(
            ['bodice' => 'shirt_classic', 'sleeve' => 'sleeve', 'pants' => 'pants_straight', 'collar' => 'shirt'],
            $this->measurements('44'),
            ['bust' => 10, 'waist' => 8, 'hip' => 8],
        );

        $svg = app(SvgRenderer::class)->renderPieces(PatternComposer::toModels($result['pieces']), ['width' => 600]);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertGreaterThanOrEqual(count($result['pieces']), substr_count($svg, '<path'));

        foreach ($result['pieces'] as $piece) {
            $this->assertStringContainsString($piece['name'], $svg, $piece['code'].': نام قطعه روی نقشه');

            $model = new PatternPiece($piece);
            $this->assertGreaterThan(1, $model->area(), $piece['code'].': مساحت قطعه باید معنادار باشد.');
            $this->assertGreaterThan(1, $model->perimeter(), $piece['code']);
        }
    }

    public function test_every_bodice_and_lower_combination_produces_a_joinable_waist(): void
    {
        $composer = $this->composer();
        $measurements = $this->measurements('42');

        foreach (PatternComposer::BODICE_BLOCKS as $bodice) {
            foreach (array_merge(PatternComposer::SKIRT_BLOCKS, PatternComposer::PANTS_BLOCKS) as $lower) {
                $result = $composer->compose(
                    ['bodice' => $bodice, 'lower' => $lower],
                    $measurements,
                    ['bust' => 8, 'waist' => 6, 'hip' => 8],
                );

                $message = $bodice.' + '.$lower;

                $this->assertGreaterThanOrEqual(4, count($result['pieces']), $message);
                $this->assertEqualsWithDelta(
                    $composer->waistGirth($this->group($result, 'bodice')),
                    $composer->waistGirth($this->group($result, 'lower')),
                    0.5,
                    $message.': کمرها باید جور شوند.',
                );
            }
        }
    }
}
