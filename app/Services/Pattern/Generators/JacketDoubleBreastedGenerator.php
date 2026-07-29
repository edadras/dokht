<?php

namespace App\Services\Pattern\Generators;

/**
 * ژاکت دوردیف‌دکمه.
 *
 * کتِ کوتاهِ رسمی با دو ردیف دکمه. آنچه این مدل را از کت تک‌ردیفه جدا می‌کند یک
 * عدد است: هم‌پوشانی جلو. در تک‌ردیفه هم‌پوشانی فقط جای دکمه است (دو تا سه
 * سانتی‌متر) ولی اینجا باید آن‌قدر باشد که ردیف بیرونی دکمه رویش بنشیند — یعنی
 * هشت تا ده سانتی‌متر. همین یک عدد سه چیز دیگر را هم عوض می‌کند: سجاف جلو باید
 * پهن‌تر شود، برگردان یقه پایین‌تر می‌شکند و لبهٔ جلو دو لا پارچه دارد پس لایی
 * جداگانه می‌خواهد.
 *
 * تنه با درز مرکز پشت و ساسون کمر فرم می‌گیرد، آستین دوتکهٔ خیاطی است و همه‌چیز
 * آستر می‌خورد.
 */
class JacketDoubleBreastedGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'jacket_double_breasted';
    }

    public function label(): string
    {
        return 'ژاکت دوردیف‌دکمه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 4,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 3,
                'shoulder_slope' => 4,
                'waist_dart_share' => 0.5,
            ]),
            $this->garmentLengthParam(24, 8, 60),
            // «تعداد دکمه» را برمی‌داریم: در دوردیفه شمار دکمه از تعداد ردیف
            // درمی‌آید و پارامتر بی‌اثر، کاربر را گمراه می‌کند.
            $this->without($this->openingParam('button', 8), ['front_opening', 'buttons']),
            $this->collarParam('turn', [
                'turn' => 'یقه برگردان (نوک‌دار)',
                'shawl' => 'یقه شالی',
            ], 8),
            $this->sleeveParam('two_piece', 60),
            [
                'button_rows' => [
                    'label' => 'تعداد دکمه در هر ردیف', 'min' => 2, 'max' => 5, 'step' => 1, 'default' => 3,
                ],
                'hem_flare' => [
                    'label' => 'باز شدن لبه پایین در هر پهلو', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                ],
                'back_vent' => [
                    'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->pocketParam(true, 15, 16),
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 12.0, ['waist' => -2.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $stand = max(5.0, (float) $this->param($params, 'button_stand', 8));
        $length = (float) $this->param($params, 'length', 24);
        $rows = (int) $this->param($params, 'button_rows', 3);
        $vent = (float) $this->param($params, 'back_vent', 16);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'db-jacket-',
            'grow' => 0.0,
            'shape' => 'fitted',
            'stand' => $stand,
            'back_seam' => true,
            'bust_dart' => true,
            'buttons' => 0, // ردیف‌ها را خودمان می‌گذاریم، نه یک ردیف مرکزی
            'front_name' => 'تنه جلوی ژاکت (با هم‌پوشانی دوردیفه)',
            'back_name' => 'تنه پشت ژاکت (درز مرکزی)',
            'facing_width' => max(10.0, $stand + 6),
            'collar_break' => 6,
            'lining' => true,
            'lining_options' => ['length' => max(0.0, $length - 2), 'back_pleat' => 2.0],
        ]);

        $top = $g['bust_y'] + 2;
        $bottom = max($top + 4, $g['front_waist_y'] - 2);
        $pieces[0] = $this->markDoubleBreast($pieces[0], $stand, $top, $bottom, $rows);

        // لبهٔ دو لای جلو خودش لایی می‌خواهد؛ سجاف به تنهایی آن را نگه نمی‌دارد
        $pieces[0]['meta']['interfacing'] = true;

        $notes = [
            'هم‌پوشانی جلو '.$this->fa(round($stand, 1)).' سانتی‌متر است تا ردیف بیرونی دکمه رویش بنشیند؛ '
                .'سجاف جلو هم به همان نسبت پهن‌تر بریده شده است.',
        ];

        if ($vent > 1) {
            $pieces[1]['meta']['back_vent'] = round($vent, 2);
            $pieces[1]['meta']['notes'] = array_merge($pieces[1]['meta']['notes'] ?? [], [
                'چاک مرکز پشت به بلندی '.$this->fa(round($vent, 1)).' سانتی‌متر باز می‌ماند؛ پهنای زیرِ چاک چهار سانتی‌متر است.',
            ]);
        }

        if ($this->flag($params, 'pocket', true)) {
            $pieces = array_merge($pieces, $this->weltPocketSet(
                (float) $this->param($params, 'pocket_width', 15),
                (float) $this->param($params, 'pocket_height', 16),
                ['prefix' => 'db-jacket-', 'welt' => 3.0],
            ));
        }

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
