<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\DraftsBodice;
use App\Services\Pattern\Geometry;

/**
 * پیراهن کلاسیک.
 *
 * تنه جلو با اضافه جای دکمه، تنه پشت که زیر یوک بریده می‌شود، یوک، پاتلت،
 * یقه، آستین و مچ آستین.
 */
class ClassicShirtGenerator extends BaseGenerator
{
    use BuildsSleeve, DraftsBodice;

    public function label(): string
    {
        return 'پیراهن کلاسیک';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4.5, 'unit' => 'سانتی‌متر',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 1, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => -2, 'max' => 10, 'step' => 0.5, 'default' => 0, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 6, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => 0, 'max' => 8, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'body_length' => [
                'label' => 'بلندی تنه از کمر', 'min' => 0, 'max' => 45, 'step' => 1, 'default' => 22, 'unit' => 'سانتی‌متر',
            ],
            'fitted' => [
                'label' => 'کمر گرفته باشد', 'type' => 'toggle', 'default' => false,
            ],
            'waist_dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.45,
            ],
            'button_stand' => [
                'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 4, 'step' => 0.25, 'default' => 1.75, 'unit' => 'سانتی‌متر',
            ],
            'yoke_depth' => [
                'label' => 'بلندی یوک پشت', 'min' => 5, 'max' => 18, 'step' => 0.5, 'default' => 10, 'unit' => 'سانتی‌متر',
            ],
            'collar_height' => [
                'label' => 'بلندی یقه', 'min' => 4, 'max' => 12, 'step' => 0.5, 'default' => 7.5, 'unit' => 'سانتی‌متر',
            ],
            'cuff' => [
                'label' => 'مچ‌بند داشته باشد', 'type' => 'toggle', 'default' => true,
            ],
            'cuff_height' => [
                'label' => 'بلندی مچ‌بند', 'min' => 4, 'max' => 12, 'step' => 0.5, 'default' => 6, 'unit' => 'سانتی‌متر',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 5, 'step' => 0.25, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $fitted = $this->flag($params, 'fitted', false);
        $stand = (float) $this->param($params, 'button_stand', 1.75);
        $yokeDepth = (float) $this->param($params, 'yoke_depth', 10);
        $bottom = max($g['front_waist_y'], $g['back_waist_y']) + (float) $this->param($params, 'body_length', 22);

        $front = $this->bodicePiece($g, [
            'side' => 'front',
            'shape' => $fitted ? 'dress' : 'straight',
            'darts' => $fitted,
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'shirt-front',
            'name' => 'تنه جلو',
            'meta' => ['button_stand' => $stand],
        ]);

        // تنه پشت زیر خط یوک بریده می‌شود
        $back = $this->shirtBack($g, $yokeDepth, $bottom, $fitted);
        $yoke = $this->yokePiece($g, $yokeDepth);

        $armhole = ($front['meta']['armhole_length'] ?? 24)
            + ($back['meta']['armhole_length'] ?? 18)
            + ($yoke['meta']['armhole_length'] ?? 6);

        $sleeves = $this->sleevePieces($measurements, $ease, $params, [
            'armhole_length' => $armhole,
            'sleeve_name' => 'آستین پیراهن',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12) + ($yoke['meta']['neck_length'] ?? 9);

        $pieces = array_merge(
            [$front, $back, $yoke],
            $sleeves,
            [
                $this->collarPiece($neckHalf + $stand, (float) $this->param($params, 'collar_height', 7.5)),
                $this->placketPiece($stand, $bottom),
            ],
        );

        return $this->finish($pieces);
    }

    /** تنه پشت پیراهن: از خط یوک تا لبه پایین. */
    protected function shirtBack(array $g, float $yokeDepth, float $bottom, bool $fitted): array
    {
        $quarterBust = $g['quarter_bust'];
        $bustY = $g['bust_y'] - $yokeDepth;
        $waistY = $g['back_waist_y'] - $yokeDepth;
        $bottomY = $bottom - $yokeDepth;
        $acrossBack = $g['across_back'];
        $sideX = $fitted ? $quarterBust - $g['side_intake'] : $quarterBust;

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($acrossBack, 0),
            Geometry::curve($quarterBust, $bustY, $acrossBack + 0.5, $bustY * 0.55),
        ];

        $edges = ['shoulder', 'armhole'];

        if ($fitted) {
            $outline[] = Geometry::curve($sideX, $waistY, $quarterBust - ($g['side_intake'] * 0.7), $bustY + (($waistY - $bustY) * 0.55));
            $edges[] = 'side';
            $outline[] = Geometry::curve(
                $g['quarter_hip'],
                min($bottomY, $waistY + $g['hip_drop']),
                $sideX + 0.5,
                $waistY + ($g['hip_drop'] * 0.5),
            );
            $edges[] = 'side';
            $outline[] = Geometry::point($g['quarter_hip'], $bottomY);
            $edges[] = 'side';
        } else {
            $outline[] = Geometry::point($quarterBust, $bottomY);
            $edges[] = 'side';
        }

        $outline[] = Geometry::point(0, $bottomY);
        $edges[] = 'hem';
        $edges[] = 'default';

        return $this->piece([
            'code' => 'shirt-back',
            'name' => 'تنه پشت',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($quarterBust * 0.4, 3, $bottomY - 3),
            'notches' => [
                $this->notch($acrossBack * 0.5, 0, 0, 'نشانه یوک', 'yoke'),
                $this->notch($sideX, $waistY, 2, 'نشانه کمر روی پهلو', 'side'),
            ],
            'markers' => [
                $this->marker('bust', 'خط سینه', 0, max(0.5, $bustY), $quarterBust),
                $this->marker('waist', 'خط کمر', 0, max(1.0, $waistY), $sideX),
                $this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $bottomY),
            ],
            'meta' => [
                'part' => 'back_bodice',
                'edges' => $edges,
                'fold_edges' => [count($outline) - 1],
                'side' => 'back',
                'armhole_edge' => 1,
                'armhole_length' => Geometry::edgeLength($outline, 1),
                'yoke_depth' => $yokeDepth,
            ],
        ]);
    }

    /** یوک پشت: تکه بالای پشت که سرشانه‌ها را نگه می‌دارد (دو لا بریده می‌شود). */
    protected function yokePiece(array $g, float $yokeDepth): array
    {
        $neckWidth = $g['neck_width'] + 0.3;

        $outline = [
            Geometry::point(0, $g['back_neck_depth']),
            Geometry::curve($neckWidth, 0, $neckWidth * 0.32, $g['back_neck_depth'] * 0.24),
            Geometry::point($g['shoulder_half'], $g['shoulder_drop'] + 0.5),
            Geometry::curve($g['across_back'], $yokeDepth, $g['across_back'] - 0.4, $yokeDepth * 0.55),
            Geometry::point(0, $yokeDepth),
        ];

        $edges = ['neck', 'shoulder', 'armhole', 'shoulder', 'default'];

        return $this->piece([
            'code' => 'yoke',
            'name' => 'یوک پشت',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($g['across_back'] * 0.5, 1.5, $yokeDepth - 1),
            'notches' => [
                $this->notch($g['across_back'] * 0.5, $yokeDepth, 3, 'نشانه یوک', 'yoke'),
            ],
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $yokeDepth),
            ],
            'meta' => [
                'part' => 'yoke',
                'edges' => $edges,
                'fold_edges' => [4],
                'armhole_edge' => 2,
                'armhole_length' => Geometry::edgeLength($outline, 2),
                'neck_length' => Geometry::edgeLength($outline, 0),
            ],
        ]);
    }

    /** یقه پیراهن: نیم‌یقه روی تای مرکز پشت. */
    protected function collarPiece(float $halfNeck, float $height): array
    {
        $length = max(10.0, $halfNeck);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($length, 0.8),
            Geometry::point($length + 2.0, $height),
            Geometry::curve(0, $height, $length * 0.5, $height - 1.2),
        ];

        return $this->piece([
            'code' => 'collar',
            'name' => 'یقه',
            'cut_quantity' => 2,
            'on_fold' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($length * 0.5, 1.5, $height - 1),
            'notches' => [
                $this->notch(0, $height, 2, 'مرکز پشت یقه', 'collar_center'),
            ],
            'markers' => [
                $this->marker('cb', 'خط مرکز پشت', 0, 0, 0, $height),
            ],
            'meta' => [
                'part' => 'collar',
                'edges' => ['default', 'side', 'neck', 'default'],
                'fold_edges' => [3],
                'neck_length' => Geometry::edgeLength($outline, 2),
                'interfacing' => true,
            ],
        ]);
    }

    /** پاتلت (نوار جای دکمه) که روی لبه جلو دوخته می‌شود. */
    protected function placketPiece(float $stand, float $length): array
    {
        $width = $stand * 2;

        return $this->piece([
            'code' => 'placket',
            'name' => 'پاتلت جای دکمه',
            'cut_quantity' => 1,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $length),
                Geometry::point(0, $length),
            ],
            'grainline' => $this->grainline($width * 0.5, 2, $length - 2),
            'meta' => [
                'part' => 'placket',
                'edges' => ['default', 'default', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
            ],
        ]);
    }
}
