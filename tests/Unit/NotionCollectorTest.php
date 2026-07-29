<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\NotionCollector;
use App\Services\Pattern\Style\StyleRegistry;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * یراق لباس: دکمه، زیپ، قزن، دکمه فشاری و کش.
 *
 * قاعده این بخش یک جمله است: عددی که در فهرست خرید نوشته می‌شود باید همان چیزی
 * باشد که روی الگو علامت خورده — نه یک عدد ثابت و نه حدسی از روی نوع لباس.
 */
class NotionCollectorTest extends TestCase
{
    protected NotionCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new NotionCollector;
    }

    /** @return array<int, array<string, mixed>> */
    protected function build(string $key, array $params = []): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate(
            Measurements::complete([]),
            [],
            array_merge($generator->defaultParams(), $params),
        );
    }

    /** @return array<int, array<string, mixed>> */
    protected function notionsOf(array $pieces): array
    {
        return $this->collector->forPieces(array_map(fn (array $piece) => [
            'cut_quantity' => $piece['cut_quantity'] ?? 1,
            'meta' => $piece['meta'] ?? [],
            'drills' => $piece['drills'] ?? [],
        ], $pieces));
    }

    public function test_same_notions_are_added_up_and_different_ones_stay_apart(): void
    {
        $rows = $this->collector->forPieces([
            ['cut_quantity' => 1, 'meta' => ['notions' => [
                ['type' => 'button', 'label' => 'دکمه جلو', 'count' => 4, 'size' => 1.5],
            ]]],
            ['cut_quantity' => 1, 'meta' => ['notions' => [
                ['type' => 'button', 'label' => 'دکمه جلو', 'count' => 2, 'size' => 1.5],
                ['type' => 'zip', 'label' => 'زیپ پهلو', 'count' => 1, 'length' => 20.0],
            ]]],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('button', $rows[0]['type']);
        $this->assertSame(6, $rows[0]['count'], 'دو ردیف دکمه هم‌اندازه باید با هم جمع شوند.');
        $this->assertSame('zip', $rows[1]['type']);
        $this->assertSame(20.0, $rows[1]['length']);
    }

    public function test_a_notion_marked_per_cut_multiplies_with_the_cut_quantity(): void
    {
        // مچ‌بند دو بار بریده می‌شود، پس دکمه‌اش هم دو برابر است؛ ولی زیپ مرکز جلو
        // یکی است هرچند تنه جلو دو بار بریده شود.
        $rows = $this->collector->forPieces([
            ['cut_quantity' => 2, 'meta' => ['notions' => [
                ['type' => 'button', 'label' => 'دکمه مچ', 'count' => 1, 'per_cut' => true],
            ]]],
            ['cut_quantity' => 2, 'meta' => ['notions' => [
                ['type' => 'zip', 'label' => 'زیپ مرکز جلو', 'count' => 1, 'length' => 55.0],
            ]]],
        ]);

        $this->assertSame(2, $rows[0]['count']);
        $this->assertSame(1, $rows[1]['count']);
    }

    public function test_a_shirt_reports_the_buttons_that_are_actually_drawn_on_the_placket(): void
    {
        $pieces = $this->build('shirt_classic');
        $placket = null;

        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? null) === 'placket') {
                $placket = $piece;
            }
        }

        $this->assertNotNull($placket, 'پیراهن کلاسیک باید پاتلت داشته باشد.');

        $drills = count(array_filter(
            $placket['drills'] ?? [],
            fn (array $drill) => str_starts_with((string) ($drill['key'] ?? ''), 'button'),
        ));

        $rows = $this->notionsOf($pieces);
        $placketRow = array_values(array_filter(
            $rows,
            fn (array $row) => $row['type'] === 'button' && str_contains($row['label'], 'پیراهن'),
        ));

        $this->assertCount(1, $placketRow);
        $this->assertGreaterThan(2, $placketRow[0]['count']);
        $this->assertSame(
            $drills,
            $placketRow[0]['count'],
            'تعداد دکمه در فهرست خرید باید دقیقاً به اندازه نشانه‌های روی پاتلت باشد.',
        );

        // مچ آستین هم دکمه دارد و چون دو بار بریده می‌شود، دو دکمه می‌خواهد
        $cuffRow = array_values(array_filter(
            $rows,
            fn (array $row) => $row['type'] === 'button' && str_contains($row['label'], 'مچ'),
        ));

        if ($cuffRow !== []) {
            $this->assertSame(2, $cuffRow[0]['count'], 'دکمه مچ برای دو آستین دو عدد است.');
        }
    }

    public function test_a_zip_front_garment_reports_the_measured_zip_length(): void
    {
        $pieces = $this->build('bomber');
        $rows = $this->notionsOf($pieces);
        $zips = array_values(array_filter($rows, fn (array $row) => $row['type'] === 'zip'));

        $this->assertCount(1, $zips, 'بمبر جلوباز زیپ‌دار است و باید یک زیپ اعلام کند.');
        $this->assertGreaterThan(20.0, $zips[0]['length']);
        $this->assertLessThan(120.0, $zips[0]['length']);
    }

    public function test_an_elastic_waistband_reports_the_length_that_is_cut_not_the_body_measure(): void
    {
        $pieces = $this->build('pants_elastic_waist');
        $band = null;

        foreach ($pieces as $piece) {
            if (($piece['meta']['band_stretch'] ?? null) !== null) {
                $band = $piece;
            }
        }

        $this->assertNotNull($band);

        $rows = $this->notionsOf($pieces);
        $elastic = array_values(array_filter($rows, fn (array $row) => $row['type'] === 'elastic'));

        $this->assertNotEmpty($elastic);
        $this->assertSame(
            round((float) $band['meta']['band_girth'], 1),
            $elastic[0]['length'],
            'طول کش باید همان طول بریده‌شده نوار باشد، نه دور کمر بدن.',
        );
        $this->assertLessThan(
            (float) $band['meta']['band_target'],
            (float) $elastic[0]['length'],
            'کش کوتاه‌تر از دور کمر بریده می‌شود تا کشیده شود.',
        );
    }

    public function test_the_hook_and_snap_closures_put_their_marks_on_the_pattern(): void
    {
        $pieces = $this->build('bodice_block');

        foreach ([
            'closure_hook_eye' => 'hook',
            'closure_snap' => 'snap',
        ] as $key => $type) {
            $style = StyleRegistry::make($key);
            $result = $style->apply($pieces, ['params' => $style->defaultParams(), 'measurements' => Measurements::complete([])]);

            $rows = $this->notionsOf($result['pieces']);
            $matching = array_values(array_filter($rows, fn (array $row) => $row['type'] === $type));

            $this->assertNotEmpty($matching, "سبک «{$key}» باید یراق «{$type}» اعلام کند.");
            $this->assertGreaterThan(0, $matching[0]['count']);

            $marks = 0;

            foreach ($result['pieces'] as $piece) {
                foreach ($piece['drills'] ?? [] as $drill) {
                    if (($drill['type'] ?? null) === $type) {
                        $marks++;
                    }
                }
            }

            $this->assertSame(
                $matching[0]['count'],
                $marks,
                "تعداد «{$type}» در فهرست خرید باید با نشانه‌های روی الگو یکی باشد.",
            );
        }
    }

    public function test_a_fitted_skirt_gets_a_zip_that_clears_the_hip_and_can_be_turned_off(): void
    {
        $pieces = $this->build('skirt_straight');
        $zips = array_values(array_filter($this->notionsOf($pieces), fn (array $row) => $row['type'] === 'zip'));

        $this->assertCount(1, $zips, 'دامن راسته با کمربند دوخته باید زیپ داشته باشد.');

        $hipY = null;

        foreach ($pieces as $piece) {
            $hipY ??= $piece['meta']['hip_y'] ?? null;
        }

        $this->assertNotNull($hipY);
        $this->assertGreaterThan(
            (float) $hipY,
            $zips[0]['length'],
            'زیپ دامن باید از خط باسن رد شود، وگرنه دامن پوشیده نمی‌شود.',
        );

        $off = $this->notionsOf($this->build('skirt_straight', ['zip' => 'none']));
        $this->assertSame(
            [],
            array_values(array_filter($off, fn (array $row) => $row['type'] === 'zip')),
            'با خاموش‌کردن گزینه زیپ نباید زیپی در فهرست بماند.',
        );
    }

    public function test_a_center_back_skirt_zip_opens_the_back_panel_off_the_fold(): void
    {
        $onFold = null;

        foreach ($this->build('skirt_straight', ['zip' => 'back']) as $piece) {
            if (($piece['meta']['part'] ?? null) === 'skirt_back') {
                $onFold = (bool) ($piece['on_fold'] ?? false);
            }
        }

        $this->assertNotNull($onFold);
        $this->assertFalse($onFold, 'زیپ مرکز پشت بدون درز مرکزی جایی برای نشستن ندارد.');
    }

    public function test_trousers_with_a_sewn_waistband_get_a_fly_but_elastic_ones_do_not(): void
    {
        $withFly = $this->notionsOf($this->build('pants_cigarette'));
        $zips = array_values(array_filter($withFly, fn (array $row) => $row['type'] === 'zip'));
        $hooks = array_values(array_filter($withFly, fn (array $row) => $row['type'] === 'hook'));

        $this->assertCount(1, $zips, 'شلوار با کمربند دوخته باید زیپ جلو داشته باشد.');
        $this->assertGreaterThan(8.0, $zips[0]['length']);
        $this->assertNotEmpty($hooks, 'کمربند شلوار با قزن بسته می‌شود.');

        $elastic = $this->notionsOf($this->build('pants_elastic_waist'));
        $this->assertSame(
            [],
            array_values(array_filter($elastic, fn (array $row) => $row['type'] === 'zip')),
            'شلوار کمرکشی زیپ نمی‌خواهد؛ خود کش بستِ لباس است.',
        );
    }

    public function test_every_declared_notion_in_the_catalogue_is_sane(): void
    {
        $problems = [];

        foreach (GeneratorRegistry::keys() as $key) {
            foreach ($this->notionsOf($this->build($key)) as $row) {
                if (! array_key_exists($row['type'], NotionCollector::LABELS)) {
                    $problems[] = "{$key}: نوع یراق ناشناخته «{$row['type']}»";
                }

                if ($row['count'] < 1) {
                    $problems[] = "{$key}: تعداد «{$row['label']}» برابر {$row['count']} است";
                }

                // سقف چهار متر برای یراقی است که دور یک لبه می‌پیچد (زیپ، کش،
                // بند). نوار مو و بند دور دم لباس عروس روی کل دور دم می‌نشینند و
                // دم یک دامن پرحجم خودش چهار متر می‌شود؛ برای این‌ها سقف دوازده متر
                // است که همچنان جلوی عدد بی‌معنا را می‌گیرد.
                $ceiling = str_contains($row['label'], 'کرینولین') || str_contains($row['label'], 'نوار مو')
                    ? 1200
                    : 400;

                if ($row['length'] !== null && ($row['length'] <= 0 || $row['length'] > $ceiling)) {
                    $problems[] = "{$key}: طول «{$row['label']}» برابر {$row['length']} سانتی‌متر است";
                }

                if (in_array($row['type'], NotionCollector::BY_LENGTH, true) && $row['length'] === null) {
                    $problems[] = "{$key}: «{$row['label']}» با طول سفارش داده می‌شود ولی طول ندارد";
                }
            }
        }

        $this->assertSame([], $problems, "ایراد در یراق کاتالوگ:\n  - ".implode("\n  - ", $problems));
    }
}
