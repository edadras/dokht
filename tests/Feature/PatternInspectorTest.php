<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Services\Pattern\PatternBuilder;
use App\Services\Pattern\PatternInspector;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بازرسی الگو روی الگوی دست‌ساز کاربر.
 *
 * سامانه فقط الگوهای کاتالوگ را نمی‌سازد: کاربر در استودیوی طراحی الگو می‌سازد،
 * از عکس درمی‌آورد، در ویرایشگر با دست جابه‌جا می‌کند و از بازارچه می‌خرد. همه
 * این‌ها باید به یک اندازه سنجیده شوند.
 */
class PatternInspectorTest extends TestCase
{
    use RefreshDatabase;

    protected PatternInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new PatternInspector;
    }

    /** الگویی دست‌ساز با یک قطعه دلخواه. */
    protected function handmade(array $outline, array $meta = []): Pattern
    {
        $pattern = Pattern::create([
            'name' => 'الگوی دست‌ساز',
            'measurements' => Measurements::complete([]),
            'ease' => [],
            'params' => [],
            'seam_allowances' => ['default' => 1.0],
            'version' => 1,
        ]);

        $pattern->pieces()->create([
            'code' => 'handmade',
            'name' => 'قطعه دست‌ساز',
            'cut_quantity' => 1,
            'outline' => $outline,
            'meta' => array_merge(['part' => 'front_bodice'], $meta),
        ]);

        return $pattern->load('pieces');
    }

    public function test_a_self_crossing_handmade_piece_is_reported_as_an_error(): void
    {
        $this->actingAsWorkshopUser();

        // مسیر پروانه‌ای: دو ضلع از وسط هم رد می‌شوند
        $report = $this->inspector->inspect($this->handmade([
            ['x' => 0, 'y' => 0], ['x' => 40, 'y' => 60], ['x' => 0, 'y' => 60], ['x' => 40, 'y' => 0],
        ]));

        $this->assertGreaterThan(0, $report['errors']);
        $this->assertLessThan(80, $report['score']);
        $this->assertStringContainsString(
            'خودش را قطع می‌کند',
            implode(' ', array_column($report['findings'], 'message')),
        );
    }

    public function test_a_clean_handmade_piece_passes(): void
    {
        $this->actingAsWorkshopUser();

        $report = $this->inspector->inspect($this->handmade(
            [['x' => 0, 'y' => 0], ['x' => 40, 'y' => 0], ['x' => 40, 'y' => 60], ['x' => 0, 'y' => 60]],
            ['edges' => ['neck', 'side', 'hem', 'side'], 'fold_edges' => []],
        ));

        $this->assertSame(0, $report['errors'], implode("\n", array_column($report['findings'], 'message')));
    }

    public function test_a_notch_that_does_not_sit_on_the_edge_it_claims_is_caught(): void
    {
        $this->actingAsWorkshopUser();

        $pattern = $this->handmade(
            [['x' => 0, 'y' => 0], ['x' => 40, 'y' => 0], ['x' => 40, 'y' => 60], ['x' => 0, 'y' => 60]],
            ['edges' => ['neck', 'side', 'hem', 'side'], 'fold_edges' => []],
        );

        $pattern->pieces->first()->update([
            'notches' => [['x' => 20, 'y' => 30, 'edge' => 0, 'label' => 'نشانه سرگردان']],
        ]);

        $report = $this->inspector->inspect($pattern->fresh('pieces'));
        $messages = implode(' ', array_column($report['findings'], 'message'));

        $this->assertStringContainsString('نشانه سرگردان', $messages);
        $this->assertStringContainsString('فاصله دارد', $messages);
    }

    public function test_every_catalogue_pattern_passes_its_own_inspection(): void
    {
        $this->actingAsWorkshopUser();
        $builder = app(PatternBuilder::class);
        $problems = [];

        foreach (PatternTemplate::query()->limit(20)->get() as $template) {
            $pattern = $builder->createPattern($template, ['name' => 'x'], [
                'measurements' => Measurements::complete([]),
            ]);

            $report = $this->inspector->inspect($pattern);

            if ($report['errors'] > 0) {
                $problems[] = $template->generator.': '.implode(' • ', array_column(
                    array_filter($report['findings'], fn (array $f) => $f['level'] === 'error'),
                    'message',
                ));
            }
        }

        $this->assertSame([], $problems, "الگوی کاتالوگ نباید در بازرسی خودش خطا بدهد:\n".implode("\n", $problems));
    }

    public function test_saving_a_broken_edit_returns_the_inspection_with_the_response(): void
    {
        $user = $this->actingAsWorkshopUser();
        $pattern = $this->handmade(
            [['x' => 0, 'y' => 0], ['x' => 40, 'y' => 0], ['x' => 40, 'y' => 60], ['x' => 0, 'y' => 60]],
            ['edges' => ['neck', 'side', 'hem', 'side'], 'fold_edges' => []],
        );

        $response = $this->actingAs($user)->putJson(route('patterns.geometry', $pattern), [
            'pieces' => [[
                'id' => $pattern->pieces->first()->id,
                // همان قطعه، ولی دو رأس جابه‌جا شده تا مسیر خودش را قطع کند
                'outline' => [
                    ['x' => 0, 'y' => 0], ['x' => 40, 'y' => 60], ['x' => 0, 'y' => 60], ['x' => 40, 'y' => 0],
                ],
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $this->assertGreaterThan(
            0,
            $response->json('inspection.errors'),
            'ذخیره ویرایش باید نتیجه بازرسی را برگرداند تا کاربر بداند چه چیزی خراب شده.',
        );
    }
}
