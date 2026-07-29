<?php

namespace Tests\Unit\Generators;

use App\Models\GarmentType;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون کاتالوگ لباس‌های کامل.
 *
 * هر لباس باید از قطعه‌های واقعی سرِ هم شده باشد — بالاتنه، پایین‌تنه، آستین،
 * یقه، سجاف و آستر — نه فقط یک مسیر بسته. برای همین علاوه بر سالم بودن هندسه،
 * بررسی می‌شود که هر لباس همان اجزایی را داشته باشد که خیاط برایش می‌برد.
 */
class GarmentCatalogTest extends TestCase
{
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'];

    /** لباس‌های کاملی که این کاتالوگ می‌سازد. */
    protected const KEYS = [
        'manteau_straight', 'manteau_flared', 'manteau_belted', 'manteau_abaya', 'manteau_short',
        'abaya', 'tunic', 'cardigan', 'vest_single', 'vest_double',
        'coat_classic', 'coat_trench', 'raincoat', 'bomber',
        'hoodie', 'sweatshirt', 'kaftan', 'kimono_robe',
        'jumpsuit', 'overall', 'romper',
        'dress_short', 'dress_maxi', 'dress_flared', 'dress_mermaid', 'bridal_gown',
        'child_dress', 'child_hoodie', 'child_tshirt',
    ];

