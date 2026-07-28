<?php

namespace App\Services\Vision;

use App\Support\Jalali;
use RuntimeException;

/**
 * جداکردن لباس از زمینه در یک عکس و اندازه‌گیری شکل آن.
 *
 * اینجا هیچ هوش مصنوعی و هیچ سرویس بیرونی در کار نیست. کاری که انجام می‌شود
 * دقیقاً همین چند مرحله است و هر مرحله قابل بازبینی است:
 *
 *   ۱) کوچک‌کردن عکس تا حداکثر ۲۰۰ نقطه (سرعت و کم‌شدن نویز).
 *   ۲) اگر عکس PNG با زمینه شفاف باشد، خود کانال شفافیت نقاب است.
 *   ۳) وگرنه رنگ زمینه از چهار گوشه عکس تخمین زده می‌شود و فاصله رنگی هر نقطه
 *      تا آن رنگ حساب می‌شود.
 *   ۴) آستانه جدایی با روش اوتسو روی همان نقشه فاصله انتخاب می‌شود (بدون عدد
 *      دستی)؛ اگر نتیجه بی‌معنی بود، اوتسو روی روشنایی تکرار می‌شود.
 *   ۵) نویز با باز/بسته‌کردن ریخت‌شناسی پاک، بزرگ‌ترین تکه نگه داشته و سوراخ‌های
 *      داخلی پر می‌شود.
 *
 * خروجی یک «نقطه شروع» است، نه یک تشخیص قطعی؛ همه محدودیت‌ها در notes برمی‌گردد.
 */
class GarmentImageAnalyzer
{
    /** بزرگ‌ترین ضلع تصویر کاری. */
    public const MAX_SIDE = 200;

    public function __construct(
        protected GarmentClassifier $classifier = new GarmentClassifier,
        protected DesignProposal $proposals = new DesignProposal,
    ) {}

    /**
     * تحلیل کامل یک فایل عکس و ساخت پیشنهاد.
     *
     * @param  array{sensitivity?: float, workshop_id?: int|null, image_url?: string|null}  $options
     * @return array<string, mixed>
     */
    public function analyze(string $path, array $options = []): array
    {
        [$mask, $notes] = $this->silhouette($path, (float) ($options['sensitivity'] ?? 1.0));
        $features = SilhouetteFeatures::extract($mask, $notes);
        $classification = $this->classifier->classify($features);

        return $this->proposals->build('photo', $mask, $features, $classification, $options);
    }

    /** اندازه‌گیری ویژگی‌ها از فایل عکس. */
    public function features(string $path, float $sensitivity = 1.0): SilhouetteFeatures
    {
        [$mask, $notes] = $this->silhouette($path, $sensitivity);

        return SilhouetteFeatures::extract($mask, $notes);
    }

    /**
     * ساخت نقاب دودویی از فایل عکس.
     *
     * @return array{0: Silhouette, 1: array<int, string>}
     */
    public function silhouette(string $path, float $sensitivity = 1.0): array
    {
        $sensitivity = max(0.4, min(2.0, $sensitivity));
        $image = $this->load($path);

        try {
            [$width, $height] = [imagesx($image), imagesy($image)];
            $notes = [];

            [$red, $green, $blue, $alpha, $luma] = $this->channels($image, $width, $height);

            $transparent = count(array_filter($alpha, fn ($value) => $value > 90));

            if ($transparent > 0.08 * $width * $height) {
                $bits = array_map(fn ($value) => $value < 60 ? 1 : 0, $alpha);
                $notes[] = 'عکس زمینه شفاف داشت، پس خود کانال شفافیت به‌عنوان مرز لباس استفاده شد.';
            } else {
                [$bits, $note] = $this->separateFromBackground($red, $green, $blue, $luma, $width, $height, $sensitivity);
                $notes[] = $note;
            }

            $mask = new Silhouette($width, $height, $bits);
            $foreground = $mask->coverage();

            if ($foreground < 0.03 || $foreground > 0.88) {
                [$bits, $note] = $this->separateByBrightness($luma, $width, $height, $sensitivity);
                $mask = new Silhouette($width, $height, $bits);
                $notes[] = 'جداسازی بر پایه رنگ زمینه نتیجه بی‌معنی داد ('
                    .Jalali::digits(number_format($foreground * 100, 0)).'٪ از قاب)، پس '.$note;
            }

            $mask = $mask->opened()->closed()->largestComponent()->fillHoles();

            if ($mask->area() === 0) {
                $notes[] = 'هیچ شکل به‌هم‌پیوسته‌ای در عکس پیدا نشد؛ عکس را روی زمینه ساده و یک‌دست دوباره بگیرید.';
            }

            return [$mask, $notes];
        } finally {
            imagedestroy($image);
        }
    }

    /** بارگذاری و کوچک‌کردن عکس. */
    protected function load(string $path): \GdImage
    {
        $data = @file_get_contents($path);

        if ($data === false || $data === '') {
            throw new RuntimeException('فایل عکس خوانده نشد.');
        }

        $image = @imagecreatefromstring($data);

        if ($image === false) {
            throw new RuntimeException('این فایل یک عکس معتبر نیست.');
        }

        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest > self::MAX_SIDE) {
            $scale = self::MAX_SIDE / $longest;
            $resized = imagescale($image, max(8, (int) round($width * $scale)), max(8, (int) round($height * $scale)));

            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * خواندن کانال‌های رنگ، شفافیت و روشنایی.
     *
     * @return array{0: array<int,int>, 1: array<int,int>, 2: array<int,int>, 3: array<int,int>, 4: array<int,int>}
     */
    protected function channels(\GdImage $image, int $width, int $height): array
    {
        $red = [];
        $green = [];
        $blue = [];
        $alpha = [];
        $luma = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colour = imagecolorat($image, $x, $y);
                $r = ($colour >> 16) & 0xFF;
                $g = ($colour >> 8) & 0xFF;
                $b = $colour & 0xFF;

                $red[] = $r;
                $green[] = $g;
                $blue[] = $b;
                $alpha[] = ($colour >> 24) & 0x7F;
                $luma[] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }

        return [$red, $green, $blue, $alpha, $luma];
    }

