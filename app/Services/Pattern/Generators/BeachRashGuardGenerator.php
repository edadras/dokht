<?php

namespace App\Services\Pattern\Generators;

/**
 * راش‌گارد.
 *
 * تنها مدل این خانواده که با پارچه کشی و **آزادی منفی** بریده می‌شود. اسمش هم از
 * همین می‌آید: لباسی که جلوی ساییدگی پوست روی تخته موج‌سواری را می‌گیرد، و
 * لباسی که روی تن شل باشد این کار را نمی‌کند — در آب باد می‌کند، بالا می‌رود و
 * زیرش می‌ساید.
 *
 * پس سه تصمیم، این الگو را از یک تی‌شرت آستین‌بلند جدا می‌کند:
 *
 *   ۱. الگو کوچک‌تر از بدن بریده می‌شود و خودش هم این را در meta.stretch_ratio
 *      اعلام می‌کند؛ ضریب کشسانی پارچه پارامتر اصلی است، نه یک تنظیم پیشرفته.
 *   ۲. آستین تا مچ می‌آید و لبه‌های باز (دم لباس و مچ) کش می‌خورند تا لباس در آب
 *      بالا نرود.
 *   ۳. یقه ایستاده با زیپ کوتاه روی مرکز جلو، تا هم گردن از آفتاب در امان بماند
 *      و هم پوشیدن لباسِ تنگ ممکن باشد.
 */
class BeachRashGuardGenerator extends BeachBaseGenerator
{
    /** راش‌گارد تنها مدل این خانواده است که کوچک‌تر از بدن بریده می‌شود. */
    protected bool $negativeEase = true;

    public static function key(): string
    {
        return 'beach_rash_guard';
    }

    public function label(): string
    {
        return 'راش‌گارد';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->stretchSchema(0.88),
            $this->baseSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 0.5,
                'front_neck_depth_extra' => 1,
                'back_neck_depth' => 1.5,
                'armhole_depth_extra' => 1,
            ], [
                'shoulder_slope', 'neck_width_extra', 'front_neck_depth_extra',
                'back_neck_depth', 'armhole_depth_extra',
            ]),
            $this->garmentLengthParam(18, 4, 34, 'بلندی از خط کمر'),
            $this->sleeveParam('set_in', 58, [
                'set_in' => 'آستین بلند تا مچ',
                'none' => 'بدون آستین',
            ]),
            [
                'cap_ease' => [
                    'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 3, 'step' => 0.25,
                    'default' => 0.75, 'unit' => 'سانتی‌متر',
                    'hint' => 'روی پارچه کشی، آزادی سرآستین باید کم باشد؛ زیاد که باشد سر آستین چروک می‌افتد.',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه ایستاده', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'zip_length' => [
                    'label' => 'بلندی زیپ مرکز جلو', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 16, 'unit' => 'سانتی‌متر',
                    'hint' => 'زیپ کوتاه؛ راش‌گارد از سر پوشیده می‌شود و زیپ فقط یقه را باز می‌کند.',
                ],
                'hem_elastic' => [
                    'label' => 'کش دم لباس و مچ آستین', 'type' => 'toggle', 'default' => true,
                    'hint' => 'بدون کش، راش‌گارد در آب بالا می‌رود و شکم را باز می‌گذارد.',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->stretch($params);
        $ease = $this->stretchEase($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 18);
        $zip = (float) $this->param($params, 'zip_length', 16);
        $wantsElastic = $this->flag($params, 'hem_elastic', true);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'waist_dart' => false,
            'bottom_tag' => 'hem',
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'rash-guard-front',
            'name' => 'تنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'rash-guard-back',
            'name' => 'تنه پشت',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        if ($zip > 4) {
            $front['markers'][] = $this->marker(
                'zip',
                'زیپ کوتاه مرکز جلو',
                0,
                $g['front_neck_depth'],
                0,
                $g['front_neck_depth'] + $zip,
            );
            $front['meta']['zip_length'] = round($zip, 1);
            $front = $this->addNotion(
                $front,
                ['type' => 'zip', 'label' => 'زیپ کوتاه مرکز جلو (جدانشدنی)', 'count' => 1, 'length' => round($zip, 1)],
                'زیپ روی چاکی به بلندی '.$this->fa(round($zip))
                    .' سانتی‌متر روی مرکز جلو می‌نشیند؛ چاک با نوار پشتی محکم می‌شود تا پارچه کشی کش نیاید.',
            );
        }

        $armhole = $this->armholeOf([$front, $back]);
        $halfNeck = $this->neckOf([$front, $back]);
        $style = (string) $this->param($params, 'sleeve_style', 'set_in');

        // دم آستین راش‌گارد باید از مچ تنگ‌تر باشد، وگرنه در آب بالا می‌رود؛
        // پیش‌فرض شش سانتی‌متر آزادی مچِ آستین معمولی این‌جا معنا ندارد.
        $sleeves = $this->sleeveSet(
            $measurements,
            $ease,
            array_merge($params, ['hem_ease' => -($this->m($measurements, 'wrist', 16.5) * (1 - $stretch))]),
            $armhole,
            $g,
            ['prefix' => 'rash-guard-', 'sleeve_name' => 'آستین بلند راش‌گارد'],
        );

        if ($wantsElastic) {
            $front = $this->elastic($front, 'hem', 'کش دم لباس (جلو)', $params);
            $back = $this->elastic($back, 'hem', 'کش دم لباس (پشت)', $params);

            foreach ($sleeves as $index => $sleeve) {
                if (($sleeve['meta']['part'] ?? '') === 'sleeve') {
                    $sleeves[$index] = $this->elastic($sleeve, 'hem', 'کش مچ آستین', $params);
                }
            }
        }

        $pieces = array_merge([$front, $back], $sleeves);

        $pieces[] = $this->standCollarPiece($halfNeck, (float) $this->param($params, 'collar_height', 4), [
            'prefix' => 'rash-guard-',
            'name' => 'یقه ایستاده',
        ]);

        if ($zip > 4) {
            $pieces[] = $this->bandPiece('rash-guard-zip-stay', 'نوار پشتِ زیپ', $zip + 4, 6, [
                'cut' => 2, 'part' => 'placket',
                'meta' => [
                    'girth_role' => 'trim',
                    'interfacing' => true,
                    'notes' => [
                        'از پارچه کم‌کشش یا با لایی چسب کشی بریده می‌شود؛ بدون آن، لبه چاک زیر زیپ موج می‌خورد.',
                    ],
                ],
            ]);
        }

        if ($style === 'none') {
            $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'rash-guard-', 'height' => 3]);
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $this->stretchNotes($params, [
            'دور سینه تمام‌شده '.$this->fa(round($g['bust'], 1)).' سانتی‌متر است در برابر بدنِ '
                .$this->fa(round($this->m($measurements, 'bust', 92), 1)).' سانتی‌متری؛ یعنی کوچک‌تر از بدن.',
            'درزها را با دوخت تخت (فلت‌لاک) بدوزید؛ درز برجسته زیر تخته موج‌سواری پوست را می‌ساید.',
        ]));

        $pieces[0]['meta']['stretch'] = round($stretch, 3);

        return $this->finishBlock($pieces, $g, 0.0);
    }
}
