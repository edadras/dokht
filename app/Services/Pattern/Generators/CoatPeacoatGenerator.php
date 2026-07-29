<?php

namespace App\Services\Pattern\Generators;

/**
 * پالتو پی‌کت.
 *
 * پالتوی کوتاه دریانوردی. دو نشانهٔ این مدل، هر دو، از یک کار می‌آیند: پی‌کت
 * ساخته شده بود تا باد و آب را از گردن دور کند.
 *
 *   ۱. یقهٔ برگردانِ پهن. یقهٔ پی‌کت آن‌قدر بزرگ است که بشود بالا زد و گردن را
 *      پوشاند. یقهٔ پهن روی شانه نمی‌خوابد مگر لبهٔ درزِ گردنش پرتر منحنی شود؛
 *      همین کار در ulsterCollarPiece انجام شده است.
 *   ۲. دو ردیف دکمه با هم‌پوشانی زیاد. هم‌پوشانیِ ده سانتی‌متری یعنی دو لا
 *      پارچه روی سینه، و همان است که باد را نگه می‌دارد. برای همین دکمه‌ها هم
 *      درشت‌اند و از خط سینه شروع می‌شوند، نه از پایین‌تر.
 *
 * تنه تا روی باسن می‌آید، درز مرکز پشت دارد و همه‌جا آستر می‌خورد.
 */
class CoatPeacoatGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'coat_peacoat';
    }

    public function label(): string
    {
        return 'پالتو پی‌کت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 4.5,
                'neck_width_extra' => 3,
                'front_neck_depth_extra' => 4,
                'shoulder_slope' => 4,
                'waist_dart_share' => 0.4,
            ]),
            $this->garmentLengthParam(30, 16, 55),
            $this->without($this->openingParam('button', 10), ['front_opening', 'buttons']),
            $this->sleeveParam('two_piece', 60),
            [
                'collar_height' => [
                    'label' => 'پهنای یقهٔ برگردان', 'min' => 7, 'max' => 16, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                    'hint' => 'یقهٔ پی‌کت عمداً پهن است تا بشود بالا زد و گردن را پوشاند.',
                ],
                'collar_point' => [
                    'label' => 'بلندی نوک یقه', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'button_rows' => [
                    'label' => 'تعداد دکمه در هر ردیف', 'min' => 2, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 2.5, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 16, 17),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 15.0, ['waist' => -1.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = max(6.0, (float) $this->param($params, 'button_stand', 10));
        $length = (float) $this->param($params, 'length', 30);
        $rows = (int) $this->param($params, 'button_rows', 3);
        $collarHeight = (float) $this->param($params, 'collar_height', 11);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'peacoat-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'stand' => $stand,
            'back_seam' => true,
            'bust_dart' => true,
            'buttons' => 0,
            // یقه را خودمان می‌سازیم؛ یقهٔ برگردان معمولی برای پی‌کت باریک است
            'collar' => 'none',
            'front_name' => 'تنه جلوی پی‌کت (هم‌پوشانی دوردیفه)',
            'back_name' => 'تنه پشت پی‌کت (درز مرکزی)',
            'facing_width' => max(12.0, $stand + 5),
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 2), 'back_pleat' => 2.5],
        ]);

        $halfNeck = $this->neckOf([$pieces[0], $pieces[1]]);

        $top = $g['bust_y'] + 1;
        $bottom = max($top + 4, $g['front_waist_y'] + min(4.0, $length - 4));
        $pieces[0] = $this->markDoubleBreast($pieces[0], $stand, $top, $bottom, $rows);
        $pieces[0]['meta']['interfacing'] = true;

        $pieces[] = $this->ulsterCollarPiece($halfNeck, $collarHeight, [
            'prefix' => 'peacoat-',
            'point' => (float) $this->param($params, 'collar_point', 7),
            'name' => 'یقهٔ برگردان پی‌کت',
        ]);

        $notes = [
            'یقه '.$this->fa(round($collarHeight, 1)).' سانتی‌متر پهناست و می‌تواند بالا زده شود؛ '
                .'برای همین هر دو لایه‌اش از پارچهٔ رو بریده می‌شود، نه یکی از آستر.',
            'هم‌پوشانی '.$this->fa(round($stand, 1)).' سانتی‌متری جلو، دو لا پارچه روی سینه می‌گذارد؛ '
                .'همین دو لا کارِ اصلی پی‌کت است.',
        ];

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 16),
                (float) $this->param($params, 'pocket_height', 17),
                ['prefix' => 'peacoat-', 'welt' => 3.0, 'name' => 'مغزی جیب عمودی'],
            ));
            $notes[] = 'جیب‌ها عمودی‌اند و روی درز پهلو می‌افتند؛ در پالتوی کوتاه، جیب افقی زیر خط دکمه‌ها جا نمی‌شود.';
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
