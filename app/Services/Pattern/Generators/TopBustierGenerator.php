<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * بوستیه (تاپ کرستی).
 *
 * تاپی بدون بند که به‌جای کش، با پنل‌بندی و تیغهٔ فنر سر جایش می‌ماند. فرقش با
 * بندو همین است: بندو با کشسانی می‌چسبد، بوستیه با شکل.
 *
 * پنل‌بندی کار تزیینی نیست: هر درز عمودی، انحنای بدن را در خودش حل می‌کند، پس
 * بوستیه به‌جای ساسون، درز دارد و همین است که به تن می‌چسبد. تعداد پنل هرچه
 * بیشتر، فرم دقیق‌تر و کار سنگین‌تر.
 *
 * آستر و لایی این‌جا اختیاری نیستند بلکه پیش‌فرض روشن‌اند؛ بوستیهٔ تک‌لایه روی
 * تن نمی‌ایستد.
 */
class TopBustierGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_bustier';
    }

    public function label(): string
    {
        return 'بوستیه (تاپ کرستی)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            [
                'front_panels' => [
                    'label' => 'تعداد پنل نیم‌جلو', 'min' => 2, 'max' => 4, 'step' => 1, 'default' => 3,
                ],
                'back_panels' => [
                    'label' => 'تعداد پنل نیم‌پشت', 'min' => 2, 'max' => 4, 'step' => 1, 'default' => 2,
                ],
            ],
            $this->topLineParam(1, 'جای خط بالا نسبت به زیر بغل'),
            [
                'top_shape' => [
                    'label' => 'شکل خط بالا', 'type' => 'select', 'default' => 'sweetheart',
                    'options' => ['sweetheart' => 'قلبی', 'straight' => 'صاف'],
                ],
                'boning' => [
                    'label' => 'تیغهٔ فنر روی درزها', 'type' => 'toggle', 'default' => true,
                ],
                'lining' => [
                    'label' => 'آستر پنل‌ها ساخته شود', 'type' => 'toggle', 'default' => true,
                ],
                'closure' => [
                    'label' => 'بست مرکز پشت', 'type' => 'select', 'default' => 'zip',
                    'options' => ['zip' => 'زیپ مخفی', 'hook' => 'قزن ردیفی', 'lacing' => 'بند کشی (لِیس)'],
                ],
            ],
        ), length: 6);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $grow = $this->fitGrow($params, ['fitted' => -1.5, 'regular' => -0.5, 'loose' => 0.5]);

        /*
         * فرم باید در خودِ پنل‌ها بنشیند، نه فقط در مهرِ «دور هدف».
         *
         * پیش‌تر $grow حساب می‌شد و تنها به finishBlock می‌رفت: الگو می‌گفت دورش
         * فلان است ولی پنل‌ها همیشه یک اندازه بودند. یعنی بوستیهٔ جذب و گشاد یک
         * چیز بودند، و مهرِ دور هدف هم دروغ می‌گفت. corsetPanels پهنایش را از
         * quarter_bust می‌گیرد، پس فرم باید پیش از آن در اندازه‌ها بنشیند —
         * روی *دورِ کامل*، چون blockMetrics خودش بر چهار تقسیم می‌کند.
         */
        $ease = array_merge($ease, [
            'bust' => $this->ease($ease, 'bust', 6) + ($grow * 4),
            'waist' => $this->ease($ease, 'waist', 4) + ($grow * 4),
            'hip' => $this->ease($ease, 'hip', 6) + ($grow * 4),
        ]);

        $g = $this->blockMetrics($measurements, $ease, $params);

        $drop = (float) $this->param($params, 'top_drop', 1);

        /*
         * بوستیه از خطِ بالا شروع می‌شود، نه از سرشانه، پس کوتاه‌کردنش زودتر از
         * هر تاپِ دیگری به ته می‌رسد: روی تنِ کوتاه (کودک) و با قدِ کراپ، پنل‌ها
         * به یکی‌دو سانتی‌متر می‌رسیدند — نواری که نه راستای پارچه رویش جا
         * می‌شود نه می‌شود دوختش.
         *
         * پس مثل باقیِ تاپ‌ها از کفِ مشترک رد می‌شویم، با فاصلهٔ بیشتر چون
         * لبهٔ بالای بوستیه خودش پایین‌تر از سینه است.
         */
        $length = $this->bodyLength($params, $g, 6, clearance: 12.0 + $drop);

        $lacing = $this->param($params, 'closure', 'zip') === 'lacing';
        $shared = ['top_extra' => -$drop, 'length' => $length];

        $panels = array_merge(
            $this->corsetPanels($g, array_merge($shared, [
                'side' => 'front',
                'panels' => (int) $this->param($params, 'front_panels', 3),
                'center_fold' => true,
            ])),
            $this->corsetPanels($g, array_merge($shared, [
                'side' => 'back',
                'panels' => (int) $this->param($params, 'back_panels', 2),
                'center_fold' => $lacing,
            ])),
        );

        $boning = $this->flag($params, 'boning', true);
        $sweetheart = $this->param($params, 'top_shape', 'sweetheart') === 'sweetheart';
        $cut = [];

        foreach ($panels as $index => $panel) {
            // گودی قلبی با پایین‌آوردن خط بالای پنلِ مرکز جلو ساخته می‌شود؛ همان
            // کاری که روی کاغذ هم می‌کنند. بقیه پنل‌ها دست‌نخورده می‌مانند تا دو
            // لبهٔ همسایه هم‌اندازه بمانند.
            $isCenterFront = $sweetheart
                && ! empty($panel['on_fold'])
                && str_contains((string) ($panel['meta']['part'] ?? ''), 'front');

            if ($isCenterFront) {
                [, $minY] = Geometry::bounds($panel['outline']);
                $dip = min(4.5, max(1.5, $length * 0.35));
                $panel = $this->cutTop($panel, [
                    'center' => $minY + $dip,
                    'side' => $minY + 0.2,
                    'shape' => 'straight',
                ]);
            }

            if ($boning) {
                $panel['meta']['boning'] = true;
            }

            $panel['meta']['interfacing'] = true;
            $panel['meta']['sleeveless'] = true;
            $cut[] = $panel;
        }

        $pieces = $cut;

        if (! $lacing) {
            $pieces[] = $this->bandPiece('bustier-back-guard', 'پشت‌بند مرکز پشت', $length + $g['bust_y'], 7, [
                'cut' => 1, 'part' => 'facing',
                'meta' => ['interfacing' => true, 'notes' => ['زیر زیپ یا ردیف قزن مرکز پشت دوخته می‌شود.']],
            ]);
        }

        if ($this->flag($params, 'lining', true)) {
            foreach ($cut as $panel) {
                if (($panel['meta']['girth_role'] ?? '') !== 'shell') {
                    continue;
                }

                $liner = $panel;
                $liner['code'] = $panel['code'].'-lining';
                $liner['name'] = 'آستر '.$panel['name'];
                $liner['layer'] = 'lining';
                $liner['meta']['girth_role'] = 'lining';
                $liner['meta']['part'] = 'lining';
                $liner['meta']['interfacing'] = false;
                $liner['meta']['boning'] = false;
                $pieces[] = $liner;
            }
        }

        $closure = (string) $this->param($params, 'closure', 'zip');

        $notes = [
            $this->finishNote($params, ['خط بالا']),
            ['type' => 'info', 'text' => 'هر درز عمودی انحنای بدن را در خودش حل می‌کند؛ برای همین بوستیه ساسون ندارد و به‌جایش پنل دارد.'],
            ['type' => 'info', 'text' => match ($closure) {
                'hook' => 'مرکز پشت با ردیف قزن بسته می‌شود؛ در صورت مواد حساب شده است.',
                'lacing' => 'مرکز پشت با بند کشی بسته می‌شود، پس دو تا سه سانتی‌متر فاصله بین دو لبه طبیعی است و در الگو حساب شده.',
                default => 'مرکز پشت زیپ مخفی می‌خورد.',
            }],
        ];

        if ($boning) {
            $notes[] = ['type' => 'info', 'text' => 'روی هر درز عمودی یک تیغهٔ فنر می‌نشیند؛ بلندی هر تیغه دو سانتی‌متر کمتر از خود درز بریده می‌شود تا سرش بیرون نزند.'];
        }

        $notes[] = ['type' => 'warning', 'text' => 'بوستیهٔ تک‌لایه روی تن نمی‌ایستد؛ آستر و لایی را خاموش نکنید مگر اینکه پارچه خودش سفت باشد.'];

        return $this->finishBlock($this->noted($pieces, $notes), $g, $grow);
    }
}
