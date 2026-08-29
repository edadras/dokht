<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\VariantAware;
use App\Support\Measurements;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * خانواده‌های جدولی.
 *
 * یک کلاس که چند صد مدل می‌دهد، اگر بی‌دقت نوشته شود چند صد مدلِ *یکسان*
 * می‌دهد و کسی نمی‌فهمد. این آزمون همان را می‌پاید: هر ردیف باید کلیدِ خودش،
 * نامِ خودش و پارامترهای خودش را داشته باشد، و رجیستری باید هر ردیف را جدا
 * ببیند.
 */
class VariantCatalogTest extends TestCase
{
    /** @return array<int, class-string> */
    protected function families(): array
    {
        $out = [];

        foreach (Finder::create()->files()->in(app_path('Services/Pattern/Generators'))->name('*.php') as $file) {
            $class = 'App\\'.str_replace(
                [app_path().DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getRealPath(),
            );

            if (! class_exists($class) || ! is_subclass_of($class, VariantAware::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $out[] = $class;
        }

        return $out;
    }

    public function test_there_are_variant_families(): void
    {
        $this->assertNotEmpty($this->families(), 'هیچ خانوادهٔ جدولی پیدا نشد.');
    }

    public function test_every_row_has_its_own_key_and_title(): void
    {
        $problems = [];

        foreach ($this->families() as $class) {
            $titles = [];

            foreach ($class::variants() as $key => $spec) {
                if (! is_string($key) || $key === '') {
                    $problems[] = $class.' ردیفی با کلید خالی دارد.';

                    continue;
                }

                $title = (string) ($spec['title'] ?? '');

                if ($title === '') {
                    $problems[] = $class.'|'.$key.' نام ندارد.';

                    continue;
                }

                if (isset($titles[$title])) {
                    $problems[] = $class.': «'.$title.'» هم برای '.$titles[$title].' است هم برای '.$key.'.';
                }

                $titles[$title] = $key;
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_the_registry_sees_every_row_as_its_own_model(): void
    {
        $problems = [];
        $rows = 0;

        foreach ($this->families() as $class) {
            foreach (array_keys($class::variants()) as $key) {
                $rows++;

                if (! GeneratorRegistry::has($key)) {
                    $problems[] = $key.' در رجیستری نیست.';

                    continue;
                }

                $generator = GeneratorRegistry::make($key);

                if (! $generator instanceof VariantAware) {
                    $problems[] = $key.' روی ردیف تنظیم نشد.';

                    continue;
                }

                if ($generator->variantKey() !== $key) {
                    $problems[] = $key.' روی ردیف «'.$generator->variantKey().'» تنظیم شد.';
                }
            }
        }

        $this->assertSame([], array_slice($problems, 0, 20));
        $this->assertGreaterThan(500, $rows, 'خانواده‌های جدولی باید صدها مدل بدهند.');
    }

    /**
     * دو ردیفِ یک خانواده نباید *الگوی* یکسان بدهند.
     *
     * مقایسه روی خودِ قطعه‌هاست، نه روی نام و پارامتر. علتش یک تلهٔ واقعی است:
     * محوری به جدول اضافه شد که نامش در عنوان می‌آمد و در پارامترها هم بود، ولی
     * درفتِ پایه اصلاً آن پارامتر را نمی‌شناخت — پس همهٔ ردیف‌هایش یک الگو
     * می‌دادند با دو نامِ متفاوت. با مقایسهٔ نام و پارامتر این از دست می‌رفت،
     * چون آن دو *واقعاً* فرق داشتند؛ آن‌چه فرق نمی‌کرد الگو بود.
     *
     * چند ردیفِ پراکنده از هر خانواده کافی است: اگر محوری بی‌اثر باشد، در همان
     * چند ردیفِ نخست هم خودش را نشان می‌دهد.
     */
    public function test_rows_of_a_family_really_produce_different_patterns(): void
    {
        $problems = [];
        $body = Measurements::complete(Measurements::fromSize('40'));

        foreach ($this->families() as $class) {
            $keys = array_keys($class::variants());
            $seen = [];

            foreach (array_slice($keys, 0, 16) as $key) {
                $generator = GeneratorRegistry::make($key);

                try {
                    $pieces = $generator->generate($body, [], $generator->defaultParams());
                } catch (\Throwable $error) {
                    $problems[] = $class.'|'.$key.' ساخته نشد: '.$error->getMessage();

                    continue;
                }

                $signature = md5(json_encode(array_map(
                    fn (array $piece) => [$piece['code'] ?? '', $piece['outline'] ?? []],
                    $pieces,
                )));

                if (isset($seen[$signature])) {
                    $problems[] = $class.': الگوی «'.$key.'» با «'.$seen[$signature].'» مو نمی‌زند.';
                }

                $seen[$signature] = $key;
            }
        }

        $this->assertSame([], array_slice($problems, 0, 20));
    }
}
