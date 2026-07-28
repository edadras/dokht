<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;
use App\Services\Pattern\Generators\Concerns\DraftsBodice;
use App\Services\Pattern\Geometry;

/**
 * کت.
 *
 * تنه جلو با اضافه دکمه و خط برگردان یقه، تنه پشت با درز مرکزی، آستین دوتکه
 * (آستین رو و آستین زیر)، برگردان یقه، سجاف جلو و آستر جلو و پشت.
 */
class BlazerGenerator extends BaseGenerator
{
    use BuildsSleeve, DraftsBodice;

    public function label(): string
    {
        return 'کت';
    }

    public function paramsSchema(): array
    {
        return [
            'shoulder_slope' => [
                'label' => 'شیب سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5, 'default' => 4, 'unit' => 'سانتی‌متر',
            ],
            'neck_width_extra' => [
                'label' => 'اضافه عرض یقه', 'min' => 0, 'max' => 6, 'step' => 0.5, 'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'front_neck_depth_extra' => [
                'label' => 'گودی بیشتر یقه جلو', 'min' => 0, 'max' => 12, 'step' => 0.5, 'default' => 1, 'unit' => 'سانتی‌متر',
            ],
            'back_neck_depth' => [
                'label' => 'گودی یقه پشت', 'min' => 1, 'max' => 6, 'step' => 0.5, 'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'armhole_depth_extra' => [
                'label' => 'گودی بیشتر حلقه آستین', 'min' => 1, 'max' => 9, 'step' => 0.5, 'default' => 3, 'unit' => 'سانتی‌متر',
            ],
            'waist_dart_share' => [
                'label' => 'سهم ساسون از کاهش کمر', 'min' => 0, 'max' => 0.9, 'step' => 0.05, 'default' => 0.5,
            ],
            'body_length' => [
                'label' => 'بلندی تنه از کمر', 'min' => 5, 'max' => 60, 'step' => 1, 'default' => 22, 'unit' => 'سانتی‌متر',
            ],
            'button_stand' => [
                'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 8, 'step' => 0.5, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'lapel_width' => [
                'label' => 'پهنای برگردان یقه', 'min' => 4, 'max' => 14, 'step' => 0.5, 'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'lapel_break' => [
                'label' => 'محل شکست یقه از کمر', 'min' => -10, 'max' => 15, 'step' => 1, 'default' => 2, 'unit' => 'سانتی‌متر',
            ],
            'cap_ease' => [
                'label' => 'آزادی سرآستین', 'min' => 0.5, 'max' => 6, 'step' => 0.25, 'default' => 3, 'unit' => 'سانتی‌متر',
            ],
            'lining' => [
                'label' => 'قطعه‌های آستر ساخته شود', 'type' => 'toggle', 'default' => true,
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $stand = (float) $this->param($params, 'button_stand', 2);
        $bottom = max($g['front_waist_y'], $g['back_waist_y']) + (float) $this->param($params, 'body_length', 22);
        $lapelWidth = (float) $this->param($params, 'lapel_width', 8);
        $breakY = max($g['bust_y'] + 4, $g['front_waist_y'] + (float) $this->param($params, 'lapel_break', 2));

        $front = $this->bodicePiece($g, [
            'side' => 'front',
            'shape' => 'dress',
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'hem_flare' => 2,
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'bust_dart' => true,
            'code' => 'blazer-front',
            'name' => 'تنه جلو',
            'meta' => ['button_stand' => $stand, 'lapel_break_y' => round($breakY, 2)],
        ]);

        $front['markers'][] = $this->marker('roll_line', 'خط برگردان یقه', 0, $breakY, $stand + $g['neck_width'], 0);

        $back = $this->bodicePiece($g, [
            'side' => 'back',
            'shape' => 'dress',
            'bottom_y' => $bottom,
            'bottom_tag' => 'hem',
            'hem_flare' => 2,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'code' => 'blazer-back',
            'name' => 'تنه پشت',
        ]);

        $armhole = ($front['meta']['armhole_length'] ?? 24) + ($back['meta']['armhole_length'] ?? 24);

        $pieces = array_merge(
            [$front, $back],
            $this->twoPieceSleeve($measurements, $ease, $params, $armhole),
            [
                $this->lapelPiece($lapelWidth, $g['neck_width'], $breakY),
                $this->facingPiece($g, $stand, $bottom, $lapelWidth),
            ],
        );

        if ($this->flag($params, 'lining', true)) {
            $pieces[] = $this->bodicePiece($g, [
                'side' => 'front',
                'shape' => 'dress',
                'bottom_y' => $bottom - 2,
                'bottom_tag' => 'hem',
                'hem_flare' => 2,
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'layer' => 'lining',
                'code' => 'lining-front',
                'name' => 'آستر جلو',
                'part' => 'lining',
                'meta' => ['note' => 'آستر جلو تا خط سجاف بریده می‌شود.'],
            ]);

            $pieces[] = $this->bodicePiece($g, [
                'side' => 'back',
                'shape' => 'dress',
                'bottom_y' => $bottom - 2,
                'bottom_tag' => 'hem',
                'hem_flare' => 2,
                'on_fold' => false,
                'cut' => 2,
                'mirror' => true,
                'layer' => 'lining',
                'code' => 'lining-back',
                'name' => 'آستر پشت',
                'part' => 'lining',
            ]);
        }

        return $this->finish($pieces);
    }

    /**
     * آستین دوتکه: آستین رو (پهن، با سرآستین کامل) و آستین زیر (باریک، با کپ کم‌عمق).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function twoPieceSleeve(array $m, array $ease, array $params, float $armholeLength): array
    {
        $bicep = $this->m($m, 'bicep', 28.5) + $this->ease($ease, 'bicep', 6);
        $wrist = $this->m($m, 'wrist', 16.5) + 8;
        $length = max(20.0, $this->m($m, 'arm_length', 58) + (float) $this->param($params, 'length_extra', 0));
        $capEase = (float) $this->param($params, 'cap_ease', 3);

        $upperWidth = $bicep * 0.72;
        $underWidth = $bicep * 0.36;
        $upperCap = $this->fitCapHeight($upperWidth, ($armholeLength + $capEase) * 0.72);
        $underCap = max(2.0, $upperCap * 0.3);

        $upperHem = $wrist * 0.62;
        $underHem = $wrist * 0.38;

        $upperOutline = $this->capOutline($upperWidth, $upperCap);
        $upperOutline[] = Geometry::curve(
            $upperWidth - 1.5,
            $length,
            $upperWidth + 1.2,
            $upperCap + (($length - $upperCap) * 0.55),
        );
        $upperOutline[] = Geometry::point($upperWidth - 1.5 - $upperHem, $length);
        $upperOutline[] = Geometry::curve(
            0,
            $upperCap,
            ($upperWidth - $upperHem) * 0.25,
            $upperCap + (($length - $upperCap) * 0.5),
        );

        $upper = $this->piece([
            'code' => 'upper-sleeve',
            'name' => 'آستین رو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $upperOutline,
            'grainline' => $this->grainline($upperWidth * 0.5, $upperCap * 0.4, $length - 4),
            'notches' => [
                $this->notch($upperWidth * 0.5, 0, 2, 'نوک آستین (سرشانه)', 'sleeve_top'),
                $this->notch(0, $upperCap, 4, 'نشانه آستین زیر', 'under_sleeve'),
            ],
            'markers' => [
                $this->marker('bicep', 'خط بازو', 0, $upperCap, $upperWidth),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['armhole', 'armhole', 'armhole', 'armhole', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'cap_height' => round($upperCap, 2),
                'cap_length' => Geometry::edgesLength($upperOutline, [0, 1, 2, 3]),
                'two_piece' => 'upper',
            ],
        ]);

        $underOutline = [
            Geometry::point(0, $underCap),
            Geometry::curve($underWidth, $underCap, $underWidth * 0.5, -$underCap * 0.9),
            Geometry::curve($underWidth - 1, $length, $underWidth + 1, $underCap + (($length - $underCap) * 0.55)),
            Geometry::point($underWidth - 1 - $underHem, $length),
            Geometry::curve(0, $underCap, ($underWidth - $underHem) * 0.2, $underCap + (($length - $underCap) * 0.5)),
        ];

        $under = $this->piece([
            'code' => 'under-sleeve',
            'name' => 'آستین زیر',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $underOutline,
            'grainline' => $this->grainline($underWidth * 0.5, $underCap + 2, $length - 4),
            'notches' => [
                $this->notch(0, $underCap, 4, 'نشانه آستین رو', 'under_sleeve'),
            ],
            'meta' => [
                'part' => 'sleeve',
                'edges' => ['armhole', 'side', 'hem', 'side', 'default'],
                'fold_edges' => [],
                'cap_height' => round($underCap, 2),
                'two_piece' => 'under',
            ],
        ]);

        return [$upper, $under];
    }

    /** برگردان یقه: تکه‌ای که روی سینه برمی‌گردد. */
    protected function lapelPiece(float $width, float $neckWidth, float $breakY): array
    {
        $length = max(12.0, $breakY * 0.6);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($width, 1.5),
            Geometry::point($width + 1.5, $length * 0.55),
            Geometry::curve(1.0, $length, $width * 0.35, $length * 0.9),
        ];

        return $this->piece([
            'code' => 'lapel',
            'name' => 'برگردان یقه',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($width * 0.5, 2, $length - 2),
            'markers' => [
                $this->marker('roll_line', 'خط برگردان', 0, 0, 1.0, $length),
            ],
            'meta' => [
                'part' => 'lapel',
                'edges' => ['neck', 'side', 'default', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
            ],
        ]);
    }

    /** سجاف جلو: نواری که پشت لبه جلو دوخته می‌شود و برگردان یقه را می‌پوشاند. */
    protected function facingPiece(array $g, float $stand, float $bottom, float $lapelWidth): array
    {
        $top = $g['neck_width'] + $stand;
        $hemWidth = max(6.0, $lapelWidth * 0.8);

        $outline = [
            Geometry::point(0, 0),
            Geometry::point($top, 0),
            Geometry::curve($hemWidth, $bottom, $top + $lapelWidth * 0.4, $g['front_waist_y'] * 0.6),
            Geometry::point(0, $bottom),
        ];

        return $this->piece([
            'code' => 'facing',
            'name' => 'سجاف جلو',
            'cut_quantity' => 2,
            'mirror' => true,
            'outline' => $outline,
            'grainline' => $this->grainline($hemWidth * 0.5, 3, $bottom - 3),
            'meta' => [
                'part' => 'facing',
                'edges' => ['neck', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'interfacing' => true,
            ],
        ]);
    }
}
