<?php

namespace App\Services\Pattern\Generators;

/**
 * لایه ترکیبی لباس کودک.
 *
 * ChildBaseGenerator چهار تفاوتِ بدنِ کودک را نگه می‌دارد؛ این لایه روی آن
 * می‌نشیند تا یک مدلِ تازهٔ بچگانه فقط «شخصیت» خودش را بنویسد و درفت را دوباره
 * ننویسد. سه فرم پوشش داده می‌شود و هر سه از همان مسیرهای آزموده‌شده می‌گذرند:
 *
 *   top   → بالاتنه (تی‌شرت، پیراهن، سویشرت) با outerGarment
 *   dress → همان با بلندی بیشتر و دامنِ باز
 *   pants → پاچه با legPanel و کمرِ کشی
 *
 * سنجشِ «رد شدن یقه از سر» خودکار است: هر لباسی که جلویش باز نمی‌شود، یقه‌اش با
 * دور سر سنجیده می‌شود و اگر کم آمد، عرض و گودی یقه باز و در نهایت چاکِ پشت
 * پیشنهاد می‌شود. لباسِ کودکی که از سر رد نشود، لباس نیست.
 */
abstract class ChildGarmentBaseGenerator extends ChildBaseGenerator
{
    /** کاربردهایی که یک لباسِ بچگانه می‌تواند داشته باشد. */
    protected const USES = [
        'daily' => 'روزمره',
        'play' => 'بازی',
        'school' => 'مدرسه',
        'party' => 'مهمانی',
        'sleep' => 'خواب',
        'outdoor' => 'بیرون و سرما',
    ];

    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، form (top|dress|pants)، length، shape، grow،
     * play، growth، opening، buttons، collar، collar_height، sleeve،
     * sleeve_length، neck_band، band_ratio، hem_flare، facing، pocket، knit،
     * rib، use، extra، schema، panel، notes.
     *
     * برای form=pants: rise، thigh_ease، knee_ease، hem_ease، leg_length،
     * elastic_ratio.
     *
     * @return array<string, mixed>
     */
    abstract protected function child(): array;

    public function label(): string
    {
        return (string) ($this->child()['title'] ?? 'لباس کودک');
    }

