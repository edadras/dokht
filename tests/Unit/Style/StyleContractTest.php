<?php

namespace Tests\Unit\Style;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleRegistry;
use App\Support\Measurements;
use Tests\TestCase;
use Throwable;

/**
 * قرارداد سبک‌ها.
 *
 * سبک روی قطعه‌های آماده کار می‌کند و چند تعهد دارد که رابط کاربری و ترکیب‌کننده
 * الگو روی آن‌ها حساب باز کرده‌اند:
 *
 *   • supports() برای ورودی نامناسب باید دلیل فارسی بدهد، نه false و نه انگلیسی؛
 *     همین متن است که در کارت انتخاب به کاربر نشان داده می‌شود.
 *   • هر پارامتر باید برچسب فارسی داشته باشد و پیش‌فرضش با کمینه، بیشینه و گام
 *     خودش جور باشد، وگرنه فرم با مقداری باز می‌شود که خودش نمی‌پذیرد.
 *   • apply() باید قطعه‌هایی بدهد که همان آزمون‌های هندسی کاتالوگ را رد کنند.
 *   • اجرای دوباره یک سبک نباید کار را خراب کند.
 *
 * این آزمون هم مانند بازرسی کاتالوگ داده‌محور است و روی هرچه در StyleRegistry
 * باشد اجرا می‌شود.
 */
class StyleContractTest extends TestCase
{
    /** برچسب‌های مجاز لبه. */
    protected const EDGE_TAGS = ['neck', 'shoulder', 'armhole', 'side', 'hem', 'waist', 'default'];

    protected static array $inputs = [];

    /**
     * ورودی‌های نمونه: بالاتنه پایه، بالاتنه با آستین، بالاتنه با دامن و دامن تنها.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function inputs(): array
    {
        if (static::$inputs !== []) {
            return static::$inputs;
        }

        $measurements = Measurements::fromSize('40');

        $build = function (string $key) use ($measurements): array {
            $generator = GeneratorRegistry::make($key);

            return $generator->generate($measurements, [], $generator->defaultParams());
        };

        $bodice = $build('bodice_block');
        $sleeve = $build('sleeve');
        $skirt = $build('skirt_a_line');

        return static::$inputs = [
            'بالاتنه پایه' => $bodice,
            'بالاتنه با آستین' => array_merge($bodice, $sleeve),
            'بالاتنه با دامن' => array_merge($bodice, $skirt),
            'دامن تنها' => $skirt,
        ];
    }

    /** @return array<string, mixed> */
    protected function context(array $params = []): array
    {
        return [
            'measurements' => Measurements::fromSize('40'),
            'ease' => [],
            'params' => $params,
            'garment' => 'dress',
            'notes' => [],
        ];
    }

    protected function isPersian(string $text): bool
    {
        return trim($text) !== '' && preg_match('/\p{Arabic}/u', $text) === 1;
    }

