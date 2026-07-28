<?php

namespace Tests\Unit\Generators;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * آزمون کاتالوگ بلوک‌های بالاتنه.
 *
 * ملاک «دوختنی بودن» است، نه فقط اجرا شدن کد: مسیر هر قطعه بسته و بدون تقاطع
 * باشد، برچسب همه لبه‌ها کامل باشد، دور تمام‌شده روی سینه و کمر و باسن دقیقاً
 * «دور بدن + آزادی» دربیاید و درزهایی که به هم دوخته می‌شوند هم‌اندازه باشند.
 */
class BodiceCatalogTest extends TestCase
{
    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'];

    /** بلوک‌هایی که این کاتالوگ می‌سازد. */
    protected const KEYS = [
        'bodice_dartless',
        'bodice_princess_armhole',
        'bodice_princess_shoulder',
        'bodice_empire',
        'bodice_drop_waist',
        'bodice_wrap',
        'bodice_peplum',
        'bodice_corset',
        'bodice_boxy',
        'bodice_yoke',
        'bodice_double_breasted',
        'bodice_knit',
    ];

    /**
     * اندازه‌های آزمون: سه سایز بانوان و یک سایز کودک.
     *
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
        return ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4];
    }

    public function test_every_catalog_block_is_registered_in_the_bodice_group(): void
    {
        foreach (static::KEYS as $key) {
            $this->assertTrue(GeneratorRegistry::has($key), "تولیدکننده «{$key}» در فهرست نیست.");
            $this->assertSame('bodice', GeneratorRegistry::groupOf($key), "گروه «{$key}» بالاتنه نیست.");

            $generator = GeneratorRegistry::make($key);

            $this->assertNotSame('', $generator->label());
            $this->assertNotEmpty($generator->paramsSchema());
            $this->assertSame(
                array_keys($generator->paramsSchema()),
                array_keys($generator->defaultParams()),
                "پیش‌فرض‌های «{$key}» با توضیح پارامترها هم‌خوان نیست.",
            );
        }
    }

    public function test_every_block_produces_sewable_pieces_at_four_body_sizes(): void
    {
        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());

                $this->assertNotEmpty($pieces, "«{$key}» در سایز {$size} هیچ قطعه‌ای نساخت.");

                foreach ($pieces as $piece) {
                    $where = "{$key}/{$size}/{$piece['code']}";

                    $this->assertSame([], Geometry::validatePiece($piece), "قطعه {$where} سالم نیست.");
                    $this->assertCount(
                        count($piece['outline']),
                        $piece['meta']['edges'] ?? [],
                        "برچسب لبه‌های {$where} کامل نیست.",
                    );
                    $this->assertArrayHasKey('fold_edges', $piece['meta'], "meta.fold_edges روی {$where} نیست.");

                    foreach ($piece['meta']['edges'] as $tag) {
                        $this->assertContains($tag, static::EDGE_TAGS, "برچسب لبه ناشناخته روی {$where}.");
                    }

                    foreach ($piece['meta']['fold_edges'] as $edge) {
                        $this->assertArrayHasKey($edge, $piece['outline'], "لبه تای پارچه روی {$where} بیرون از مسیر است.");
                    }
                }
            }
        }
    }

    public function test_finished_girth_matches_body_plus_ease(): void
    {
        foreach (static::KEYS as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
                [$girth, $target] = $this->girth($pieces);

                $this->assertNotNull($target, "«{$key}» دور هدف را روی قطعه‌ها ثبت نکرده است.");
                $this->assertNotEmpty($girth, "«{$key}» در سایز {$size} هیچ دور اندازه‌گیری‌شده‌ای ندارد.");

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

    public function test_princess_and_panel_seams_match_between_neighbours(): void
    {
        foreach (['bodice_princess_armhole', 'bodice_princess_shoulder', 'bodice_corset'] as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
                $seams = [];

                foreach ($pieces as $piece) {
                    if (($piece['layer'] ?? 'outer') !== 'outer') {
                        continue;
                    }

                    foreach ($piece['meta']['seams'] ?? [] as $seam => $length) {
                        $seams[$seam][] = $length;
                    }
                }

                $this->assertNotEmpty($seams, "«{$key}» هیچ درز مدلی ثبت نکرده است.");

                foreach ($seams as $seam => $lengths) {
                    if (count($lengths) < 2) {
                        continue;
                    }

                    $this->assertEqualsWithDelta(
                        min($lengths),
                        max($lengths),
                        0.1,
                        "دو لبه درز «{$seam}» در «{$key}» سایز {$size} هم‌اندازه نیست.",
                    );
                }
            }
        }
    }

    public function test_front_and_back_side_seams_walk_equal(): void
    {
        foreach (['bodice_dartless', 'bodice_boxy', 'bodice_double_breasted', 'bodice_knit'] as $key) {
            $generator = GeneratorRegistry::make($key);

            foreach ($this->bodies() as $size => $body) {
                $pieces = $generator->generate($body, $this->ease(), $generator->defaultParams());
                $sides = [];

                foreach ($pieces as $piece) {
                    $side = $piece['meta']['side'] ?? null;

                    if ($side === null || ($piece['layer'] ?? 'outer') !== 'outer') {
                        continue;
                    }

                    $sides[$side] = (float) ($piece['meta']['side_seam_length'] ?? 0);
                }

                $this->assertArrayHasKey('front', $sides);
                $this->assertArrayHasKey('back', $sides);
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
     * برای هر پارامتر، حالت‌های مرزی: هر گزینه انتخابی، وارونه هر کلید و کمینه و بیشینه.
     *
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
     * جمع دور تمام‌شده روی پنل‌های پوسته و دور هدفی که خود مدل ثبت کرده است.
     *
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
