<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه پپلوم‌دار.
 *
 * بالاتنه روی خط کمر تمام می‌شود و یک «پپلوم» — دامنِ کوتاهِ پُر — به همان خط
 * دوخته می‌شود. لبه بالای پپلوم دقیقاً یک‌چهارم دور کمر است (همان اندازه‌ای که
 * لبه کمرِ بالاتنه پس از بستن ساسون‌ها دارد)، پس درز کمر بدون کش آمدن بسته
 * می‌شود و همه پُری روی لبه پایین می‌آید.
 */
class BodicePeplumGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_peplum';
    }

    public function label(): string
    {
        return 'بالاتنه پپلوم‌دار';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(['waist_dart_share' => 0.7]),
            [
                'peplum_style' => [
                    'label' => 'فرم پپلوم', 'type' => 'select', 'default' => 'circle',
                    'options' => ['circle' => 'کلوش (دایره‌ای)', 'flare' => 'اریب ساده', 'gather' => 'چین‌دار'],
                ],
                'peplum_length' => [
                    'label' => 'بلندی پپلوم', 'min' => 8, 'max' => 45, 'step' => 1,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                ],
                'fullness' => [
                    'label' => 'پُری پپلوم', 'min' => 0.3, 'max' => 1, 'step' => 0.05,
                    'default' => 0.7,
                    'hint' => 'یک یعنی کلوش کامل؛ عددهای کمتر پپلوم را آرام‌تر می‌کند.',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                ],
                'back_zip' => [
                    'label' => 'زیپ مرکز پشت', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $zip = $this->flag($params, 'back_zip', true);
        $style = (string) $this->param($params, 'peplum_style', 'circle');
        $length = (float) $this->param($params, 'peplum_length', 22);
        $fullness = (float) $this->param($params, 'fullness', 0.7);

        $pieces = [
            $this->bodyPanel($g, [
                'side' => 'front',
                'shape' => 'waist',
                'bust_dart' => $this->flag($params, 'bust_dart', true),
                'code' => 'peplum-bodice-front',
                'name' => 'بالاتنه جلو',
            ]),
            $this->bodyPanel($g, [
                'side' => 'back',
                'shape' => 'waist',
                'on_fold' => ! $zip,
                'cut' => $zip ? 2 : 1,
                'mirror' => $zip,
                'code' => 'peplum-bodice-back',
                'name' => 'بالاتنه پشت',
                'meta' => ['back_zip' => $zip],
            ]),
        ];

        foreach (['front', 'back'] as $side) {
            $isFront = $side === 'front';

            $pieces[] = $style === 'circle'
                ? $this->circleSkirtPanel($g, [
                    'side' => $side,
                    'length' => $length,
                    'fullness' => $fullness,
                    'code' => 'peplum-'.$side,
                    'name' => $isFront ? 'پپلوم جلو' : 'پپلوم پشت',
                    'girth_role' => 'trim',
                    'cut' => $isFront || ! $zip ? 1 : 2,
                    'on_fold' => $isFront || ! $zip,
                ])
                : $this->lowerPanel($g, [
                    'side' => $side,
                    'shape' => 'flare',
                    'top_width' => $g['quarter_waist'],
                    'top_y' => $g['side_waist_y'],
                    'length' => $length,
                    'gather' => $style === 'gather' ? round($g['quarter_waist'] * $fullness, 2) : 0.0,
                    'flare' => $style === 'flare' ? round($length * $fullness * 0.9, 2) : 0.0,
                    'top_tag' => 'waist',
                    'on_fold' => $isFront || ! $zip,
                    'cut' => $isFront || ! $zip ? 1 : 2,
                    'code' => 'peplum-'.$side,
                    'name' => $isFront ? 'پپلوم جلو' : 'پپلوم پشت',
                    'girth_role' => 'trim',
                ]);
        }

        return $this->finishBlock($pieces, $g);
    }
}
