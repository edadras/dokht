<?php

namespace Tests\Unit;

use App\Services\Pattern\GeneratorRegistry;
use App\Services\Pattern\Generators\PatternGenerator;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * دو مدل نباید یک کلید داشته باشند.
 *
 * رجیستری پوشه را می‌خواند و هر کلاس را زیر کلیدِ خودش می‌نشاند. اگر دو کلاس یک
 * کلید بگویند، دومی بی‌صدا جای اولی را می‌گیرد و یک مدلِ کامل از کاتالوگ
 * ناپدید می‌شود — بی خطا، بی هشدار، فقط یک ردیفِ کمتر در فهرست. همین یک بار
 * پیش آمد و تا وقتی شمارهٔ کل را نشمردیم دیده نشد.
 */
class GeneratorRegistryUniquenessTest extends TestCase
{
    public function test_no_two_generators_claim_the_same_key(): void
    {
        $owners = [];
        $clashes = [];

        foreach (Finder::create()->files()->in(app_path('Services/Pattern/Generators'))->name('*.php') as $file) {
            $class = 'App\\'.str_replace(
                [app_path().DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getRealPath(),
            );

            if (! class_exists($class) || ! is_subclass_of($class, PatternGenerator::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract() || ! method_exists($class, 'key')) {
                continue;
            }

            $key = $class::key();

            if (isset($owners[$key])) {
                $clashes[] = $key.' → '.$owners[$key].' و '.$class;

                continue;
            }

            $owners[$key] = $class;
        }

        $this->assertSame([], $clashes, 'دو مدل یک کلید گرفته‌اند؛ یکی از آن‌ها در فهرست ناپدید می‌شود.');

        // رجیستری کلیدهای دستیِ GENERATORS را هم دارد، پس شمارش برابر نیست؛
        // آن‌چه باید برقرار باشد این است که هیچ کلیدِ روی دیسک گم نشده باشد
        $missing = array_values(array_diff(array_keys($owners), array_keys(GeneratorRegistry::all())));

        $this->assertSame([], $missing, 'مدلی روی دیسک هست که رجیستری آن را پیدا نکرده است.');
    }
}
