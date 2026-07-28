<?php

namespace App\Services\Pattern\Generators;

/**
 * کرست چندتکه (بالاتنه پنل‌بندی‌شده).
 *
 * نیم‌تنه جلو و نیم‌تنه پشت هرکدام به چند پنل عمودی تقسیم می‌شوند؛ جای ساسون را
 * همین درزها می‌گیرند. درز هر دو پنل همسایه از یک خط شکسته ساخته می‌شود، پس طول
 * دو لبه دقیقاً برابر است و جمع پهنای پنل‌ها روی خط سینه، کمر و باسن همان
 * یک‌چهارم دور همان خط می‌ماند. هر درز جای «فنر» (بونینگ) دارد.
 */
class BodiceCorsetGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_corset';
    }

    public function label(): string
    {
        return 'کرست چندتکه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema([], ['bodice_length_extra']),
            [
                'front_panels' => [
                    'label' => 'تعداد پنل نیم‌جلو', 'min' => 2, 'max' => 6, 'step' => 1, 'default' => 3,
                    'hint' => 'روی لباس کامل دو برابر این عدد پنل جلو دیده می‌شود.',
                ],
                'back_panels' => [
                    'label' => 'تعداد پنل نیم‌پشت', 'min' => 2, 'max' => 6, 'step' => 1, 'default' => 3,
                ],
                'top_line' => [
                    'label' => 'جابه‌جایی لبه بالای کرست', 'min' => -8, 'max' => 10, 'step' => 0.5,
                    'default' => 0, 'unit' => 'سانتی‌متر',
                    'hint' => 'مثبت یعنی لبه بالا پایین‌تر می‌آید (بازتر).',
                ],
                'length' => [
                    'label' => 'بلندی از خط کمر', 'min' => 2, 'max' => 30, 'step' => 0.5,
                    'default' => 11, 'unit' => 'سانتی‌متر',
                ],
                'center_back_open' => [
                    'label' => 'مرکز پشت باز (بندی یا زیپ)', 'type' => 'toggle', 'default' => true,
                ],
                'lining' => [
                    'label' => 'آستر پنل‌ها ساخته شود', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'length', 11);
        $top = (float) $this->param($params, 'top_line', 0);
        $open = $this->flag($params, 'center_back_open', true);

        $shared = ['top_extra' => $top, 'length' => $length];

        $pieces = array_merge(
            $this->corsetPanels($g, array_merge($shared, [
                'side' => 'front',
                'panels' => (int) $this->param($params, 'front_panels', 3),
                'center_fold' => true,
            ])),
            $this->corsetPanels($g, array_merge($shared, [
                'side' => 'back',
                'panels' => (int) $this->param($params, 'back_panels', 3),
                'center_fold' => ! $open,
            ])),
        );

        if ($open) {
            $pieces[] = $this->bandPiece('corset-lacing-guard', 'پشت‌بند مرکز پشت', $length + $g['bust_y'] + 8, 8, [
                'cut' => 1, 'part' => 'facing',
                'meta' => ['interfacing' => true, 'notes' => ['زیر رج بندها یا زیپ مرکز پشت دوخته می‌شود.']],
            ]);
        }

        if ($this->flag($params, 'lining', true)) {
            foreach ($this->corsetPanels($g, array_merge($shared, [
                'side' => 'front',
                'panels' => (int) $this->param($params, 'front_panels', 3),
                'center_fold' => true,
                'layer' => 'lining',
                'prefix' => 'lining-',
            ])) as $panel) {
                $panel['name'] = 'آستر '.$panel['name'];
                $panel['meta']['girth_role'] = 'lining';
                $panel['meta']['part'] = 'lining';
                $pieces[] = $panel;
            }

            foreach ($this->corsetPanels($g, array_merge($shared, [
                'side' => 'back',
                'panels' => (int) $this->param($params, 'back_panels', 3),
                'center_fold' => ! $open,
                'layer' => 'lining',
                'prefix' => 'lining-',
            ])) as $panel) {
                $panel['name'] = 'آستر '.$panel['name'];
                $panel['meta']['girth_role'] = 'lining';
                $panel['meta']['part'] = 'lining';
                $pieces[] = $panel;
            }
        }

        return $this->finishBlock($pieces, $g);
    }
}
