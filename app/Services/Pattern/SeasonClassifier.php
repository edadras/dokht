<?php

namespace App\Services\Pattern;

use Throwable;

/**
 * فصلِ هر مدلِ کاتالوگ.
 *
 * «لباسِ فصلی» یک دستهٔ تازه از الگو نیست؛ همان الگوهای موجود است که از زاویهٔ
 * دیگری فهرست می‌شود. پس این‌جا هیچ مدلی ساخته نمی‌شود و هیچ فهرستِ دستی هم
 * نوشته نمی‌شود — چون فهرستِ دستی برای چهارصد مدل، روزِ بعد کهنه است.
 *
 * فصل از خودِ الگو **خوانده** می‌شود، از سه عدد و دو نشانه:
 *
 *   بلندیِ آستین   پارامترِ اعلام‌شدهٔ خودِ مدل؛ صفر یعنی بی‌آستین
 *   پوشش           قدِ اعلام‌شدهٔ لباس
 *   لایه            آستر و لایی — لباسِ چندلایه لباسِ سرماست
 *   کشباف/کشی     پارچهٔ اعلام‌شدهٔ خودِ الگو
 *   گروه            جایی که گروه، خودش شناسنامهٔ فصل است (مایو، پالتو)
 *
 * این‌ها همه از paramsSchema خوانده می‌شوند، نه از ساختنِ الگو. نخستین نسخه
 * برای هر مدل یک الگوی کامل می‌ساخت تا بلندیِ آستین را از روی قطعه اندازه
 * بگیرد؛ با پانصد مدل قابلِ تحمل بود و با دو هزار و پانصد مدل، بازکردنِ صفحهٔ
 * کارگاه یازده ثانیه طول می‌کشید. عددی که لازم داریم را خودِ مدل *اعلام* کرده
 * است؛ ساختنِ کلِ الگو برای خواندنِ همان عدد، کارِ اضافه بود.
 *
 * قاعده، به همین ترتیب خوانده می‌شود و اولین سطری که بخورد برنده است:
 *
 *   ۱. مایو و ساحلی            → تابستان
 *   ۲. کت و پالتو و کاپشن      → پاییز و زمستان
 *   ۳. آستین ۵۰+ و چندلایه     → پاییز و زمستان
 *   ۴. آستین ۵۰+               → پاییز، زمستان و بهار
 *   ۵. آستین ۲۶ تا ۴۹          → بهار و پاییز
 *   ۶. آستین تا ۲۵ یا بی‌آستین → تابستان و بهار
 *
 * متعلقات از این قاعده بیرون‌اند: کلاهِ آفتابی و کلاهِ پشمی هر دو «کلاه»اند و
 * آستین ندارند، پس فصلشان از دلِ الگو درنمی‌آید. آن‌ها هر چهار فصل گرفته
 * می‌شوند مگر خودشان چیز دیگری بگویند — و این را صادقانه اعلام می‌کنیم، نه
 * اینکه حدس را به‌جای اندازه بنشانیم.
 */
final class SeasonClassifier
{
    /** فصل‌ها با نام فارسی. */
    public const SEASONS = [
        'spring' => 'بهار',
        'summer' => 'تابستان',
        'autumn' => 'پاییز',
        'winter' => 'زمستان',
    ];

    /** بدنِ مرجعِ سنجش؛ فصل نباید با سایز عوض شود. */
    protected const REFERENCE = '40';

    /** آستینی که بلندتر از این باشد، آستینِ بلند شمرده می‌شود (سانتی‌متر). */
    protected const LONG_SLEEVE = 50.0;

    /** آستینِ کوتاه‌تر از این، آستینِ تابستانی است (سانتی‌متر). */
    protected const SHORT_SLEEVE = 26.0;

    /** @var array<string, array{seasons: array<int, string>, why: string}> */
    protected static array $cache = [];

    /**
     * فصل‌های یک مدل.
     *
     * @return array<int, string>
     */
    public static function of(string $key): array
    {
        return static::read($key)['seasons'];
    }

    /** چرا این فصل‌ها — به فارسی، برای نمایش کنار مدل. */
    public static function why(string $key): string
    {
        return static::read($key)['why'];
    }