    public function paramsSchema(): array
    {
        $c = $this->child();
        $form = (string) ($c['form'] ?? 'top');

        if ($form === 'pants') {
            return array_merge(
                $this->childEaseSchema((float) ($c['play'] ?? 2), (float) ($c['growth'] ?? 2)),
                [
                    'length_extra' => [
                        'label' => 'تغییر قد شلوار', 'min' => -40, 'max' => 12, 'step' => 1,
                        'default' => 0, 'unit' => 'سانتی‌متر',
                    ],
                    'knee_ease' => [
                        'label' => 'آزادی دور زانو', 'min' => 2, 'max' => 26, 'step' => 1,
                        'default' => (float) ($c['knee_ease'] ?? 9), 'unit' => 'سانتی‌متر',
                    ],
                    'hem_ease' => [
                        'label' => 'آزادی دم پا', 'min' => 2, 'max' => 30, 'step' => 1,
                        'default' => (float) ($c['hem_ease'] ?? 8), 'unit' => 'سانتی‌متر',
                    ],
                    'elastic_ratio' => [
                        'label' => 'کوتاهی کش کمر', 'min' => 0.7, 'max' => 1, 'step' => 0.01,
                        'default' => (float) ($c['elastic_ratio'] ?? 0.82),
                    ],
                    'garment_use' => [
                        'label' => 'کاربرد', 'type' => 'select',
                        'default' => (string) ($c['use'] ?? 'daily'), 'options' => self::USES,
                    ],
                ],
                (array) ($c['extra'] ?? []),
            );
        }

        $schema = array_merge(
            $this->childSchema((array) ($c['schema'] ?? [])),
            $this->childEaseSchema((float) ($c['play'] ?? 1.5), (float) ($c['growth'] ?? 2)),
            $this->garmentLengthParam(
                (float) ($c['length'] ?? 12),
                (float) ($c['length_min'] ?? 4),
                (float) ($c['length_max'] ?? 90),
                $form === 'dress' ? 'بلندی دامن از خط کمر' : 'بلندی از خط کمر',
            ),
            $this->sleeveParam(
                (string) ($c['sleeve'] ?? 'set_in'),
                (float) ($c['sleeve_length'] ?? 24),
                ['none' => 'بدون آستین', 'set_in' => 'آستین (کوتاه یا بلند)'],
            ),
            [
                'garment_use' => [
                    'label' => 'کاربرد', 'type' => 'select',
                    'default' => (string) ($c['use'] ?? 'daily'), 'options' => self::USES,
                ],
            ],
        );

        if ((string) ($c['opening'] ?? 'closed') !== 'closed') {
            $schema = array_merge($schema, $this->openingParam(
                (string) $c['opening'],
                (float) ($c['button_stand'] ?? 2),
            ));
            $schema['buttons']['default'] = (int) ($c['buttons'] ?? 4);
        }

        if ((string) ($c['collar'] ?? 'none') !== 'none') {
            $schema = array_merge($schema, $this->collarParam(
                (string) $c['collar'],
                ['none' => 'بدون یقه', 'stand' => 'یقه ایستاده', 'turn' => 'یقه برگردان', 'hood' => 'کلاه'],
                (float) ($c['collar_height'] ?? 5),
            ));
        }

        if (($c['neck_band'] ?? false) === true) {
            $schema['neck_band'] = ['label' => 'نوار یقه کشباف', 'type' => 'toggle', 'default' => true];
            $schema['band_ratio'] = [
                'label' => 'نسبت کوتاهی نوار یقه', 'min' => 0.75, 'max' => 1, 'step' => 0.05,
                'default' => (float) ($c['band_ratio'] ?? 0.88),
            ];
        }

        if (($c['hem_flare'] ?? 0) > 0) {
            $schema['hem_flare'] = [
                'label' => 'باز شدن لبه پایین', 'min' => 0, 'max' => 40, 'step' => 1,
                'default' => (float) $c['hem_flare'], 'unit' => 'سانتی‌متر',
            ];
        }

        return array_merge($schema, (array) ($c['extra'] ?? []));
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $c = $this->child();

        return (string) ($c['form'] ?? 'top') === 'pants'
            ? $this->childPants($measurements, $ease, $params, $c)
            : $this->childBody($measurements, $ease, $params, $c);
    }

    /**
     * بالاتنه یا پیراهن.
     *
     * @param  array<string, mixed>  $c
     * @return array<int, array<string, mixed>>
     */
    protected function childBody(array $measurements, array $ease, array $params, array $c): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $prefix = (string) ($c['prefix'] ?? static::key()).'-';
        $opening = (string) $this->param($params, 'front_opening', $c['opening'] ?? 'closed');

