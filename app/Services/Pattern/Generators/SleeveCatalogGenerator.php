<?php

namespace App\Services\Pattern\Generators;

/**
 * پایه مشترک همه آستین‌های فهرست آستین.
 *
 * هر آستین تازه فقط key، label، paramsSchema و sleeveSpec خودش را می‌نویسد؛
 * پیاده‌کردن سرآستین روی حلقه، نشانه‌ها، خط بازو و آرنج و یادداشت فارسی از این
 * کلاس می‌آید. کلاس‌های این خانواده خودکار در فهرست مدل‌ها (گروه «آستین») دیده
 * می‌شوند و نیازی به ثبت دستی ندارند.
 */
abstract class SleeveCatalogGenerator extends BaseGenerator
{
    use SleeveDraftSupport;

    /** گروه نمایش در فهرست مدل‌ها. */
    public static function group(): string
    {
        return 'sleeve';
    }

    /**
     * ویژگی‌های شکل این آستین (پف، گشادی، ساسون آرنج و مانند این‌ها).
     *
     * @param  array<string, float>  $m
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    abstract protected function sleeveSpec(array $m, array $ease, array $params): array;

    public function generate(array $measurements, array $ease, array $params): array
    {
        $spec = $this->sleeveSpec($measurements, $ease, $params);
        $frame = $this->sleeveFrame($measurements, $ease, $params, $spec);

        return $this->finish($this->sleevePieces($frame, $measurements, $ease, $params, $spec));
    }

    /**
     * قطعه‌های این آستین؛ مدل‌های چندتکه این متد را بازنویسی می‌کنند.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sleevePieces(array $frame, array $m, array $ease, array $params, array $spec): array
    {
        return [$this->sleevePiece($frame, $spec)];
    }
}