    /**
     * همهٔ مدل‌های یک فصل.
     *
     * @return array<int, string>
     */
    public static function inSeason(string $season): array
    {
        $keys = [];

        foreach (array_keys(GeneratorRegistry::all()) as $key) {
            if (in_array($season, static::of($key), true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** حافظهٔ موقت را پاک می‌کند (برای آزمون). */
    public static function flush(): void
    {
        static::$cache = [];
    }

    /**
     * @return array{seasons: array<int, string>, why: string}
     */
    protected static function read(string $key): array
    {
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        return static::$cache[$key] = static::classify($key);
    }

    /**
     * @return array{seasons: array<int, string>, why: string}
     */
    protected static function classify(string $key): array
    {
        $group = GeneratorRegistry::groupOf($key);

        if (in_array($group, ['swim', 'beach'], true)) {
            return ['seasons' => ['summer'], 'why' => 'مایو و لباس ساحلی فقط برای تابستان بریده می‌شود.'];
        }

        if (in_array($group, ['outerwear', 'suit'], true)) {
            return ['seasons' => ['autumn', 'winter'], 'why' => 'لایهٔ رویی است و روی لباس دیگر پوشیده می‌شود.'];
        }

        $facts = static::measure($key);

        if ($facts === null) {
            return [
                'seasons' => array_keys(static::SEASONS),
                'why' => 'این مدل ساخته نشد، پس فصلش اندازه گرفته نشده و هر چهار فصل فهرست شده است.',
            ];
        }

        if ($group === 'accessory') {
            return [
                'seasons' => array_keys(static::SEASONS),
                'why' => 'متعلقات آستین و پوشش ندارند، پس فصلشان از الگو درنمی‌آید؛ هر چهار فصل فهرست شده است.',
            ];
        }

        $sleeve = $facts['sleeve'];
        $layers = $facts['layers'];

        if ($sleeve >= static::LONG_SLEEVE && $layers >= 1) {
            return [
                'seasons' => ['autumn', 'winter'],
                'why' => 'آستین بلند ('.round($sleeve).' سانتی‌متر) و بیش از یک لایه.',
            ];
        }

        if ($sleeve >= static::LONG_SLEEVE) {
            return [
                'seasons' => ['spring', 'autumn', 'winter'],
                'why' => 'آستین بلند ('.round($sleeve).' سانتی‌متر) و یک‌لایه.',
            ];
        }

        if ($sleeve >= static::SHORT_SLEEVE) {
            return [
                'seasons' => ['spring', 'autumn'],
                'why' => 'آستین میانه ('.round($sleeve).' سانتی‌متر).',
            ];
        }

        return [
            'seasons' => ['spring', 'summer'],
            'why' => $sleeve > 0.5
                ? 'آستین کوتاه ('.round($sleeve).' سانتی‌متر).'
                : 'بی‌آستین.',
        ];
    }

    /**
     * سه عددی که فصل از آن‌ها درمی‌آید، از روی *اعلامِ* خودِ مدل.
     *
     * @return array{sleeve: float, cover: float, layers: int}|null
     */
    protected static function measure(string $key): ?array
    {
        try {
            $generator = GeneratorRegistry::make($key);
            $schema = $generator->paramsSchema();
            $defaults = $generator->defaultParams();
        } catch (Throwable) {
            return null;
        }

        $read = function (array $names) use ($schema, $defaults): ?float {
            foreach ($names as $name) {
                $value = $defaults[$name] ?? ($schema[$name]['default'] ?? null);

                if (is_numeric($value)) {
                    return (float) $value;
                }
            }

            return null;
        };

        // «بی‌آستین» را باید از «آستینِ اعلام‌نشده» جدا کرد: مدلی که سبکِ آستینش
        // none است واقعاً آستین ندارد، ولی مدلی که اصلاً پارامترِ آستین ندارد
        // (دامن، شلوار) هم بی‌آستین است و هر دو تابستانی شمرده می‌شوند
        $style = $defaults['sleeve_style'] ?? ($schema['sleeve_style']['default'] ?? null);
        $sleeve = $style === 'none' ? 0.0 : ($read(['sleeve_length']) ?? 0.0);

        $lining = $defaults['lining'] ?? ($schema['lining']['default'] ?? null);
        $layers = 0;

        if (is_string($lining) && $lining !== 'none') {
            $layers++;
        }

        if ($lining === true) {
            $layers++;
        }

        return [
            'sleeve' => round($sleeve, 1),
            'cover' => round($read(['length', 'skirt_length', 'body_length']) ?? 0.0, 1),
            'layers' => $layers,
        ];
    }
}