        // لباسی که جلویش باز می‌شود از سر پوشیده نمی‌شود؛ headClearance خودش
        // این را می‌فهمد و یقه را زورکی پهن نمی‌کند
        $clearance = $this->headClearance($g, $measurements, [
            'required' => $opening === 'closed',
            'margin' => 2.0,
            'max_depth' => (float) ($c['max_neck_depth'] ?? 4.0),
        ]);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => $prefix,
            'grow' => $grow,
            'shape' => (string) ($c['shape'] ?? 'straight'),
            'length' => (float) $this->param($params, 'length', $c['length'] ?? 12),
            'opening' => $opening,
            'stand' => (float) ($c['button_stand'] ?? 2),
            'hem_flare' => (float) $this->param($params, 'hem_flare', $c['hem_flare'] ?? 0),
            'collar' => (string) $this->param($params, 'collar', $c['collar'] ?? 'none'),
            'collar_height' => (float) $this->param($params, 'collar_height', $c['collar_height'] ?? 5),
            'buttons' => (int) $this->param($params, 'buttons', $c['buttons'] ?? 4),
            'facing' => (bool) ($c['facing'] ?? ($opening !== 'closed')),
            'bust_dart' => false,
            'panel' => array_merge([
                'waist_dart' => false,
                'neck_width_extra' => $clearance['width_extra'],
                'meta' => ['knit' => (bool) ($c['knit'] ?? false)],
            ], (array) ($c['panel'] ?? [])),
            'front' => ['neck_depth_extra' => $clearance['front_depth_extra']],
            'sleeve' => (array) ($c['sleeve_options'] ?? []),
        ]);

        if (($c['neck_band'] ?? false) === true && $this->flag($params, 'neck_band', true)) {
            $pieces[] = $this->neckBandPiece($this->neckOf(array_slice($pieces, 0, 2)), [
                'prefix' => $prefix,
                'ratio' => (float) $this->param($params, 'band_ratio', $c['band_ratio'] ?? 0.88),
                'height' => 3,
            ]);
        }

        if (($c['pocket'] ?? false) === true) {
            $pieces = array_merge($pieces, $this->pocketSet(
                array_merge(['pocket' => true], $params),
                ['prefix' => $prefix],
            ));
        }

        $pieces = $this->stampHeadClearance($pieces, $clearance, $g, ['notion' => 'snap']);
        $pieces = $this->childUse($pieces, $params, $c);

        return $this->finishBlock($this->childNoted($pieces, (array) ($c['notes'] ?? [])), $g, $grow);
    }

    /**
     * شلوار یا شلوارک کودک.
     *
     * کمر همیشه کشی است: کودک بستِ سخت را باز نمی‌کند و بستِ سخت هم زیرِ شکمِ
     * جلو آمدهٔ او جا نمی‌افتد.
     *
     * @param  array<string, mixed>  $c
     * @return array<int, array<string, mixed>>
     */
    protected function childPants(array $measurements, array $ease, array $params, array $c): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->childGrow($params);
        $prefix = (string) ($c['prefix'] ?? static::key()).'-';

        $legEase = $this->legEase($ease, $grow);
        $legParams = array_merge($params, ['back_darts' => 0]);

        if (isset($c['leg_length'])) {
            $legParams['length_extra'] = (float) $this->param($params, 'length_extra', 0)
                - (float) $c['leg_length'];
        }

        $pieces = [];

        foreach (['front', 'back'] as $side) {
            $leg = $this->legPanel($measurements, $legEase, $legParams, [
                'side' => $side,
                'code' => $prefix.'leg-'.$side,
                'name' => $side === 'front' ? 'پاچه جلو' : 'پاچه پشت',
            ]);

            $leg['meta']['girth_role'] = 'bottom';
            $pieces[] = $leg;
        }

        $ratio = min(1.0, max(0.7, (float) $this->param($params, 'elastic_ratio', $c['elastic_ratio'] ?? 0.82)));
        $pieces[] = $this->elasticWaistPiece($prefix.'waist-elastic', $measurements, $ease, $ratio, 3);

        if (($c['rib'] ?? false) === true) {
            $hem = 0.0;

            foreach ($pieces as $piece) {
                if (($piece['meta']['girth_role'] ?? '') === 'bottom') {
                    $hem += (float) ($piece['meta']['hem_width'] ?? 0);
                }
            }

            $pieces[] = $this->ribBandPiece(
                $prefix.'leg-rib',
                'نوار کشباف دم پاچه',
                max(12.0, $hem),
                ['height' => 4, 'ratio' => 0.88, 'cut' => 2, 'on_fold' => false, 'part' => 'cuff'],
            );
        }

        $pieces = $this->childUse($pieces, $params, $c);

        return $this->finishBlock($this->childNoted($pieces, array_merge(
            ['کمر کشی است؛ لبه کمر به اندازه دور باسن بریده می‌شود تا شلوار از باسن رد شود.'],
            (array) ($c['notes'] ?? []),
        )), $g, $grow, ['shell', 'bottom']);
    }

    /**
     * ثبت مدل و کاربرد روی همه قطعه‌ها.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, mixed>  $c
     * @return array<int, array<string, mixed>>
     */
    protected function childUse(array $pieces, array $params, array $c): array
    {
        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['child'] = [
                'model' => (string) ($c['prefix'] ?? static::key()),
                'form' => (string) ($c['form'] ?? 'top'),
                'use' => (string) $this->param($params, 'garment_use', $c['use'] ?? 'daily'),
            ];
        }

        return $pieces;
    }
}
