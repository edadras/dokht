<?php

namespace Tests\Unit;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\SeamAllowanceService;
use App\Services\Pattern\SewingRelationBuilder;
use App\Support\Measurements;
use Tests\TestCase;

/**
 * کامل کردن جفت‌های دوخت با هندسه.
 *
 * `suggest()` روی نقش قطعه‌ها کار می‌کند و درزهایی را می‌بیند که نامشان را
 * می‌شناسد: پهلو، سرشانه، آستین، یقه، یوک، مچ‌بند، کمربند. درز پنلی — پرنسسی،
 * کرست، ترک‌دار — نامی ندارد و روی مسیرِ خط‌شکسته ده‌ها لبهٔ پشت‌سرهم است.
 * `complete()` همان‌ها را با طول و برچسب و خویشاوندی قطعه‌ها جفت می‌کند.
 *
 * جای خطر این تابع «جفتِ اشتباه» است، نه «جفتِ کم»: درزی که وجود ندارد لباس را
 * روی مانکن پیچ می‌دهد، ولی درزی که بسته نشده فقط باز می‌ماند. پس بیشتر این
 * آزمون‌ها می‌پایند که چیزی که نباید جفت شود، جفت نشود.
 */
class SewingCompletionTest extends TestCase
{
    /** الگوی در حافظه از یک مدل کاتالوگ؛ این آزمون به پایگاه داده کاری ندارد. */
    protected function pattern(string $key, string $size = '40'): Pattern
    {
        $generator = GeneratorRegistry::make($key);
        $pieces = $generator->generate(
            Measurements::complete(Measurements::fromSize($size)),
            [],
            $generator->defaultParams(),
        );

        $models = collect($pieces)->map(function (array $piece, int $index) {
            $model = new PatternPiece;
            $model->code = (string) ($piece['code'] ?? 'piece-'.$index);
            $model->name = (string) ($piece['name'] ?? '');
            $model->outline = $piece['outline'];
            $model->meta = $piece['meta'] ?? [];
            $model->darts = $piece['darts'] ?? [];
            $model->layer = (string) ($piece['layer'] ?? 'outer');
            $model->cut_quantity = (int) ($piece['cut_quantity'] ?? 1);
            $model->on_fold = (bool) ($piece['on_fold'] ?? false);

            return $model;
        });

        $pattern = new Pattern(['name' => $key]);
        $pattern->setRelation('pieces', $models);

        return $pattern;
    }

    /** @return array<int, array<string, mixed>> */
    protected function complete(string $key, string $size = '40'): array
    {
        $pattern = $this->pattern($key, $size);

        return SewingRelationBuilder::complete($pattern, SewingRelationBuilder::suggest($pattern));
    }

    /** «قطعه→قطعه» برای مقایسهٔ ساده در ادعاها. */
    protected function links(array $relations): array
    {
        return array_map(
            fn (array $relation) => $relation['from']['piece'].' → '.$relation['to']['piece'],
            $relations,
        );
    }

    public function test_a_princess_bodice_gets_its_two_panel_seams(): void
    {
        $links = $this->links($this->complete('bodice_princess_armhole'));

        $this->assertContains('front-center → front-side', $links, 'درز پرنسسی جلو باید بسته شود.');
        $this->assertContains('back-center → back-side', $links, 'درز پرنسسی پشت باید بسته شود.');
        $this->assertContains('front-side → back-side', $links, 'درز پهلو باید بسته شود.');
    }

    public function test_a_six_panel_corset_is_sewn_panel_by_panel_in_order(): void
    {
        $links = $this->links($this->complete('bodice_corset'));

        foreach ([
            'front-panel-1 → front-panel-2',
            'front-panel-2 → front-panel-3',
            'back-panel-1 → back-panel-2',
            'back-panel-2 → back-panel-3',
            'front-panel-3 → back-panel-3',
        ] as $expected) {
            $this->assertContains($expected, $links, "«{$expected}» باید دوخته شود.");
        }

        // پنل یک به پنل سه نمی‌رسد؛ پنل دو بینشان است
        $this->assertNotContains('front-panel-1 → front-panel-3', $links);
        $this->assertNotContains('front-panel-1 → back-panel-1', $links, 'مرکز جلو به مرکز پشت دوخته نمی‌شود.');
    }

