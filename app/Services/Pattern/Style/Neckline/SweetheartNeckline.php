<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه دلبری.
 *
 * دو کاسه کوچک روی سینه که در مرکز جلو به هم می‌رسند و از آن‌جا خط به سرگردن
 * می‌رود. روی نیم‌قطعه فقط یک کاسه درفت می‌شود؛ کاسه دوم قرینه آن است.
 */
class SweetheartNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_sweetheart';
    }

    public function label(): string
    {
        return 'یقه دلبری';
    }

    public function description(): string
    {
        return 'دو کاسه قلبی روی سینه؛ برای لباس مجلسی و بالاتنه ساسون‌دار.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(9, 3, 24),
            'lobe' => [
                'label' => 'پهنای کاسه', 'min' => 3, 'max' => 12, 'step' => 0.5, 'default' => 6,
                'unit' => 'سانتی‌متر', 'hint' => 'نصف پهنای دو کاسه روی مرکز جلو.',
            ],
            'lobe_rise' => [
                'label' => 'بلندی کاسه', 'min' => 1, 'max' => 10, 'step' => 0.5, 'default' => 3.5, 'unit' => 'سانتی‌متر',
            ],
            'width' => $this->widthField(2),
            'back_depth' => $this->backDepthField(3, 20),
        ] + $this->finishFields();
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['depth']);
        $lobe = min((float) $p['lobe'], max(2.0, ($snp['x'] - $a['center_x']) * 0.8));
        $rise = (float) $p['lobe_rise'];

        // کاسه: از مرکز جلو با مماس افقی بیرون می‌رود و بالا می‌آید
        $top = Geometry::curve(
            $a['center_x'] + $lobe,
            $center['y'] - $rise,
            $a['center_x'] + ($lobe * 0.85),
            $center['y'],
        );

        return [
            'points' => [$center, $top, $this->arrive($top, $this->continueDir($top), $a, 90.0, $snp)],
            'tags' => ['neck', 'neck'],
            'alpha' => 90.0,
            'notes' => ['یقه دلبری روی خط سینه می‌نشیند؛ ساسون سینه را نبندید و اگر لازم شد لایه چسب نازک به کاسه‌ها بزنید.'],
        ];
    }
}
