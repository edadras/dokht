<?php

namespace App\Services\Pattern\Style\Pocket;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Detail\DetailStyle;

/**
 * پایه مشترک جیب‌ها.
 *
 * هر جیب دو کار می‌کند: قطعه یا قطعه‌های خودش را درفت می‌کند، و قطعه میزبان را
 * آماده می‌کند — جای جیب را با سوراخ مته و خط نشانه علامت می‌زند و اگر جیب واقعاً
 * روی لباس باز می‌شود (فیلتابی، مورب، زیپ‌دار) خط برش را روی میزبان ثبت می‌کند.
 */
abstract class BasePocket extends DetailStyle
{
    public static function group(): string
    {
        return 'pocket';
    }

    /** قطعه‌هایی که این جیب می‌تواند رویشان بنشیند. */
    protected function hostParts(array $context): array
    {
        return $this->text($context, 'host', 'front') === 'back'
            ? static::BACK_PARTS
            : static::FRONT_PARTS;
    }

    /** پارامترهای مشترک جای‌گذاری. */
    protected function placementParams(bool $withHost = true): array
    {
        $schema = [];

        if ($withHost) {
            $schema['host'] = [
                'label' => 'روی کدام طرف', 'type' => 'select', 'default' => 'front',
                'options' => ['front' => 'جلو', 'back' => 'پشت'],
            ];
        }

        $schema['offset_x'] = [
            'label' => 'جابه‌جایی افقی', 'min' => -20, 'max' => 20, 'step' => 0.5, 'default' => 0,
            'unit' => 'سانتی‌متر', 'hint' => 'مثبت یعنی به سمت پهلو.',
        ];

        $schema['offset_y'] = [
            'label' => 'جابه‌جایی عمودی', 'min' => -20, 'max' => 25, 'step' => 0.5, 'default' => 0,
            'unit' => 'سانتی‌متر', 'hint' => 'مثبت یعنی پایین‌تر.',
        ];

        return $schema;
    }

    public function supports(array $pieces, array $context): true|string
    {
        if ($this->firstIndexOfParts($pieces, $this->hostParts($context)) === null) {
            return 'برای این جیب یک قطعه تنه (جلو یا پشت) لازم است؛ این لباس چنین قطعه‌ای ندارد.';
        }

        return true;
    }

    /* ---------------------------------------------------------------------
     |  جای‌گذاری روی میزبان
     * ------------------------------------------------------------------- */

    /**
     * جای پیش‌فرض گوشه بالا-چپ جیب روی میزبان، به‌علاوه جابه‌جایی کاربر.
     *
     * @return array{0: float, 1: float}
     */
    protected function anchor(array $piece, array $context, float $width, float $height): array
    {
        $pieceWidth = Geometry::width($piece['outline']);
        $pieceHeight = Geometry::height($piece['outline']);
        $center = $this->centerX($piece);
        $part = $this->partOf($piece);

        if (in_array($part, ['front_bodice', 'back_bodice'], true)) {
            // جیب سینه: کمی زیر خط سینه و میان مرکز و پهلو
            $bust = $this->markerY($piece, 'bust') ?? ($pieceHeight * 0.32);
            $x = $center + (($pieceWidth - $center) * 0.28);
            $y = $bust + 1.5;
        } elseif (in_array($part, ['skirt_front', 'skirt_back'], true)) {
            $x = $center + (($pieceWidth - $center) * 0.30);
            $y = 7.0;
        } else { // شلوار
            $x = $center + (($pieceWidth - $center) * 0.28);
            $y = 6.0;
        }

        $x += $this->num($context, 'offset_x', 0);
        $y += $this->num($context, 'offset_y', 0);

        return $this->fit($piece, $x, $y, $width, $height);
    }