    /** اجزایی که هر لباس بدون آن‌ها دوخته نمی‌شود. */
    protected const REQUIRED_PARTS = [
        'manteau_straight' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing', 'pocket'],
        'manteau_flared' => ['front_bodice', 'back_bodice', 'sleeve', 'facing'],
        'manteau_belted' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing', 'belt'],
        'manteau_abaya' => ['front_bodice', 'back_bodice', 'facing'],
        'manteau_short' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing', 'waistband', 'cuff'],
        'abaya' => ['front_bodice', 'back_bodice', 'facing', 'belt'],
        'tunic' => ['front_bodice', 'back_bodice', 'sleeve', 'placket', 'facing'],
        'cardigan' => ['front_bodice', 'back_bodice', 'sleeve', 'placket', 'pocket'],
        'vest_single' => ['front_bodice', 'back_bodice', 'facing', 'lining'],
        'vest_double' => ['front_bodice', 'back_bodice', 'facing', 'lining'],
        'coat_classic' => ['front_bodice', 'back_bodice', 'sleeve', 'facing', 'lining', 'pocket'],
        'coat_trench' => ['front_bodice', 'back_bodice', 'sleeve', 'collar', 'facing', 'lining', 'belt', 'pocket'],
        'raincoat' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'facing', 'pocket'],
        'bomber' => ['front_bodice', 'back_bodice', 'sleeve', 'facing', 'lining', 'waistband', 'cuff', 'collar'],
        'hoodie' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'waistband', 'cuff', 'pocket'],
        'sweatshirt' => ['front_bodice', 'back_bodice', 'sleeve', 'waistband', 'cuff', 'collar'],
        'kaftan' => ['front_bodice', 'back_bodice', 'facing'],
        'kimono_robe' => ['front_bodice', 'back_bodice', 'placket', 'belt', 'pocket'],
        'jumpsuit' => ['front_bodice', 'back_bodice', 'front_leg', 'back_leg', 'facing'],
        'overall' => ['front_leg', 'back_leg', 'front_panel', 'belt', 'pocket'],
        'romper' => ['front_bodice', 'back_bodice', 'front_leg', 'back_leg', 'facing', 'waistband'],
        'dress_short' => ['front_bodice', 'back_bodice', 'facing', 'lining'],
        'dress_maxi' => ['front_bodice', 'back_bodice', 'facing'],
        'dress_flared' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'sleeve', 'facing'],
        'dress_mermaid' => ['front_panel', 'back_panel', 'skirt_front', 'skirt_back', 'facing', 'lining'],
        'bridal_gown' => ['front_panel', 'back_panel', 'skirt_front', 'skirt_back', 'lining', 'waistband'],
        'child_dress' => ['front_bodice', 'back_bodice', 'skirt_front', 'skirt_back', 'facing', 'belt'],
        'child_hoodie' => ['front_bodice', 'back_bodice', 'sleeve', 'hood', 'waistband', 'cuff', 'pocket'],
        'child_tshirt' => ['front_bodice', 'back_bodice', 'sleeve', 'collar'],
    ];

    /**
     * @return array<string, array<string, float>>
     */
    protected function bodies(): array
    {
        return [
            '34' => Measurements::fromSize('34'),
            '40' => Measurements::fromSize('40'),
            '48' => Measurements::fromSize('48'),
            'child' => Measurements::complete([
                'height' => 116, 'bust' => 60, 'waist' => 55, 'hip' => 64,
                'shoulder_width' => 27, 'arm_length' => 38,
            ]),
        ];
    }

    /** @return array<string, float> */
    protected function ease(): array
    {
        return ['bust' => 8, 'waist' => 6, 'hip' => 8, 'bicep' => 5];
    }

    public function test_every_garment_is_registered_in_the_garment_group(): void
    {
        foreach (static::KEYS as $key) {
            $this->assertTrue(GeneratorRegistry::has($key), "تولیدکننده «{$key}» در فهرست نیست.");
            $this->assertSame('garment', GeneratorRegistry::groupOf($key), "گروه «{$key}» لباس کامل نیست.");

            $generator = GeneratorRegistry::make($key);

            $this->assertNotSame('', $generator->label());
            $this->assertNotEmpty($generator->paramsSchema());
            $this->assertSame(
                array_keys($generator->paramsSchema()),
                array_keys($generator->defaultParams()),
            );
        }
    }

    public function test_every_garment_produces_sewable_pieces_at_four_body_sizes(): void
    {
        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());

                $this->assertNotEmpty($pieces, "«{$key}» در سایز {$size} هیچ قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $where = "{$key}/{$size}/{$piece['code']}";

                    $this->assertSame([], Geometry::validatePiece($piece), "قطعه {$where} سالم نیست.");
                    $this->assertCount(count($piece['outline']), $piece['meta']['edges'] ?? [], "برچسب لبه‌های {$where} کامل نیست.");
                    $this->assertArrayHasKey('fold_edges', $piece['meta'], "meta.fold_edges روی {$where} نیست.");
                    $this->assertNotNull($piece['grainline'], "راستای پارچه روی {$where} تعیین نشده است.");

                    foreach ($piece['meta']['edges'] as $tag) {
                        $this->assertContains($tag, static::EDGE_TAGS, "برچسب لبه ناشناخته روی {$where}.");
                    }

                    if (($piece['meta']['girth_role'] ?? '') === 'shell') {
                        $this->assertArrayHasKey('waist_y', $piece['meta'], "meta.waist_y روی {$where} نیست.");
                        $this->assertNotEmpty($piece['notches'], "هیچ نشانه جفت‌شدنی روی {$where} نیست.");
                    }
                }
            }
        }
    }

    public function test_each_garment_is_assembled_from_the_expected_blocks(): void
    {
        $body = Measurements::fromSize('40');
        $labels = array_keys(GarmentType::PART_LABELS);

        foreach (static::REQUIRED_PARTS as $key => $expected) {
            $generator = GeneratorRegistry::make($key);
            $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
            $parts = array_values(array_unique(array_map(
                fn (array $piece) => (string) ($piece['meta']['part'] ?? ''),
                $pieces,
            )));

            foreach ($parts as $part) {
                $this->assertContains($part, $labels, "جزء «{$part}» در «{$key}» جزو اجزای شناخته‌شده نیست.");
            }

            foreach ($expected as $part) {
                $this->assertContains($part, $parts, "«{$key}» بدون جزء «{$part}» ساخته شده است.");
            }
        }
    }

    /**
     * لباس بی‌آستین نباید آستین بدهد.
     *
     * در گزینه‌های `outerGarment`، آرایه خالیِ `sleeve` یعنی «آستین با تنظیمات
     * پیش‌فرض»، نه «بی‌آستین». جلیقه با همین یک اشتباه هم نوار اریب حلقه می‌گرفت
     * هم یک جفت آستین — دو راه‌حل متضاد برای یک حلقه.
     */
    public function test_a_sleeveless_garment_ships_no_sleeve(): void
    {
        $body = Measurements::fromSize('40');

        foreach (['vest_single', 'vest_double'] as $key) {
            $generator = GeneratorRegistry::make($key);
            $parts = array_map(
                fn (array $piece) => (string) ($piece['meta']['part'] ?? ''),
                $generator->generate($body, $this->ease(), $generator->defaultParams()),
            );

            $this->assertNotContains('sleeve', $parts, "«{$key}» جلیقه است و آستین ندارد.");
        }
    }

    public function test_lined_garments_produce_lining_pieces(): void
    {
        $body = Measurements::fromSize('40');

        foreach (['coat_classic', 'coat_trench', 'bomber', 'vest_single', 'dress_short'] as $key) {
            $generator = GeneratorRegistry::make($key);
            $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
            $layers = array_column(array_column($pieces, 'meta'), 'girth_role');

            $this->assertContains('lining', $layers, "«{$key}» با وجود آستر، قطعه آستر ندارد.");
            $this->assertNotEmpty(
                array_filter($pieces, fn (array $piece) => ($piece['layer'] ?? '') === 'lining'),
                "لایه قطعه‌های آستر «{$key}» درست ثبت نشده است.",
            );
        }
    }

    public function test_finished_girth_matches_body_plus_ease(): void
    {
        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
                [$girth, $target] = $this->girth($pieces);

                if ($girth === []) {
                    continue; // اورال بنددار بالاتنه ندارد
                }

                $this->assertNotNull($target, "«{$key}» دور هدف را ثبت نکرده است.");

                foreach ($girth as $line => $value) {
                    $this->assertEqualsWithDelta(
                        $target[$line],
                        $value,
                        0.3,
                        "دور {$line} در «{$key}» سایز {$size} با دور هدف نمی‌خواند.",
                    );
                }
            }
        }
    }

    public function test_front_and_back_side_seams_walk_equal(): void
    {
        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
                $sides = [];

                foreach ($pieces as $piece) {
                    if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                        continue;
                    }

                    $side = $piece['meta']['side'] ?? null;

                    if ($side === null || ($piece['meta']['panel'] ?? null) === 'center') {
                        continue;
                    }

                    $sides[$side] = (float) ($piece['meta']['side_seam_length'] ?? 0);
                }

                if (! isset($sides['front'], $sides['back'])) {
                    continue;
                }

                $this->assertEqualsWithDelta(
                    $sides['front'],
                    $sides['back'],
                    0.1,
                    "درز پهلوی جلو و پشت «{$key}» در سایز {$size} هم‌اندازه نیست.",
                );
            }
        }
    }

    public function test_every_parameter_choice_still_produces_valid_pieces(): void
    {
        $body = Measurements::fromSize('40');

        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->variants($generator->paramsSchema()) as $variant) {
                $params = array_merge($generator->defaultParams(), $variant);
                $pieces = $generator->generate($body, $this->ease(), $params);
                $label = $key.' '.json_encode($variant, JSON_UNESCAPED_UNICODE);

                $this->assertNotEmpty($pieces, "«{$label}» هیچ قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $this->assertSame([], Geometry::validatePiece($piece), "قطعه {$piece['code']} با {$label} سالم نیست.");
                    $this->assertCount(count($piece['outline']), $piece['meta']['edges'] ?? []);
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    protected function variants(array $schema): array
    {
        $variants = [];

        foreach ($schema as $name => $field) {
            $type = $field['type'] ?? 'number';

            if ($type === 'select') {
                foreach (array_keys($field['options'] ?? []) as $option) {
                    $variants[] = [$name => $option];
                }

                continue;
            }

            if ($type === 'toggle') {
                $variants[] = [$name => ! ($field['default'] ?? false)];

                continue;
            }

            if (isset($field['min'], $field['max'])) {
                $variants[] = [$name => $field['min']];
                $variants[] = [$name => $field['max']];
            }
        }

        return $variants;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{0: array<string, float>, 1: array<string, float>|null}
     */
    protected function girth(array $pieces): array
    {
        $girth = [];
        $target = null;

        foreach ($pieces as $piece) {
            if (($piece['meta']['girth_role'] ?? '') !== 'shell') {
                continue;
            }

            $target = $piece['meta']['target_girth'] ?? $target;

            foreach ($piece['meta']['girth'] ?? [] as $line => $value) {
                $girth[$line] = round(($girth[$line] ?? 0) + ($value * ($piece['meta']['girth_factor'] ?? 1)), 3);
            }
        }

        return [$girth, $target];
    }
}
