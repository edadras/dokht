<?php

namespace App\Services\Vision;

/**
 * نقاب دودویی شکل لباس (سیلوئت).
 *
 * یک شبکه ساده از صفر و یک است: ۱ یعنی «این نقطه پارچه است» و ۰ یعنی زمینه.
 * هم عکس (بعد از جداسازی از زمینه) و هم طرح دستی (بعد از پرکردن چندضلعی) به
 * همین ساختار تبدیل می‌شوند تا استخراج ویژگی‌ها دقیقاً یک مسیر داشته باشد.
 *
 * قرارداد مختصات: x به راست، y به پایین، مبدأ گوشه بالا-چپ.
 */
final class Silhouette
{
    /** @param  array<int, int>  $bits  آرایه تخت به طول width*height */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly array $bits,
    ) {}

    /** نقاب خالی. */
    public static function blank(int $width, int $height): self
    {
        return new self($width, $height, array_fill(0, max(1, $width * $height), 0));
    }

    public function at(int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return false;
        }

        return $this->bits[$y * $this->width + $x] === 1;
    }

    /** شمار نقطه‌های پارچه. */
    public function area(): int
    {
        $sum = 0;

        foreach ($this->bits as $bit) {
            $sum += $bit;
        }

        return $sum;
    }

    /** نسبت پرشدگی کل قاب. */
    public function coverage(): float
    {
        $cells = $this->width * $this->height;

        return $cells > 0 ? $this->area() / $cells : 0.0;
    }

    /**
     * کادر دربرگیرنده شکل.
     *
     * @return array{min_x: int, min_y: int, max_x: int, max_y: int, width: int, height: int}|null
     */
    public function bounds(): ?array
    {
        $minX = $this->width;
        $minY = $this->height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $this->height; $y++) {
            $row = $y * $this->width;

            for ($x = 0; $x < $this->width; $x++) {
                if ($this->bits[$row + $x] !== 1) {
                    continue;
                }

                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < 0) {
            return null;
        }

        return [
            'min_x' => $minX,
            'min_y' => $minY,
            'max_x' => $maxX,
            'max_y' => $maxY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
        ];
    }

    /**
     * بازه‌های پیوسته پارچه در یک سطر.
     *
     * برای تشخیص «دو پاچه» لازم است: سطری که دو بازه جدا دارد یعنی شکل در آن
     * ارتفاع به دو شاخه تقسیم شده است.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function runs(int $y): array
    {
        if ($y < 0 || $y >= $this->height) {
            return [];
        }

        $runs = [];
        $start = null;
        $row = $y * $this->width;

        for ($x = 0; $x < $this->width; $x++) {
            $on = $this->bits[$row + $x] === 1;

            if ($on && $start === null) {
                $start = $x;
            } elseif (! $on && $start !== null) {
                $runs[] = [$start, $x - 1];
                $start = null;
            }
        }

        if ($start !== null) {
            $runs[] = [$start, $this->width - 1];
        }

        return $runs;
    }

    /** فرسایش: هر نقطه‌ای که همسایه خالی داشته باشد حذف می‌شود (نقطه‌های ریز پاک می‌شوند). */
    public function erode(): self
    {
        return $this->morph(false);
    }

    /** گسترش: هر نقطه خالی که همسایه پر داشته باشد پر می‌شود (سوراخ‌های ریز بسته می‌شوند). */
    public function dilate(): self
    {
        return $this->morph(true);
    }

    /** باز کردن: پاک‌کردن نویز نقطه‌ای بدون تغییر شکل کلی. */
    public function opened(): self
    {
        return $this->erode()->dilate();
    }

    /** بستن: پرکردن شکاف‌های باریک (مثل خط قلم ناپیوسته در طرح دستی). */
    public function closed(): self
    {
        return $this->dilate()->erode();
    }

    /** بزرگ‌ترین تکه به‌هم‌پیوسته؛ بقیه (سایه، برچسب، لکه) دور ریخته می‌شود. */
    public function largestComponent(): self
    {
        $size = $this->width * $this->height;
        $seen = array_fill(0, max(1, $size), false);
        $best = [];

        for ($index = 0; $index < $size; $index++) {
            if ($seen[$index] || $this->bits[$index] !== 1) {
                continue;
            }

            $component = [];
            $stack = [$index];
            $seen[$index] = true;

            while ($stack !== []) {
                $current = array_pop($stack);
                $component[] = $current;

                $x = $current % $this->width;
                $y = intdiv($current, $this->width);

                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;

                    if ($nx < 0 || $ny < 0 || $nx >= $this->width || $ny >= $this->height) {
                        continue;
                    }

                    $neighbour = $ny * $this->width + $nx;

                    if ($seen[$neighbour] || $this->bits[$neighbour] !== 1) {
                        continue;
                    }

                    $seen[$neighbour] = true;
                    $stack[] = $neighbour;
                }
            }

            if (count($component) > count($best)) {
                $best = $component;
            }
        }

        $bits = array_fill(0, max(1, $size), 0);

        foreach ($best as $index) {
            $bits[$index] = 1;
        }

        return new self($this->width, $this->height, $bits);
    }

    /**
     * پرکردن سوراخ‌های داخلی.
     *
     * از لبه قاب سیل می‌کنیم؛ هر نقطه خالی که از لبه دیده نشود سوراخ داخلی است
     * (مثلاً وسط یک طرح دستی که فقط دورش کشیده شده).
     */
    public function fillHoles(): self
    {
        $size = $this->width * $this->height;
        $outside = array_fill(0, max(1, $size), false);
        $stack = [];

        for ($x = 0; $x < $this->width; $x++) {
            foreach ([0, $this->height - 1] as $y) {
                $index = $y * $this->width + $x;

                if (! $outside[$index] && $this->bits[$index] === 0) {
                    $outside[$index] = true;
                    $stack[] = $index;
                }
            }
        }

        for ($y = 0; $y < $this->height; $y++) {
            foreach ([0, $this->width - 1] as $x) {
                $index = $y * $this->width + $x;

                if (! $outside[$index] && $this->bits[$index] === 0) {
                    $outside[$index] = true;
                    $stack[] = $index;
                }
            }
        }

        while ($stack !== []) {
            $current = array_pop($stack);
            $x = $current % $this->width;
            $y = intdiv($current, $this->width);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $this->width || $ny >= $this->height) {
                    continue;
                }

                $neighbour = $ny * $this->width + $nx;

                if ($outside[$neighbour] || $this->bits[$neighbour] === 1) {
                    continue;
                }

                $outside[$neighbour] = true;
                $stack[] = $neighbour;
            }
        }

        $bits = $this->bits;

        for ($index = 0; $index < $size; $index++) {
            if (! $outside[$index]) {
                $bits[$index] = 1;
            }
        }

        return new self($this->width, $this->height, $bits);
    }

    /**
     * قرینگی نسبت به محور عمودی وسط کادر (۰ تا ۱).
     *
     * لباس دوخته‌شده تقریباً قرینه است؛ قرینگی پایین یعنی عکس از زاویه گرفته شده
     * یا شکل کج است و باید به تشخیص کمتر اعتماد کرد.
     */
    public function symmetry(): float
    {
        $bounds = $this->bounds();

        if ($bounds === null) {
            return 0.0;
        }

        $intersection = 0;
        $union = 0;

        for ($y = $bounds['min_y']; $y <= $bounds['max_y']; $y++) {
            for ($x = $bounds['min_x']; $x <= $bounds['max_x']; $x++) {
                $mirrored = $bounds['min_x'] + $bounds['max_x'] - $x;
                $a = $this->at($x, $y);
                $b = $this->at($mirrored, $y);

                if ($a && $b) {
                    $intersection++;
                }

                if ($a || $b) {
                    $union++;
                }
            }
        }

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * کدام لبه‌های قاب را شکل لمس می‌کند.
     *
     * @return array<int, string>
     */
    public function touchedEdges(): array
    {
        $bounds = $this->bounds();

        if ($bounds === null) {
            return [];
        }

        $edges = [];

        if ($bounds['min_y'] <= 0) {
            $edges[] = 'top';
        }

        if ($bounds['max_y'] >= $this->height - 1) {
            $edges[] = 'bottom';
        }

        if ($bounds['min_x'] <= 0) {
            $edges[] = 'start';
        }

        if ($bounds['max_x'] >= $this->width - 1) {
            $edges[] = 'end';
        }

        return $edges;
    }

    /**
     * دنبال‌کردن مرز بیرونی شکل (الگوریتم همسایگی مور).
     *
     * خروجی یک چندضلعی بسته است که برای کشیدن رونمای SVG استفاده می‌شود.
     *
     * @return array<int, array{x: int, y: int}>
     */
    public function contour(): array
    {
        $start = null;

        for ($y = 0; $y < $this->height && $start === null; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                if ($this->at($x, $y)) {
                    $start = [$x, $y];

                    break;
                }
            }
        }

        if ($start === null) {
            return [];
        }

        // همسایه‌ها به ترتیب ساعت‌گرد از بالا-چپ
        $offsets = [[-1, -1], [0, -1], [1, -1], [1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0]];

        $contour = [['x' => $start[0], 'y' => $start[1]]];
        $current = $start;
        $backtrack = [$start[0] - 1, $start[1]];
        $limit = 8 * $this->width * $this->height;

        for ($step = 0; $step < $limit; $step++) {
            $from = $this->directionIndex($offsets, $backtrack[0] - $current[0], $backtrack[1] - $current[1]);
            $found = null;

            for ($i = 1; $i <= 8; $i++) {
                $index = ($from + $i) % 8;
                $nx = $current[0] + $offsets[$index][0];
                $ny = $current[1] + $offsets[$index][1];

                if ($this->at($nx, $ny)) {
                    $previous = ($index + 7) % 8;
                    $backtrack = [$current[0] + $offsets[$previous][0], $current[1] + $offsets[$previous][1]];
                    $found = [$nx, $ny];

                    break;
                }
            }

            if ($found === null) {
                break;
            }

            $current = $found;

            if ($current[0] === $start[0] && $current[1] === $start[1]) {
                break;
            }

            $contour[] = ['x' => $current[0], 'y' => $current[1]];
        }

        return $contour;
    }

    /** جای یک جابه‌جایی در فهرست همسایه‌ها. */
    private function directionIndex(array $offsets, int $dx, int $dy): int
    {
        foreach ($offsets as $index => $offset) {
            if ($offset[0] === $dx && $offset[1] === $dy) {
                return $index;
            }
        }

        return 0;
    }

    /** پیاده‌سازی مشترک فرسایش و گسترش با همسایگی چهارتایی. */
    private function morph(bool $grow): self
    {
        $bits = $this->bits;

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $index = $y * $this->width + $x;
                $on = $this->bits[$index] === 1;

                if ($grow === $on) {
                    continue;
                }

                $neighbourMatches = false;

                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;

                    // بیرون قاب مثل زمینه حساب می‌شود
                    $value = ($nx < 0 || $ny < 0 || $nx >= $this->width || $ny >= $this->height)
                        ? false
                        : $this->bits[$ny * $this->width + $nx] === 1;

                    if ($value === $grow) {
                        $neighbourMatches = true;

                        break;
                    }
                }

                if ($neighbourMatches) {
                    $bits[$index] = $grow ? 1 : 0;
                }
            }
        }

        return new self($this->width, $this->height, $bits);
    }
}
