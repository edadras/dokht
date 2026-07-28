<?php

namespace App\Services\Pattern\Style\Neckline;

use App\Services\Pattern\Geometry;

/**
 * یقه کاول (دراپ‌دار).
 *
 * درفت کاول سه کار می‌خواهد و هر سه انجام می‌شود:
 *   ۱. خط یقه گشاد و کم‌عمق می‌شود تا کاول جای افتادن داشته باشد.
 *   ۲. مرکز جلو به اندازه «دراپ» بالاتر از خط سرشانه کشیده می‌شود و لبه یقه یک
 *      پاره‌خط راست می‌شود؛ همین اضافه است که پارچه را به چین‌های افقی می‌اندازد.
 *      این همان نتیجه برش‌دادن و بازکردن (slash & spread) الگو از خط یقه است، ولی
 *      چون مرکز جلو باید خط تای پارچه بماند، به شکل بالا کشیدن مرکز انجام می‌شود.
 *   ۳. مرکز جلو روی مورب پارچه می‌نشیند تا چین‌ها نرم بیفتد و بالای خط یقه به
 *      اندازه دو برابر پهنای سجاف برگشت (سجاف سرخود) گذاشته می‌شود.
 */
class CowlNeckline extends BaseNeckline
{
    public static function key(): string
    {
        return 'neck_cowl';
    }

    public function label(): string
    {
        return 'یقه کاول';
    }

    public function description(): string
    {
        return 'خط یقه با چین‌های نرم افقی می‌افتد؛ مرکز جلو روی مورب پارچه و با سجاف سرخود.';
    }

    public function paramsSchema(): array
    {
        return [
            'depth' => $this->depthField(6, 2, 20, 'گودی نشستن کاول'),
            'width' => $this->widthField(3, 0, 12),
            'drape' => [
                'label' => 'اندازه دراپ', 'min' => 4, 'max' => 30, 'step' => 1, 'default' => 12,
                'unit' => 'سانتی‌متر', 'hint' => 'هرچه بیشتر باشد چین‌ها عمیق‌تر و بیشتر می‌شوند.',
            ],
            'folds' => [
                'label' => 'تعداد چین', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 3,
            ],
            'back_depth' => $this->backDepthField(2, 16),
        ] + $this->finishFields(4);
    }

    protected function frontPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $drape = (float) $p['drape'];
        $depth = (float) $p['depth'];
        $folds = (int) $p['folds'];

        // مرکز جلو بالای خط سرشانه می‌رود؛ همین‌جا سجاف سرخود کاول است
        $top = Geometry::point($a['center_x'], $snp['y'] - $drape);
        $foldLine = $a['cf']['y'] + $depth;

        $markers = [
            $this->marker('cowl_fold', 'خط تای کاول (لبه سجاف سرخود)', $a['center_x'], $snp['y'], $snp['x'], $snp['y']),
            $this->marker('cowl_sit', 'جای نشستن کاول روی بدن', $a['center_x'], $foldLine, $snp['x'] * 0.6, $foldLine),
        ];

        return [
            'points' => [$top, Geometry::point($snp['x'], $snp['y'])],
            'tags' => ['neck'],
            'alpha' => null,
            'bias' => true,
            'markers' => $markers,
            'meta' => [
                'neck_is_fold' => true,
                'cowl' => [
                    'drape' => round($drape, 2),
                    'folds' => $folds,
                    'sit_depth' => round($depth, 2),
                ],
            ],
            'notes' => [
                'مرکز جلوی کاول روی مورب پارچه بریده شود؛ اگر روی راستای پارچه بریده شود چین‌ها تیز و شکسته می‌افتد.',
                'لبه بالای کاول درز ندارد و به داخل برمی‌گردد؛ یقه به این لبه دوخته نمی‌شود.',
                'شماره چین‌ها: '.$folds.' چین با '.round($drape / max(1, $folds), 1).' سانتی‌متر پارچه برای هر چین.',
            ],
        ];
    }

    /** خط یقه پشت کاول ساده و کمی گشادتر است تا کاول جلو باز بایستد. */
    protected function backPath(array $a, array $p, ?float $partnerAngle): array
    {
        $snp = $this->movedSnp($a, (float) $p['width']);
        $center = Geometry::point($a['center_x'], $a['cf']['y'] + (float) $p['back_depth']);

        return [
            'points' => [$center, $this->arrive($center, ['x' => 1.0, 'y' => 0.0], $a, 90.0, $snp)],
            'tags' => ['neck'],
            'alpha' => 90.0,
        ];
    }

    protected function afterShape(array $piece, array $a, array $p, array $path): array
    {
        if ($a['side'] !== 'front') {
            return $piece;
        }

        $edge = $this->edgeWithTag($piece, 'neck');

        return $this->recordFullness($piece, [
            'type' => 'cowl',
            'edge' => $edge,
            'amount' => round((float) $p['drape'], 2),
            'folds' => (int) $p['folds'],
            'label' => 'دراپ کاول',
        ]);
    }
}
