<?php

namespace Tests\Unit;

use App\Services\Pattern\GarmentFlatService;
use App\Services\Pattern\GeneratorRegistry;
use App\Support\Measurements;
use Tests\TestCase;
use Throwable;

/**
 * نمای دوبعدیِ لباسِ دوخته‌شده.
 *
 * چیزی که این آزمون می‌پاید «قشنگ درآمدن» نیست — آن را چشم می‌بیند. چیزی که
 * می‌پاید این است که نما *از الگو* بیاید: با عوض شدن اندازهٔ مشتری عوض شود، با
 * عوض شدن مدل عوض شود، و عددهایی که کنارش می‌نویسیم با خودِ الگو بخوانند.
 */
class GarmentFlatServiceTest extends TestCase
{
    protected GarmentFlatService $flats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flats = new GarmentFlatService;
    }

    /** @return array<int, array<string, mixed>> */
    protected function pieces(string $key, array $body): array
    {
        $generator = GeneratorRegistry::make($key);

        return $generator->generate($body, [], $generator->defaultParams());
    }

    protected function flatsOf(string $key, array $body): array
    {
        return $this->flats->flats($this->pieces($key, $body), $body);
    }

    public function test_a_dress_gets_four_views_and_each_one_is_a_real_drawing(): void
    {
        $body = Measurements::fromSize('40');
        $out = $this->flatsOf('dress', $body);

        $this->assertTrue($out['ok'], 'نمای پیراهن ساخته نشد.');
        $this->assertSame(['front', 'back', 'right', 'left'], array_keys($out['views']));

        foreach ($out['views'] as $name => $svg) {
            $this->assertStringContainsString('<svg', $svg, "نمای {$name} تصویر نیست.");
            $this->assertStringContainsString('<path', $svg, "نمای {$name} هیچ خطی ندارد.");
            $this->assertGreaterThan(400, strlen($svg), "نمای {$name} تقریباً خالی است.");
            $this->assertDoesNotMatchRegularExpression('/\b(NAN|-?INF)\b/i', $svg, "نمای {$name} عدد نامعتبر دارد.");
        }
    }

    public function test_the_views_follow_the_body_they_are_drawn_for(): void
    {
        $small = Measurements::complete(['height' => 145, 'bust' => 76, 'waist' => 60, 'hip' => 84]);
        $large = Measurements::complete(['height' => 175, 'bust' => 124, 'waist' => 112, 'hip' => 128]);

        $a = $this->flatsOf('dress', $small);
        $b = $this->flatsOf('dress', $large);

        $this->assertNotSame(
            $a['views']['front'],
            $b['views']['front'],
            'نمای دو بدنِ خیلی متفاوت یکسان درآمد؛ یعنی نما از اندازه نمی‌آید.',
        );

        // لباسِ بدنِ بزرگ‌تر باید در همه‌جا بزرگ‌تر باشد
        foreach (['دور سینهٔ دوخته‌شده', 'قد لباس'] as $key) {
            $this->assertGreaterThan(
                $a['measures'][$key] ?? 0,
                $b['measures'][$key] ?? 0,
                "«{$key}» با بزرگ‌شدن بدن بزرگ نشد.",
            );
        }
    }

    /**
     * عددی که کنار نما می‌نویسیم باید با خودِ الگو بخواند.
     *
     * دورِ سینهٔ دوخته‌شده باید نزدیکِ «سینهٔ بدن + آزادی» باشد. اگر روزی نما از
     * قطعه‌ها جدا بیفتد و عددش را از جای دیگری بگیرد، همین‌جا لو می‌رود.
     */
    public function test_the_finished_bust_matches_the_body_plus_its_ease(): void
    {
        $body = Measurements::fromSize('40');
        $out = $this->flatsOf('dress', $body);

        $bust = $out['measures']['دور سینهٔ دوخته‌شده'] ?? 0.0;

        $this->assertGreaterThan(
            $body['bust'],
            $bust,
            'لباس از سینهٔ بدن تنگ‌تر درآمد.',
        );
        $this->assertLessThan(
            $body['bust'] + 25,
            $bust,
            "دور سینهٔ دوخته‌شده {$bust} است در برابر بدن {$body['bust']}؛ این آزادی به یک پیراهنِ معمولی نمی‌خورد.",
        );
    }

    /** نمای پهلو باید از نمای جلو باریک‌تر باشد؛ تنهٔ آدم از پهلو باریک است. */
    public function test_the_side_view_is_narrower_than_the_front(): void
    {
        $body = Measurements::fromSize('40');
        $out = $this->flatsOf('dress', $body);

        $this->assertNotSame($out['views']['front'], $out['views']['right'], 'نمای پهلو همان نمای جلوست.');

        $side = $out['measures']['ضخامتِ لباس از پهلو'] ?? 0.0;
        $this->assertGreaterThan(5.0, $side, 'ضخامت لباس صفر درآمد.');
        $this->assertLessThan(
            $out['measures']['پهنای پایینِ لباس'] ?? INF,
            $side,
            'لباس از پهلو پهن‌تر از جلو درآمد.',
        );
    }

    /** دامن و شلوار سینه ندارند، پس نباید «دور سینه» هم داشته باشند. */
    public function test_a_lower_body_garment_does_not_claim_a_bust_measurement(): void
    {
        $body = Measurements::fromSize('40');

        foreach (['skirt_a_line', 'pants_straight'] as $key) {
            $out = $this->flatsOf($key, $body);

            $this->assertTrue($out['ok'], "نمای {$key} ساخته نشد.");
            $this->assertArrayNotHasKey('دور سینهٔ دوخته‌شده', $out['measures'], "{$key} دور سینه گزارش کرد.");
            $this->assertArrayHasKey('دور کمرِ دوخته‌شده', $out['measures'], "{$key} دور کمر نداد.");
        }
    }

    /**
     * یک نمونه از هر دسته باید نما بگیرد و هیچ‌کدام نباید خطا بدهد.
     *
     * این‌جا سختگیری روی «همه» نیست — بعضی قطعه‌ها (کلاه، کیف، بند) اصلاً پوستهٔ
     * لباس ندارند و درست است که نما نگیرند. سختگیری روی این است که هیچ‌کدام
     * *نشکنند* و بیشترشان جواب بدهند.
     */
    public function test_a_sample_of_the_whole_catalogue_draws_without_breaking(): void
    {
        $body = Measurements::fromSize('40');
        $keys = array_keys(GeneratorRegistry::options());
        $step = max(1, intdiv(count($keys), 300));

        $broken = [];
        $drawn = 0;
        $tried = 0;

        for ($i = 0; $i < count($keys); $i += $step) {
            $key = $keys[$i];
            $tried++;

            try {
                $out = $this->flatsOf($key, $body);
            } catch (Throwable $error) {
                $broken[] = "{$key}: ".get_class($error).' — '.$error->getMessage();

                continue;
            }

            if ($out['ok']) {
                $drawn++;

                foreach ($out['views'] as $name => $svg) {
                    if (preg_match('/\b(NAN|-?INF)\b/i', $svg)) {
                        $broken[] = "{$key} / {$name}: عدد نامعتبر در نما.";
                    }
                }
            }
        }

        $this->assertSame([], $broken, "نماهایی که شکستند:\n  - ".implode("\n  - ", array_slice($broken, 0, 30)));
        $this->assertGreaterThan(
            $tried * 0.8,
            $drawn,
            "از {$tried} مدل فقط {$drawn} تا نما گرفتند؛ یعنی قطعه‌های شناخته‌نشده زیاد شده‌اند.",
        );
    }

    /* ------------------------------------------------------------------ *
     |  پوستهٔ سه‌بعدی
     * ------------------------------------------------------------------ */

    /**
     * پوستهٔ مانکن باید از همان اعدادِ نمای دوبعدی بیاید.
     *
     * این مهم‌ترین چیزی است که این‌جا سنجیده می‌شود: اگر روزی سه‌بعدی از جای
     * دیگری عدد بگیرد، آنچه روی مانکن دیده می‌شود با آنچه در نمای دوخت دیده
     * شده نمی‌خواند — و همان بود که پیش‌تر خراب بود.
     */
    public function test_the_mannequin_shell_agrees_with_the_flat_views(): void
    {
        $body = Measurements::fromSize('40');
        $pieces = $this->pieces('dress', $body);

        $shell = $this->flats->shell($pieces, $body);
        $flat = $this->flats->flats($pieces, $body);

        $this->assertTrue($shell['ok'], 'پوستهٔ سه‌بعدی ساخته نشد.');
        $this->assertGreaterThan(20, count($shell['rings']), 'پوسته حلقهٔ کافی ندارد.');

        // قدِ پوسته همان قدِ لباس در نمای دوبعدی است
        $this->assertEqualsWithDelta(
            $flat['measures']['قد لباس'],
            $shell['height'],
            0.2,
            'قدِ پوستهٔ سه‌بعدی با قدِ نمای دوبعدی نمی‌خواند.',
        );

        /*
         * پیوندِ درست بین دو نما این است: حلقهٔ سه‌بعدی یک *لوله* است، پس محیطش
         * باید دو برابرِ پهنای تختِ همان ارتفاع باشد — نه خودِ پهنا. یک بار
         * همین دو را با هم مقایسه کردم و آزمون بی‌جهت قرمز شد؛ لوله‌ای که تختش
         * کنی پهن‌تر از قطرش می‌شود.
         */
        $perimeter = function (float $a, float $b): float {
            return M_PI * (3 * ($a + $b) - sqrt((3 * $a + $b) * ($a + 3 * $b)));
        };

        foreach ($shell['rings'] as $ring) {
            $this->assertGreaterThan(0, $ring['rx'], 'حلقه‌ای با پهنای صفر.');
            $this->assertGreaterThan(0, $ring['rz'], 'حلقه‌ای با ضخامت صفر.');
            $this->assertGreaterThanOrEqual($ring['rz'], $ring['rx'], 'مقطع باید پهن‌تر از ضخیم باشد.');
        }

        $last = $shell['rings'][count($shell['rings']) - 1];
        $hem = (float) $flat['measures']['پهنای پایینِ لباس'];

        $this->assertEqualsWithDelta(
            $hem * 2,
            $perimeter($last['rx'], $last['rz']),
            $hem * 0.12,
            'محیطِ حلقهٔ آخر با دم لباس در نمای دوبعدی نمی‌خواند.',
        );
    }

    /** مانکن باید از اندازه‌های همان مشتری بیاید، نه از یک بدنِ ثابت. */
    public function test_the_mannequin_follows_the_customer_measurements(): void
    {
        $small = Measurements::complete(['height' => 145, 'bust' => 76, 'waist' => 60, 'hip' => 84]);
        $large = Measurements::complete(['height' => 178, 'bust' => 124, 'waist' => 112, 'hip' => 128]);

        $a = $this->flats->shell($this->pieces('dress', $small), $small)['body'];
        $b = $this->flats->shell($this->pieces('dress', $large), $large)['body'];

        $this->assertNotEmpty($a);
        $this->assertCount(count($a), $b);

        /*
         * حلقهٔ سرشانه از پهنای سرشانه می‌آید و آن را این‌جا نداده‌ایم، پس هر دو
         * بدن همان پیش‌فرض را می‌گیرند و طبیعی است که فرق نکند. آنچه *باید* فرق
         * کند حلقه‌های سینه و کمر و باسن است، که اندازه‌شان را داده‌ایم.
         */
        foreach ([2 => 'سینه', 3 => 'کمر', 4 => 'باسن'] as $index => $name) {
            $this->assertGreaterThan(
                $a[$index]['rx'],
                $b[$index]['rx'],
                "حلقهٔ {$name} مانکن با بزرگ‌شدن بدن بزرگ نشد.",
            );
        }

        // و مانکنِ بلندتر باید بلندتر هم باشد
        $this->assertGreaterThan(
            $a[count($a) - 1]['y'],
            $b[count($b) - 1]['y'],
            'مانکن با بلندشدن قد بلند نشد.',
        );
    }

    /** لباس باید از بدن گشادتر باشد، وگرنه تنِ مانکن از تویش بیرون می‌زند. */
    public function test_the_garment_sits_outside_the_body_it_was_cut_for(): void
    {
        $body = Measurements::fromSize('40');
        $shell = $this->flats->shell($this->pieces('dress', $body), $body);

        // خط سینهٔ بدن در برابر پوستهٔ لباس روی همان ارتفاع
        $bust = $shell['body'][2] ?? null;
        $this->assertNotNull($bust);

        $at = null;

        foreach ($shell['rings'] as $ring) {
            if ($ring['y'] >= $bust['y'] - $shell['body'][1]['y']) {
                $at = $ring;

                break;
            }
        }

        $this->assertNotNull($at, 'پوسته به خط سینه نرسید.');
        $this->assertGreaterThan(
            $bust['rx'],
            $at['rx'],
            'لباس روی خط سینه از خودِ بدن باریک‌تر است.',
        );
    }

    /** مدلی که پوسته نمی‌گیرد باید بگوید، نه اینکه چیزی الکی بسازد. */
    public function test_a_model_without_a_shell_says_so_and_still_returns_a_mannequin(): void
    {
        $body = Measurements::fromSize('40');
        $shell = $this->flats->shell([], $body);

        $this->assertFalse($shell['ok']);
        $this->assertNotEmpty($shell['notes']);
        $this->assertNotEmpty($shell['body'], 'مانکن باید حتی بدون لباس هم ساخته شود.');
    }
}