    /**
     * جیب را داخل قطعه نگه می‌دارد: اگر گوشه‌ای بیرون بزند به سمت مرکز و بالا
     * کشیده می‌شود تا کامل روی پارچه بنشیند.
     *
     * @return array{0: float, 1: float}
     */
    protected function fit(array $piece, float $x, float $y, float $width, float $height): array
    {
        $pieceWidth = Geometry::width($piece['outline']);
        $pieceHeight = Geometry::height($piece['outline']);

        $x = max(0.5, min($x, max(0.5, $pieceWidth - $width - 0.5)));
        $y = max(0.5, min($y, max(0.5, $pieceHeight - $height - 0.5)));

        for ($i = 0; $i < 14; $i++) {
            if ($this->rectInside($piece, $x, $y, $width, $height)) {
                break;
            }

            $x = max(0.5, $x - 0.8);

            if ($x <= 0.5) {
                $y = max(0.5, $y - 1.0);
            }
        }

        return [round($x, 2), round($y, 2)];
    }

    protected function rectInside(array $piece, float $x, float $y, float $width, float $height): bool
    {
        foreach ([[$x, $y], [$x + $width, $y], [$x + $width, $y + $height], [$x, $y + $height]] as [$px, $py]) {
            if (! $this->pointInside($piece, $px, $py)) {
                return false;
            }
        }

        return true;
    }

    /** آیا این نقطه داخل مسیر قطعه است (پرتاب شعاع). */
    protected function pointInside(array $piece, float $x, float $y): bool
    {
        $points = Geometry::flatten($piece['outline'] ?? []);
        $count = count($points);
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $points[$i]['x'];
            $yi = $points[$i]['y'];
            $xj = $points[$j]['x'];
            $yj = $points[$j]['y'];

            if ((($yi > $y) !== ($yj > $y)) && ($x < (($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9)) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * علامت‌گذاری جای جیب روی میزبان: چهار سوراخ مته در گوشه‌ها و یک خط نشانه بالا.
     */
    protected function markPlacement(array $piece, float $x, float $y, float $width, float $height, string $label): array
    {
        foreach ([[$x, $y], [$x + $width, $y], [$x + $width, $y + $height], [$x, $y + $height]] as $index => [$px, $py]) {
            $piece['drills'][] = $this->drill($px, $py, 'pocket', $label.' — گوشه '.($index + 1));
        }

        $piece['markers'][] = $this->marker('pocket', $label, $x, $y, $x + $width, $y);
        $piece['markers'][] = $this->marker('pocket', $label, $x, $y + $height, $x + $width, $y + $height);

        $piece['meta']['pockets'][] = [
            'key' => static::key(),
            'label' => $label,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'width' => round($width, 2),
            'height' => round($height, 2),
        ];

        return $piece;
    }

    /**
     * کیسه جیب: قطعه‌ای که پشت لباس می‌ماند و ته آن گرد است.
     */
    protected function bagPiece(float $width, float $depth, string $code, string $name, int $cut = 2, string $layer = 'lining'): array
    {
        return $this->piece([
            'code' => $code,
            'name' => $name,
            'layer' => $layer,
            'cut_quantity' => $cut,
            'outline' => $this->roundedBottom($width, $depth, min(3.5, $width / 3)),
            'grainline' => $this->grainline($width * 0.5, 1, $depth - 1),
            'meta' => [
                'part' => 'pocket_bag',
                'edges' => ['default', 'side', 'hem', 'hem', 'hem', 'side'],
                'fold_edges' => [],
                'pocket' => static::key(),
            ],
        ]);
    }

    /** درپوش (فلپ) جیب. */
    protected function flapPiece(float $width, float $height, string $code, string $name, string $shape = 'square'): array
    {
        $outline = match ($shape) {
            'round' => $this->roundedBottom($width, $height, min(2.5, $height / 2)),
            'point' => $this->pointedBottom($width, $height, min(2.5, $height / 2)),
            default => $this->rect($width, $height),
        };

        return $this->piece([
            'code' => $code,
            'name' => $name,
            'cut_quantity' => 4,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 0.8, $height - 0.8),
            'meta' => [
                'part' => 'pocket_flap',
                'edges' => array_fill(0, count($outline), 'default'),
                'fold_edges' => [],
                'interfacing' => true,
                'pocket' => static::key(),
                'note' => 'دو تا رو و دو تا آستر (برای هر جیب یک جفت).',
            ],
        ]);
    }
}
