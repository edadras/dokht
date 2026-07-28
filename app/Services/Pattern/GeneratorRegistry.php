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

/** فهرست تولیدکننده‌های الگو؛ کلید هر ردیف همان مقدار PatternTemplate::$generator است. */
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

    /** ساخت تولیدکننده از کلید. */
    public static function make(string $key): PatternGenerator
    {
        $class = static::GENERATORS[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("تولیدکننده الگوی «{$key}» شناخته نشد.");
        }

        return app($class);
    }

    public static function has(string $key): bool
    {
        return isset(static::GENERATORS[$key]);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(static::GENERATORS);
    }

    /**
     * کلید ⇒ نام فارسی، برای فهرست‌های انتخابی.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::GENERATORS as $key => $class) {
            $options[$key] = app($class)->label();
        }

        return $options;
    }
}
