<?php

namespace App\Services\Pattern\Style;

use InvalidArgumentException;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * فهرست سبک‌های در دسترس.
 *
 * سبک‌ها با پویش پوشه‌های زیر Style پیدا می‌شوند، پس افزودن یک سبک تازه هیچ
 * تغییری در این کلاس یا در جای دیگری لازم ندارد؛ فقط یک فایل تازه کنار بقیه.
 */
class StyleRegistry
{
    /** گروه‌ها به ترتیب نمایش، با نام فارسی. */
    public const GROUPS = [
        'neckline' => 'خط یقه',
        'collar' => 'یقه',
        'sleeve' => 'آستین',
        'hem' => 'لبه و دامنه',
        'fullness' => 'چین و گشادی',
        'seam' => 'برش و درز',
        'pocket' => 'جیب',
        'closure' => 'بست و دکمه',
        'detail' => 'جزئیات',
    ];

    /** @var array<string, class-string<StyleModifier>>|null */
    protected static ?array $cache = null;

    /** @return array<string, class-string<StyleModifier>> */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $found = [];
        $directory = app_path('Services/Pattern/Style');

        if (! is_dir($directory)) {
            return static::$cache = [];
        }

        foreach (Finder::create()->files()->in($directory)->name('*.php')->depth('>= 1') as $file) {
            $class = static::classFromFile($file);

            if ($class === null || ! is_subclass_of($class, StyleModifier::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $found[$class::key()] = $class;
        }

        ksort($found);

        return static::$cache = $found;
    }

    public static function make(string $key): StyleModifier
    {
        $class = static::all()[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("سبک «{$key}» شناخته نشد.");
        }

        return app($class);
    }

    public static function has(string $key): bool
    {
        return isset(static::all()[$key]);
    }

    /**
     * سبک‌های یک گروه.
     *
     * @return array<string, StyleModifier>
     */
    public static function group(string $group): array
    {
        $styles = [];

        foreach (static::all() as $key => $class) {
            if ($class::group() === $group) {
                $styles[$key] = app($class);
            }
        }

        return $styles;
    }

    /**
     * همه سبک‌ها، دسته‌بندی‌شده برای رابط کاربری.
     *
     * @return array<string, array{label: string, styles: array<string, StyleModifier>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (static::GROUPS as $group => $label) {
            $styles = static::group($group);

            if ($styles !== []) {
                $groups[$group] = ['label' => $label, 'styles' => $styles];
            }
        }

        return $groups;
    }

    /** کلید ⇒ نام فارسی، برای فهرست‌های انتخابی. */
    public static function options(?string $group = null): array
    {
        $styles = $group ? static::group($group) : array_map(fn ($class) => app($class), static::all());

        return array_map(fn (StyleModifier $style) => $style->label(), $styles);
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