    public function test_the_bodice_waist_is_sewn_to_the_skirt_waist_not_to_the_other_bodice_panel(): void
    {
        $relations = $this->complete('evening_a_line');
        $waist = array_values(array_filter($relations, fn (array $r) => str_contains($r['label'], 'کمر')));

        $this->assertNotEmpty($waist, 'کمر بالاتنه باید به کمر دامن برسد.');

        foreach ($waist as $relation) {
            $pair = $relation['from']['piece'].'|'.$relation['to']['piece'];

            $this->assertTrue(
                str_contains($pair, 'bodice') && str_contains($pair, 'skirt'),
                "خط کمر باید بالاتنه را به دامن بدوزد، نه «{$pair}» را.",
            );
        }
    }

    /**
     * حلقه فقط به آستین می‌رود.
     *
     * دو پنلِ یک بالاتنه هر دو حلقه دارند و طول حلقه‌شان هم به هم نزدیک است؛
     * اگر جفت‌سازی فقط با طول کار کند، این دو را به هم می‌دوزد و بالاتنه روی
     * مانکن بسته می‌ماند و دست از آن رد نمی‌شود. این آزمون همان را می‌پاید —
     * یک بار روی بالاتنهٔ بی‌آستین (نباید هیچ دوختِ حلقه‌ای بسازد) و یک بار روی
     * کل کاتالوگ (هر دوختِ حلقه باید یک سرش آستین باشد).
     */
    public function test_an_armhole_is_only_ever_sewn_to_a_sleeve(): void
    {
        foreach (['bodice_princess_armhole', 'bodice_block'] as $key) {
            $armholes = array_filter($this->complete($key), fn (array $r) => str_contains($r['label'], 'حلقه'));

            $this->assertSame([], $armholes, "«{$key}» آستین ندارد؛ حلقه‌اش نباید به چیزی دوخته شود.");
        }

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            $pattern = $this->pattern($key);
            $parts = $pattern->pieces->mapWithKeys(
                fn (PatternPiece $piece) => [$piece->code => (string) ($piece->meta['part'] ?? '')],
            );

            foreach (SewingRelationBuilder::complete($pattern, SewingRelationBuilder::suggest($pattern)) as $relation) {
                if (! str_contains($relation['label'], 'حلقه')) {
                    continue;
                }

                /*
                 * آسترِ آستین هم آستین است. قطعهٔ «sleeve-lining» در meta.part
                 * خودش «lining» نوشته، پس با خواندنِ خامِ part از قلم می‌افتد؛
                 * آسترِ آستین ولی درست مثل رو در حلقهٔ آسترِ بالاتنه کار گذاشته
                 * می‌شود. آن‌چه این آزمون می‌پاید دوختنِ دو پنلِ بالاتنه به هم
                 * است، نه این.
                 */
                $partOf = function (string $code) use ($parts): string {
                    $own = $parts[$code] ?? '';

                    if ($own !== '' && $own !== 'lining') {
                        return $own;
                    }

                    $outer = preg_replace('/-lining$/', '', $code);

                    return $outer !== $code ? ($parts[$outer] ?? $own) : $own;
                };

                $sleeves = array_filter(
                    [$partOf($relation['from']['piece']), $partOf($relation['to']['piece'])],
                    fn (string $part) => $part === 'sleeve',
                );

                $this->assertCount(
                    1,
                    $sleeves,
                    "«{$key}»: حلقه فقط به آستین دوخته می‌شود، نه به "
                        .$relation['from']['piece'].' و '.$relation['to']['piece'],
                );
            }
        }
    }

    /**
     * سرآستین باید تمامش دوخته شود، نه دو سرش.
     *
     * پیش‌تر فقط نخستین و آخرین لبهٔ سرآستین به حلقه می‌رفت و میانهٔ کمان
     * بی‌دوخت می‌ماند؛ روی پیراهن کلاسیک یعنی کمانِ ۹٫۶ سانتی‌متری به حلقهٔ ۱۷
     * سانتی‌متری، و آستینی که روی مانکن مچاله می‌شد.
     */
    public function test_the_whole_sleeve_cap_is_sewn_into_the_armhole(): void
    {
        $checked = 0;

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            $pattern = $this->pattern($key);
            $relations = SewingRelationBuilder::suggest($pattern);

            $covered = [];

            foreach ($relations as $relation) {
                foreach (['from', 'to'] as $side) {
                    $piece = $relation[$side]['piece'];

                    foreach ((array) ($relation[$side]['edges'] ?? [$relation[$side]['edge']]) as $edge) {
                        $covered[$piece.'|'.(int) $edge] = true;
                    }
                }
            }

            $service = new SeamAllowanceService;

            $torso = $pattern->pieces->contains(
                fn (PatternPiece $piece) => in_array($piece->meta['part'] ?? '', ['front_bodice', 'back_bodice', 'yoke'], true),
            );

            if (! $torso) {
                continue; // بلوک آستینِ تنها، حلقه‌ای ندارد که به آن دوخته شود
            }

            foreach ($pattern->pieces as $piece) {
                if (($piece->meta['part'] ?? '') !== 'sleeve') {
                    continue;
                }

                $cap = [];

                foreach ($service->edgeTags($piece) as $edge => $tag) {
                    if ($tag === 'armhole') {
                        $cap[] = $edge;
                    }
                }

                if (count($cap) < 2) {
                    continue; // سرآستینِ یک‌لبه‌ای چیزی برای جا ماندن ندارد
                }

                $loose = array_values(array_filter(
                    $cap,
                    fn (int $edge) => ! isset($covered[$piece->code.'|'.$edge]),
                ));

                $checked++;

                $this->assertSame(
                    [],
                    $loose,
                    "«{$key}»: لبه‌های سرآستینِ «{$piece->code}» شمارهٔ "
                        .implode('، ', $loose).' به حلقه دوخته نشدند.',
                );
            }
        }

        $this->assertGreaterThan(20, $checked, 'باید سرآستین‌های واقعی سنجیده شده باشند.');
    }

    public function test_shell_is_never_sewn_to_lining(): void
    {
        foreach (['evening_a_line', 'bodice_corset', 'blazer'] as $key) {
            $relations = $this->complete($key);

            $this->assertNotEmpty($relations, "«{$key}» باید درزِ کامل‌شده داشته باشد، وگرنه این آزمون چیزی را نمی‌سنجد.");

            foreach ($relations as $relation) {
                $lining = array_filter(
                    [$relation['from']['piece'], $relation['to']['piece']],
                    fn (string $code) => str_contains($code, 'lining'),
                );

                $this->assertNotCount(1, $lining, "«{$key}»: آستر به رو دوخته نمی‌شود.");
            }
        }
    }

    public function test_the_two_sides_of_every_pair_are_the_same_length(): void
    {
        foreach (['bodice_corset', 'bodice_princess_armhole', 'evening_a_line', 'trad_qipao', 'suit_jacket'] as $key) {
            $pattern = $this->pattern($key);
            $service = new SeamAllowanceService;
            $points = $pattern->pieces->mapWithKeys(fn (PatternPiece $p) => [$p->code => $p->points()]);

            foreach (SewingRelationBuilder::complete($pattern, SewingRelationBuilder::suggest($pattern)) as $relation) {
                $length = function (array $side) use ($points): float {
                    $total = 0.0;

                    foreach ($side['edges'] as $edge) {
                        $total += Geometry::edgeLength($points[$side['piece']], (int) $edge);
                    }

                    return $total;
                };

                $a = $length($relation['from']);
                $b = $length($relation['to']);

                /*
                 * درزی که کپیِ درزِ رو است، درست همان‌قدر متوازن است که خودِ
                 * درزِ رو — نه بیشتر، نه کمتر. اگر پهلوی بالاتنهٔ رو ۲۶٫۳ به
                 * ۲۲٫۸ باشد، آسترش هم همان است؛ ایراد آن‌جا در الگوی بالاتنه
                 * است، نه در کپی. پس این‌ها با دو سرِ همان درزِ رو سنجیده
                 * می‌شوند. آن‌چه این آزمون می‌پاید، درزِ *حدس‌زده* است.
                 */
                if (isset($relation['mirrors'])) {
                    [$outerFrom, $outerTo] = explode('|', $relation['mirrors']);

                    $this->assertEqualsWithDelta(
                        $length(['piece' => $outerFrom, 'edges' => $relation['from']['edges']]),
                        $a,
                        max(0.5, $a * 0.05),
                        "«{$key}» درز «{$relation['label']}»: کپیِ آستر باید همان اندازهٔ روی خودش باشد.",
                    );
                    $this->assertEqualsWithDelta(
                        $length(['piece' => $outerTo, 'edges' => $relation['to']['edges']]),
                        $b,
                        max(0.5, $b * 0.05),
                        "«{$key}» درز «{$relation['label']}»: کپیِ آستر باید همان اندازهٔ روی خودش باشد.",
                    );

                    continue;
                }

                $this->assertEqualsWithDelta(
                    $a,
                    $b,
                    max(0.5, $a * 0.12),
                    "«{$key}» درز «{$relation['label']}»: دو سر باید هم‌اندازه باشند ({$a} و {$b}).",
                );
            }

            unset($service);
        }
    }

    public function test_no_edge_is_sewn_twice(): void
    {
        foreach (['bodice_corset', 'shirt_classic', 'evening_a_line', 'pants_straight'] as $key) {
            $seen = [];

            foreach ($this->complete($key) as $relation) {
                foreach (['from', 'to'] as $side) {
                    foreach ($relation[$side]['edges'] as $edge) {
                        $id = $relation[$side]['piece'].'|'.$edge;

                        $this->assertArrayNotHasKey($id, $seen, "«{$key}»: لبهٔ «{$id}» دو بار دوخته شده.");
                        $seen[$id] = true;
                    }
                }
            }
        }
    }

    public function test_completion_never_touches_an_edge_the_manual_list_already_used(): void
    {
        foreach (['shirt_classic', 'dress', 'blazer'] as $key) {
            $pattern = $this->pattern($key);
            $manual = SewingRelationBuilder::suggest($pattern);

            $used = [];

            foreach ($manual as $relation) {
                foreach (['from', 'to'] as $side) {
                    $used[$relation[$side]['piece'].'|'.$relation[$side]['edge']] = true;
                }
            }

            foreach (SewingRelationBuilder::complete($pattern, $manual) as $relation) {
                foreach (['from', 'to'] as $side) {
                    foreach ($relation[$side]['edges'] as $edge) {
                        $this->assertArrayNotHasKey(
                            $relation[$side]['piece'].'|'.$edge,
                            $used,
                            "«{$key}»: لبه‌ای که فهرست دستی گرفته دوباره دوخته شده.",
                        );
                    }
                }
            }
        }
    }

    /** قطعه‌هایی که اگر بی‌دوخت بمانند، لباس روی مانکن تکه‌تکه می‌ماند. */
    protected const MAIN_PARTS = [
        'front_bodice', 'back_bodice', 'front_panel', 'back_panel',
        'skirt_front', 'skirt_back', 'skirt_panel', 'front_leg', 'back_leg', 'sleeve', 'yoke',
    ];

    /**
     * مدل‌هایی که هنوز یک قطعهٔ اصلیِ بی‌دوخت دارند، با دلیلِ نوشته‌شده.
     *
     * این‌ها را «درست» نکرده‌ایم چون درزشان را هندسه نمی‌تواند حدس بزند: ترکِ
     * دامن ترک‌دار و گودهٔ گودت به دو پنلِ کناری می‌رسند که طولشان با هیچ کمانِ
     * دیگری نمی‌خواند، پیشبند اورال روی تنه می‌نشیند نه در درز، و لایه‌های
     * آستین لایه‌ای اصلاً به هم دوخته نمی‌شوند. راهش برچسب‌گذاری در خود ژنراتور
     * است، نه حدس زدن این‌جا.
     */
    protected const ALLOW_LOOSE = [
        'evening_ball_gown', 'skirt_godet', 'skirt_gored', 'dress_mermaid',
        'overall', 'sleeve_layered', 'swim_skirted',
    ];

    public function test_no_main_piece_of_the_catalogue_is_left_unsewn(): void
    {
        $loose = [];
        $models = 0;

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            $pattern = $this->pattern($key);
            $manual = SewingRelationBuilder::suggest($pattern);
            $linked = [];

            foreach ($manual as $relation) {
                $linked[$relation['from']['piece']] = true;
                $linked[$relation['to']['piece']] = true;
            }

            foreach (SewingRelationBuilder::complete($pattern, $manual) as $relation) {
                $linked[$relation['from']['piece']] = true;
                $linked[$relation['to']['piece']] = true;
            }

            $main = $pattern->pieces->filter(
                fn (PatternPiece $piece) => in_array($piece->meta['part'] ?? '', static::MAIN_PARTS, true)
                    && $piece->layer !== 'lining',
            );

            if ($main->count() < 2) {
                continue; // بلوک تک‌قطعه‌ای درزی ندارد که بسته شود
            }

            $models++;
            $orphans = $main->reject(fn (PatternPiece $piece) => isset($linked[$piece->code]));

            if ($orphans->isNotEmpty() && ! in_array($key, static::ALLOW_LOOSE, true)) {
                $loose[] = $key.': '.$orphans->pluck('code')->implode('، ');
            }
        }

        $this->assertGreaterThan(120, $models, 'باید بیشتر کاتالوگ سنجیده شده باشد.');
        $this->assertSame([], $loose, "قطعهٔ اصلیِ بی‌دوخت:\n - ".implode("\n - ", $loose));
    }

    public function test_the_result_is_the_same_every_run(): void
    {
        $first = $this->links($this->complete('bodice_corset'));
        $second = $this->links($this->complete('bodice_corset'));

        $this->assertSame($first, $second, 'خروجی باید قطعی باشد.');
    }
}