    /**
     * جداسازی با تخمین رنگ زمینه از گوشه‌ها.
     *
     * @return array{0: array<int,int>, 1: string}
     */
    protected function separateFromBackground(array $red, array $green, array $blue, array $luma, int $width, int $height, float $sensitivity): array
    {
        $patch = max(2, (int) round(0.10 * min($width, $height)));
        $samples = ['r' => [], 'g' => [], 'b' => [], 'l' => []];

        foreach ([[0, 0], [$width - $patch, 0], [0, $height - $patch], [$width - $patch, $height - $patch]] as [$ox, $oy]) {
            for ($y = max(0, $oy); $y < min($height, $oy + $patch); $y++) {
                for ($x = max(0, $ox); $x < min($width, $ox + $patch); $x++) {
                    $index = $y * $width + $x;
                    $samples['r'][] = $red[$index];
                    $samples['g'][] = $green[$index];
                    $samples['b'][] = $blue[$index];
                    $samples['l'][] = $luma[$index];
                }
            }
        }

        $background = [
            'r' => $this->median($samples['r']),
            'g' => $this->median($samples['g']),
            'b' => $this->median($samples['b']),
        ];

        $spread = $this->spread($samples['l']);
        $distance = [];

        foreach ($red as $index => $r) {
            $distance[] = max(
                abs($r - $background['r']),
                abs($green[$index] - $background['g']),
                abs($blue[$index] - $background['b']),
            );
        }

        $threshold = max(14, (int) round($this->otsu($distance) / $sensitivity));
        $bits = array_map(fn ($value) => $value > $threshold ? 1 : 0, $distance);

        $note = 'رنگ زمینه از چهار گوشه عکس تخمین زده شد ('
            .sprintf('#%02x%02x%02x', $background['r'], $background['g'], $background['b'])
            .') و آستانه جدایی با روش اوتسو روی فاصله رنگی برابر '.Jalali::digits($threshold).' از ۲۵۵ شد.';

        if ($spread > 28) {
            $note .= ' زمینه یک‌دست نیست (پراکندگی روشنایی گوشه‌ها بالاست)، پس مرز لباس می‌تواند خطا داشته باشد.';
        }

        return [$bits, $note];
    }

    /**
     * جداسازی پشتیبان: اوتسو روی روشنایی، با قطبیتی که گوشه‌ها را زمینه بداند.
     *
     * @return array{0: array<int,int>, 1: string}
     */
    protected function separateByBrightness(array $luma, int $width, int $height, float $sensitivity): array
    {
        $threshold = (int) round($this->otsu($luma) / $sensitivity);
        $corners = [
            $luma[0],
            $luma[$width - 1],
            $luma[($height - 1) * $width],
            $luma[$height * $width - 1],
        ];

        $backgroundIsBright = $this->median($corners) > $threshold;

        $bits = array_map(
            fn ($value) => ($backgroundIsBright ? $value <= $threshold : $value > $threshold) ? 1 : 0,
            $luma,
        );

        return [$bits, 'جداسازی با روشنایی انجام شد (آستانه اوتسو '.Jalali::digits($threshold).'؛ زمینه '
            .($backgroundIsBright ? 'روشن‌تر' : 'تیره‌تر').' از لباس فرض شد).'];
    }

    /**
     * آستانه اوتسو: آستانه‌ای که پراکندگی درون دو گروه را کمینه می‌کند.
     *
     * @param  array<int, int>  $values  مقادیر ۰ تا ۲۵۵
     */
    public function otsu(array $values): int
    {
        $histogram = array_fill(0, 256, 0);

        foreach ($values as $value) {
            $histogram[max(0, min(255, (int) $value))]++;
        }

        $total = count($values);

        if ($total === 0) {
            return 128;
        }

        $sum = 0.0;

        for ($i = 0; $i < 256; $i++) {
            $sum += $i * $histogram[$i];
        }

        $sumB = 0.0;
        $weightB = 0;
        $best = 0.0;
        $threshold = 0;

        for ($i = 0; $i < 256; $i++) {
            $weightB += $histogram[$i];

            if ($weightB === 0) {
                continue;
            }

            $weightF = $total - $weightB;

            if ($weightF === 0) {
                break;
            }

            $sumB += $i * $histogram[$i];
            $meanB = $sumB / $weightB;
            $meanF = ($sum - $sumB) / $weightF;
            $between = $weightB * $weightF * ($meanB - $meanF) ** 2;

            if ($between > $best) {
                $best = $between;
                $threshold = $i;
            }
        }

        return $threshold;
    }

    /** @param  array<int, int>  $values */
    protected function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        sort($values);

        return (int) $values[intdiv(count($values), 2)];
    }

    /** @param  array<int, int>  $values */
    protected function spread(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }
}