    /** @param  array<int, string>  $problems */
    protected function assertNoProblems(array $problems, string $headline): void
    {
        if ($problems === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $shown = array_slice($problems, 0, 40);
        $more = count($problems) - count($shown);

        $this->fail(
            $headline.' ('.count($problems)." مورد)\n  - ".implode("\n  - ", $shown)
            .($more > 0 ? "\n  … و {$more} مورد دیگر" : ''),
        );
    }

    public function test_the_registry_finds_the_styles_and_groups_them(): void
    {
        $styles = StyleRegistry::all();

        $this->assertGreaterThanOrEqual(40, count($styles), 'فهرست سبک‌ها ناگهان کوچک شده است.');

        $problems = [];
        $grouped = 0;

        foreach ($styles as $key => $class) {
            $style = StyleRegistry::make($key);

            if (! array_key_exists($class::group(), StyleRegistry::GROUPS)) {
                $problems[] = "{$key} در گروه ناشناخته «{$class::group()}» است.";
            }

            if (! $this->isPersian($style->label())) {
                $problems[] = "{$key} نام فارسی ندارد.";
            }

            if (! $this->isPersian($style->description())) {
                $problems[] = "{$key} توضیح فارسی ندارد.";
            }
        }

        foreach (StyleRegistry::grouped() as $group) {
            $grouped += count($group['styles']);
        }

        $this->assertSame(count($styles), $grouped, 'همه سبک‌ها باید در یکی از گروه‌های نمایش بیایند.');
        $this->assertNoProblems($problems, 'نام و گروه سبک‌ها');
    }

    public function test_an_unsuitable_input_is_refused_with_a_persian_reason(): void
    {
        $problems = [];
        $refusals = 0;

        foreach (StyleRegistry::all() as $key => $class) {
            $style = StyleRegistry::make($key);

            // مجموعه خالی برای هیچ سبکی مناسب نیست
            $empty = $style->supports([], $this->context());

            if ($empty === true) {
                $problems[] = "{$key} روی مجموعه خالی هم خودش را قابل اجرا می‌داند.";
            } elseif (! is_string($empty) || ! $this->isPersian($empty)) {
                $problems[] = "{$key} برای مجموعه خالی دلیل فارسی نداد: ".var_export($empty, true);
            } else {
                $refusals++;
            }

            foreach ($this->inputs() as $name => $pieces) {
                $answer = $style->supports($pieces, $this->context());

                if ($answer === true) {
                    continue;
                }

                if (! is_string($answer) || ! $this->isPersian($answer)) {
                    $problems[] = "{$key} روی «{$name}» پاسخ ".var_export($answer, true).' داد؛ باید دلیل فارسی باشد.';

                    continue;
                }

                $refusals++;
            }
        }

        $this->assertGreaterThan(50, $refusals, 'هیچ سبکی ورودی نامناسب را رد نکرد؛ آزمون کار نمی‌کند.');
        $this->assertNoProblems($problems, 'پیام رد کردن سبک‌ها');
    }

    public function test_every_parameter_has_a_label_and_a_default_that_fits_its_own_range(): void
    {
        $problems = [];
        $fields = 0;

        foreach (StyleRegistry::all() as $key => $class) {
            foreach (StyleRegistry::make($key)->paramsSchema() as $name => $field) {
                $fields++;
                $where = "{$key}.{$name}";

                if (! isset($field['label']) || ! is_string($field['label']) || ! $this->isPersian($field['label'])) {
                    $problems[] = "{$where} برچسب فارسی ندارد.";
                }

                if (! array_key_exists('default', $field)) {
                    $problems[] = "{$where} مقدار پیش‌فرض ندارد.";

                    continue;
                }

                $default = $field['default'];

                if (isset($field['options']) && is_array($field['options'])) {
                    if (! array_key_exists($default, $field['options']) && ! in_array($default, $field['options'], true)) {
                        $problems[] = "{$where} پیش‌فرض ".var_export($default, true).' در فهرست گزینه‌ها نیست.';
                    }

                    continue;
                }

                if (! is_numeric($default)) {
                    continue; // پارامتر کلیدی یا روشن/خاموش
                }

                $value = (float) $default;

                if (isset($field['min']) && $value < ((float) $field['min']) - 1e-9) {
                    $problems[] = sprintf('%s پیش‌فرض %s از کمینه %s کمتر است.', $where, $value, $field['min']);
                }

                if (isset($field['max']) && $value > ((float) $field['max']) + 1e-9) {
                    $problems[] = sprintf('%s پیش‌فرض %s از بیشینه %s بیشتر است.', $where, $value, $field['max']);
                }

                if (isset($field['step'], $field['min']) && ((float) $field['step']) > 0) {
                    $steps = ($value - ((float) $field['min'])) / ((float) $field['step']);

                    if (abs($steps - round($steps)) > 1e-6) {
                        $problems[] = sprintf(
                            '%s پیش‌فرض %s روی گام %s از %s نمی‌نشیند؛ فرم با مقداری باز می‌شود که خودش نمی‌پذیرد.',
                            $where, $value, $field['step'], $field['min'],
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(200, $fields, 'هیچ پارامتری برای سنجیدن پیدا نشد.');
        $this->assertNoProblems($problems, 'توضیح پارامترهای سبک');
    }

    public function test_applying_a_style_keeps_the_pieces_sound(): void
    {
        $problems = [];
        $applied = 0;

        foreach (StyleRegistry::all() as $key => $class) {
            $style = StyleRegistry::make($key);
            $params = array_map(fn (array $field) => $field['default'], $style->paramsSchema());

            foreach ($this->inputs() as $name => $pieces) {
                $context = $this->context($params);

                if ($style->supports($pieces, $context) !== true) {
                    continue;
                }

                try {
                    $result = $style->apply($pieces, $context);
                } catch (Throwable $error) {
                    $problems[] = "{$key} روی «{$name}» شکست: ".get_class($error).' — '.$error->getMessage();

                    continue;
                }

                $applied++;

                if (! isset($result['pieces']) || ! is_array($result['pieces']) || $result['pieces'] === []) {
                    $problems[] = "{$key} روی «{$name}» هیچ قطعه‌ای برنگرداند.";

                    continue;
                }

                foreach ($this->geometryProblems($result['pieces'], "{$key}|{$name}") as $problem) {
                    $problems[] = $problem;
                }
            }
        }

        $this->assertGreaterThan(60, $applied, 'هیچ سبکی روی بالاتنه پایه اجرا نشد.');
        $this->assertNoProblems($problems, 'هندسه قطعه‌ها پس از اجرای سبک');
    }

    public function test_applying_a_style_twice_does_not_corrupt_the_result(): void
    {
        $problems = [];
        $twice = 0;

        foreach (StyleRegistry::all() as $key => $class) {
            $style = StyleRegistry::make($key);
            $params = array_map(fn (array $field) => $field['default'], $style->paramsSchema());
            $pieces = $this->inputs()['بالاتنه با آستین'];
            $context = $this->context($params);

            if ($style->supports($pieces, $context) !== true) {
                continue;
            }

            try {
                $once = $style->apply($pieces, $context);
            } catch (Throwable) {
                continue; // اجرای نخست جای دیگری گزارش شده است
            }

            $again = $style->supports($once['pieces'], $context);

            if ($again !== true) {
                // سبکی که بار دوم خودش را رد کند دقیقاً همان رفتار درست است
                if (! is_string($again) || ! $this->isPersian($again)) {
                    $problems[] = "{$key} بار دوم پاسخ ".var_export($again, true).' داد؛ باید دلیل فارسی باشد.';
                }

                continue;
            }

            try {
                $result = $style->apply($once['pieces'], $context);
            } catch (Throwable $error) {
                $problems[] = "{$key} در اجرای دوم شکست: ".get_class($error).' — '.$error->getMessage();

                continue;
            }

            $twice++;

            foreach ($this->geometryProblems($result['pieces'], "{$key}|اجرای دوم") as $problem) {
                $problems[] = $problem;
            }

            $codes = array_column($result['pieces'], 'code');

            if (count($codes) !== count(array_unique($codes))) {
                $duplicates = implode('، ', array_keys(array_filter(array_count_values($codes), fn ($n) => $n > 1)));
                $problems[] = "{$key} با اجرای دوباره قطعه تکراری ساخت: {$duplicates}؛ سبک باید بار دوم خودش را رد کند یا قطعه پیشین را جایگزین کند.";
            }
        }

        $this->assertGreaterThan(0, $twice, 'هیچ سبکی دو بار اجرا نشد.');
        $this->assertNoProblems($problems, 'اجرای دوباره یک سبک');
    }

    /**
     * همان بررسی‌های هندسی بازرسی کاتالوگ، روی خروجی یک سبک.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, string>
     */
    protected function geometryProblems(array $pieces, string $where): array
    {
        $problems = [];

        foreach ($pieces as $piece) {
            $code = $piece['code'] ?? '?';
            $outline = array_values($piece['outline'] ?? []);
            $count = count($outline);

            if ($count < 3) {
                $problems[] = "{$where}|{$code} مسیر تنها {$count} نقطه دارد.";

                continue;
            }

            foreach ($outline as $index => $point) {
                foreach (['x', 'y', 'cx', 'cy'] as $axis) {
                    if (isset($point[$axis]) && ! is_finite((float) $point[$axis])) {
                        $problems[] = "{$where}|{$code} نقطه {$index} مختصات {$axis} نامعتبر دارد.";
                    }
                }
            }

            if (Geometry::selfIntersects($outline)) {
                $problems[] = "{$where}|{$code} مسیر قطعه خودش را قطع می‌کند.";
            }

            $area = Geometry::area($outline);

            if ($area < 1.0) {
                $problems[] = "{$where}|{$code} مساحت {$area} سانتی‌متر مربع است.";
            }

            $tags = $piece['meta']['edges'] ?? null;

            if (! is_array($tags)) {
                $problems[] = "{$where}|{$code} برچسب لبه ندارد.";
            } elseif (count($tags) !== $count) {
                $problems[] = "{$where}|{$code} ".count($tags)." برچسب برای {$count} لبه دارد.";
            } else {
                foreach ($tags as $index => $tag) {
                    if (! in_array($tag, static::EDGE_TAGS, true)) {
                        $problems[] = "{$where}|{$code} لبه {$index} برچسب ناشناخته «".(is_string($tag) ? $tag : gettype($tag)).'» دارد.';
                    }
                }
            }

            if (! isset($piece['grainline']['from']['x'], $piece['grainline']['to']['x'])) {
                $problems[] = "{$where}|{$code} راستای پارچه ندارد.";
            }
        }

        return $problems;
    }
}
