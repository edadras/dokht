<?php

namespace App\Services\Pattern;

use App\Services\Pattern\Generators\ALineSkirtGenerator;
use App\Services\Pattern\Generators\BlazerGenerator;
use App\Services\Pattern\Generators\BodiceBlockGenerator;
use App\Services\Pattern\Generators\ClassicShirtGenerator;
use App\Services\Pattern\Generators\DressGenerator;
use App\Services\Pattern\Generators\PatternGenerator;
use App\Services\Pattern\Generators\PencilSkirtGenerator;
use App\Services\Pattern\Generators\SleeveGenerator;
use App\Services\Pattern\Generators\StraightPantsGenerator;
use App\Services\Pattern\Generators\TShirtGenerator;
use App\Services\Pattern\Generators\WideLegPantsGenerator;
use InvalidArgumentException;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * فهرست تولیدکننده‌های الگو؛ کلید هر ردیف همان مقدار PatternTemplate::$generator است.
 *
 * ردیف‌های ثابت زیر تولیدکننده‌های پایه‌اند. هر تولیدکننده تازه‌ای که در پوشه
 * Generators گذاشته شود و متد ایستای key() داشته باشد خودکار به فهرست می‌آید،
 * پس افزودن مدل تازه هیچ تغییری در این فایل لازم ندارد.
 */
class GeneratorRegistry
{
    /** @var array<string, class-string<PatternGenerator>> */
    public const GENERATORS = [
        'bodice_block' => BodiceBlockGenerator::class,
        'sleeve' => SleeveGenerator::class,
        'skirt_a_line' => ALineSkirtGenerator::class,
        'skirt_pencil' => PencilSkirtGenerator::class,
        'pants_straight' => StraightPantsGenerator::class,
        'pants_wide_leg' => WideLegPantsGenerator::class,
        'shirt_classic' => ClassicShirtGenerator::class,
        'dress' => DressGenerator::class,
        'blazer' => BlazerGenerator::class,
        'tshirt' => TShirtGenerator::class,
    ];

    /** گروه‌های مدل به ترتیب نمایش. */
    public const GROUPS = [
        'bodice' => 'بالاتنه',
        'top' => 'تاپ',
        'shirt' => 'پیراهن و تی‌شرت',
        'swim' => 'مایو',
        'sleeve' => 'آستین',
        'skirt' => 'دامن',
        'pants' => 'پایین‌تنه',
        'garment' => 'لباس کامل',
        'accessory' => 'متعلقات',
    ];

    /** @var array<string, class-string<PatternGenerator>>|null */
    protected static ?array $cache = null;

    /** @return array<string, class-string<PatternGenerator>> */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $generators = static::GENERATORS;
        $directory = app_path('Services/Pattern/Generators');

        if (is_dir($directory)) {
            foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
                $class = static::classFromFile($file);

                if ($class === null || ! is_subclass_of($class, PatternGenerator::class)) {
                    continue;
                }

                if ((new ReflectionClass($class))->isAbstract() || ! method_exists($class, 'key')) {
                    continue;
                }

                $generators[$class::key()] = $class;
            }
        }

        return static::$cache = $generators;
    }

    /** ساخت تولیدکننده از کلید. */
    public static function make(string $key): PatternGenerator
    {
        $class = static::all()[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("تولیدکننده الگوی «{$key}» شناخته نشد.");
        }

        return app($class);
    }

    public static function has(string $key): bool
    {
        return isset(static::all()[$key]);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(static::all());
    }

    /**
     * کلید ⇒ نام فارسی، برای فهرست‌های انتخابی.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::all() as $key => $class) {
            $options[$key] = app($class)->label();
        }

        asort($options);

        return $options;
    }

    /**
     * گروه یک تولیدکننده: bodice | sleeve | skirt | pants | garment | accessory.
     *
     * تولیدکننده‌های تازه می‌توانند متد ایستای group() داشته باشند؛ برای بقیه از
     * روی کلید حدس زده می‌شود.
     */
    public static function groupOf(string $key): string
    {
        $class = static::all()[$key] ?? null;

        if ($class !== null && method_exists($class, 'group')) {
            return $class::group();
        }

        return match (true) {
            str_starts_with($key, 'bodice') => 'bodice',
            str_starts_with($key, 'sleeve') => 'sleeve',
            str_starts_with($key, 'skirt') => 'skirt',
            str_starts_with($key, 'pants'), str_starts_with($key, 'shorts') => 'pants',
            default => 'garment',
        };
    }

    /**
     * تولیدکننده‌های یک گروه: کلید ⇒ نام فارسی.
     *
     * @return array<string, string>
     */
    public static function group(string $group): array
    {
        $options = [];

        foreach (static::options() as $key => $label) {
            if (static::groupOf($key) === $group) {
                $options[$key] = $label;
            }
        }

        return $options;
    }

    /**
     * همه تولیدکننده‌ها، دسته‌بندی‌شده برای رابط کاربری.
     *
     * @return array<string, array{label: string, generators: array<string, string>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (static::GROUPS as $group => $label) {
            $generators = static::group($group);

            if ($generators !== []) {
                $groups[$group] = ['label' => $label, 'generators' => $generators];
            }
        }

        return $groups;
    }

    /** برای آزمون‌ها؛ حافظه موقت را پاک می‌کند. */
    public static function flush(): void
    {
        static::$cache = null;
    }

    /** @return class-string|null */
    protected static function classFromFile(SplFileInfo $file): ?string
    {
        $relative = str_replace(
            [app_path().DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
            ['', '', '\\'],
            $file->getRealPath(),
        );

        $class = 'App\\'.$relative;

        return class_exists($class) ? $class : null;
    }
}
