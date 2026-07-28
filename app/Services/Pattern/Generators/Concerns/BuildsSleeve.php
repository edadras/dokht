<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;

/** ساخت آستین یک‌تکه و مچ‌بند؛ پیراهن و کت هم از همین استفاده می‌کنند. */
trait BuildsSleeve
{
    /**
     * قطعه‌های آستین.
     *
     * @param  array<string, mixed>  $options  armhole_length، prefix، sleeve_name، cut
     * @return array<int, array<string, mixed>>
     */
    protected function sleevePieces(array $m, array $ease, array $params, array $options = []): array
    {
        $bicep = $this->m($m, 'bicep', 28.5);
        $wrist = $this->m($m, 'wrist', 16.5);
        $armLength = $this->m($m, 'arm_length', 58);

        $armholeLength = (float) ($options['armhole_length'] ?? 0);

        if ($armholeLength <= 0) {
            $armholeLength = (float) $this->param($params, 'armhole_length', 0);
        }

        if ($armholeLength <= 0) {
            $armholeLength = $this->m($m, 'armhole', 42) + max(0, $this->ease($ease, 'bust', 6) / 4);
        }

        $capEase = (float) $this->param($params, 'cap_ease', 1.5);
        $bicepEase = $this->ease($ease, 'bicep', 4);
        $width = max($bicep * 0.8, $bicep + $bicepEase);

        $capHeight = $this->fitCapHeight($width, $armholeLength + $capEase);

        $hasCuff = $this->flag($params, 'cuff', false) && ! ($options['no_cuff'] ?? false);
        $cuffHeight = (float) $this->param($params, 'cuff_height', 6);
        $length = isset($options['length'])
            ? max(8.0, (float) $options['length'])
            : max(12.0, $armLength + (float) $this->param($params, 'length_extra', 0));
        $bodyLength = $hasCuff ? max(12.0, $length - $cuffHeight) : $length;

        $hemEase = (float) $this->param($params, 'hem_ease', 6);
        $hemWidth = $bodyLength < 32
            ? max($width * 0.62, $width - 4)
            : max(min($width - 2, $wrist + $hemEase), $width * 0.4);

        $center = $width / 2;
        $hemHalf = $hemWidth / 2;

        $outline = $this->capOutline($width, $capHeight);
        $edges = ['armhole', 'armhole', 'armhole', 'armhole'];

        // درز پهلوی پشت آستین، دم آستین و درز جلو
        $outline[] = Geometry::curve(
            $center + $hemHalf,
            $bodyLength,
            $width - (($width - ($center + $hemHalf)) * 0.4),
            $capHeight + (($bodyLength - $capHeight) * 0.5),
        );
        $edges[] = 'side';

        $outline[] = Geometry::point($center - $hemHalf, $bodyLength);
        $edges[] = 'hem';
        $edges[] = 'side';

        $capLength = Geometry::edgesLength($outline, [0, 1, 2, 3]);

        $frontNotch = Geometry::pointOnEdge($outline, 1, 0.55);
        $backNotch = Geometry::pointOnEdge($outline, 2, 0.45);

        $notches = [
            $this->notch($center, 0, 2, 'نوک آستین (سرشانه)', 'sleeve_top'),
            $this->notch($frontNotch['x'], $frontNotch['y'], 1, 'نشانه جلو آستین', 'armhole_front'),
            $this->notch($backNotch['x'], $backNotch['y'], 2, 'نشانه پشت آستین', 'armhole_back'),
            $this->notch(0, $capHeight, 4, 'زیر بغل پشت', 'underarm'),
            $this->notch($width, $capHeight, 6, 'زیر بغل جلو', 'underarm'),
        ];

        $sleeve = $this->piece([
            'code' => ($options['prefix'] ?? '').'sleeve',
            'name' => $options['sleeve_name'] ?? 'آستین',
            'cut_quantity' => (int) ($options['cut'] ?? 2),
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($center, $capHeight * 0.35, $bodyLength - 3),
            'notches' => $notches,
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $capHeight, $width),
                $this->marker('sleeve_center', 'خط میانی آستین', $center, 0, $center, $bodyLength),
                $this->marker('elbow', 'خط آرنج', $center - ($hemHalf + 1), $capHeight + (($bodyLength - $capHeight) * 0.55), $center + $hemHalf + 1, $capHeight + (($bodyLength - $capHeight) * 0.55)),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => $edges,
                'fold_edges' => [],
                'cap_height' => round($capHeight, 2),
                'cap_length' => $capLength,
                'target_armhole' => round($armholeLength, 2),
                'bicep_width' => round($width, 2),
                'hem_width' => round($hemWidth, 2),
                'sleeve_length' => round($bodyLength, 2),
            ],
        ]);

        $pieces = [$sleeve];

        if ($hasCuff) {
            $pieces[] = $this->cuffPiece($wrist, $cuffHeight, $options);
        }

        return $pieces;
    }

    /** مچ‌بند: نواری که دور مچ بسته می‌شود (دولا با خط تای وسط). */
    protected function cuffPiece(float $wrist, float $height, array $options = []): array
    {
        $width = $wrist + 5;
        $full = $height * 2;

        return $this->piece([
            'code' => ($options['prefix'] ?? '').'cuff',
            'name' => 'مچ آستین',
            'cut_quantity' => 2,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $full),
                Geometry::point(0, $full),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $full - 1.5),
            'markers' => [
                $this->marker('fold', 'خط تای مچ‌بند', 0, $height, $width),
            ],
            'meta' => [
                'part' => 'cuff',
                'edges' => ['default', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'interfacing' => true,
            ],
        ]);
    }

    /**
     * منحنی سرآستین با چهار کمان درجه‌دو: دو کمان جلو (گودتر) و دو کمان پشت (پرتر).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function capOutline(float $width, float $height): array
    {
        return [
            Geometry::point(0, $height),
            Geometry::curve($width * 0.25, $height * 0.42, $width * 0.05, $height * 0.80),
            Geometry::curve($width * 0.50, 0, $width * 0.34, $height * 0.06),
            Geometry::curve($width * 0.75, $height * 0.36, $width * 0.66, $height * 0.02),
            Geometry::curve($width, $height, $width * 0.95, $height * 0.74),
        ];
    }

    /** ارتفاع کپ را تنظیم می‌کند تا طول سرآستین به اندازه هدف برسد. */
    protected function fitCapHeight(float $width, float $target): float
    {
        $height = $width * 0.42;
        $min = $width * 0.24;
        $max = $width * 0.70;

        for ($i = 0; $i < 14; $i++) {
            $outline = $this->capOutline($width, $height);
            $length = Geometry::edgesLength($outline, [0, 1, 2, 3]);
            $difference = $target - $length;

            if (abs($difference) < 0.15) {
                break;
            }

            $height = max($min, min($max, $height + ($difference * 0.9)));
        }

        return round($height, 2);
    }

}
