<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\NotionCollector;
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
        $buttons = array_values(array_filter($rows, fn (array $row) => $row['type'] === 'button'));

        $this->assertCount(1, $buttons);
        $this->assertGreaterThan(2, $buttons[0]['count']);
        $this->assertSame(
            $drills,
            $buttons[0]['count'],
            'تعداد دکمه در فهرست خرید باید دقیقاً به اندازه نشانه‌های روی پاتلت باشد.',
        );
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
            $style = \App\Services\Pattern\Style\StyleRegistry::make($key);
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

                if ($row['length'] !== null && ($row['length'] <= 0 || $row['length'] > 400)) {
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
