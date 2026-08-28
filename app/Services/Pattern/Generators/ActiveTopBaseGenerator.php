<?php

namespace App\Services\Pattern\Generators;

/**
 * لایه ترکیبی بالاتنه‌های ورزشی.
 *
 * ActiveBaseGenerator قرارداد پارچه و ابزارِ لبه را دارد؛ این لایه ترتیبِ ساخت
 * را یک‌جا می‌نویسد تا یک مدلِ تازه فقط چهار انتخاب داشته باشد:
 *
 *   • در کدام نیمهٔ پارچه است — ضریبِ کشسانی. زیر یک یعنی کوچک‌تر از بدن بریده
 *     می‌شود و همان تنگی است که لباس را سرِ جایش نگه می‌دارد.
 *   • خطِ بالا بند است یا سرشانهٔ کامل.
 *   • آستین دارد یا نه.
 *   • لبه‌های باز با نوارِ کشباف تمام می‌شوند یا با نوارِ فشاریِ زیرسینه.
 *
 * دو چیز در همهٔ این مدل‌ها ثابت است و این‌جا یک‌بار نوشته شده: حلقهٔ ورزشی
 * بالاتر و تنگ‌تر از حلقهٔ معمولی است تا با بالا بردنِ دست زیر بغل باز نماند، و
 * دمِ پشت از دمِ جلو بلندتر است تا با خم شدنِ کمر بیرون نماند — و آن بلندی فقط
 * روی مرکز پشت می‌نشیند، نه روی درزِ پهلو، وگرنه دو لبهٔ دوخته‌شونده هم‌اندازه
 * نمی‌مانند.
 */
abstract class ActiveTopBaseGenerator extends ActiveBaseGenerator
{
    /** کاربردهایی که یک بالاتنهٔ ورزشی می‌تواند داشته باشد. */
    protected const USES = [
        'gym' => 'باشگاه',
        'run' => 'دویدن',
        'yoga' => 'یوگا و کشش',
        'team' => 'ورزش تیمی',
        'outdoor' => 'بیرون و سرما',
    ];

    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، stretch، length، back_drop، strap، sleeve
     * (none|set_in)، sleeve_length، armhole_lift، armhole_narrow،
     * neck_width_extra، neck_depth_extra، back_neck_depth_extra، binding،
     * binding_height، binding_ratio، band (نوارِ فشاریِ زیرسینه)، band_height،
     * band_ratio، inner (لایهٔ دوم)، mesh_back، use، extra، notes.
     *
     * @return array<string, mixed>
     */
    abstract protected function active(): array;

    public function label(): string
    {
        return (string) ($this->active()['title'] ?? 'بالاتنه ورزشی');
    }

    public function paramsSchema(): array
    {
        $a = $this->active();

        $extra = array_merge(
            $this->stretchParam((float) ($a['stretch'] ?? 0.9)),
            [
                ...(isset($a['height']) ? [
                    'body_height' => [
                        'label' => 'بلندی از خط زیر بغل', 'min' => 10, 'max' => 30, 'step' => 0.5,
                        'default' => (float) $a['height'], 'unit' => 'سانتی‌متر',
                        'hint' => 'لبهٔ پایین این‌قدر پایین‌تر از خط زیر بغل می‌ایستد.',
                    ],
                ] : [
                    'body_length' => [
                        'label' => 'بلندی از خط کمر', 'min' => -20, 'max' => 30, 'step' => 1,
                        'default' => (float) ($a['length'] ?? 10), 'unit' => 'سانتی‌متر',
                        'hint' => 'عدد منفی یعنی کراپ.',
                    ],
                ]),
                'back_drop' => [
                    'label' => 'بلندتر بودن پشت', 'min' => 0, 'max' => 10, 'step' => 0.5,
                    'default' => (float) ($a['back_drop'] ?? 3), 'unit' => 'سانتی‌متر',
                ],
                'armhole_lift' => [
                    'label' => 'بالا آمدن حلقه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => (float) ($a['armhole_lift'] ?? 1.5), 'unit' => 'سانتی‌متر',
                ],
                'armhole_narrow' => [
                    'label' => 'تنگ‌تر شدن حلقه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                    'default' => (float) ($a['armhole_narrow'] ?? 2), 'unit' => 'سانتی‌متر',
                ],
                'binding_height' => [
                    'label' => 'بلندی نوار لبه', 'min' => 1, 'max' => 4, 'step' => 0.25,
                    'default' => (float) ($a['binding_height'] ?? 1.75), 'unit' => 'سانتی‌متر',
                ],
                'binding_ratio' => [
                    'label' => 'کوتاهی نوار لبه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                    'default' => (float) ($a['binding_ratio'] ?? 0.88),
                ],
                'garment_use' => [
                    'label' => 'کاربرد', 'type' => 'select',
                    'default' => (string) ($a['use'] ?? 'gym'), 'options' => self::USES,
                ],
            ],
        );

        if (($a['strap'] ?? null) !== null) {
            $extra['strap_width'] = [
                'label' => 'پهنای بند سرشانه', 'min' => 2, 'max' => 10, 'step' => 0.5,
                'default' => (float) $a['strap'], 'unit' => 'سانتی‌متر',
            ];
        }

        if ((string) ($a['sleeve'] ?? 'none') !== 'none') {
            $extra['sleeve_length'] = [
                'label' => 'بلندی آستین', 'min' => 8, 'max' => 70, 'step' => 1,
                'default' => (float) ($a['sleeve_length'] ?? 22), 'unit' => 'سانتی‌متر',
            ];
            $extra['cap_ease'] = [
                'label' => 'آزادی سرآستین', 'min' => 0, 'max' => 4, 'step' => 0.25,
                'default' => 1.0, 'unit' => 'سانتی‌متر',
            ];
        }

        if (($a['band'] ?? false) === true) {
            $extra['band_height'] = [
                'label' => 'بلندی نوار زیرسینه', 'min' => 2.5, 'max' => 9, 'step' => 0.5,
                'default' => (float) ($a['band_height'] ?? 4.5), 'unit' => 'سانتی‌متر',
            ];
            $extra['band_ratio'] = [
                'label' => 'کوتاهی نوار زیرسینه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                'default' => (float) ($a['band_ratio'] ?? 0.8),
                'hint' => 'کوتاه‌تر از بقیهٔ لبه‌ها، چون همهٔ وزن روی همین نوار است.',
            ];
        }

        return $this->activeSchema(
            array_merge([
                'neck_width_extra' => (float) ($a['neck_width_extra'] ?? 1.5),
                'front_neck_depth_extra' => (float) ($a['neck_depth_extra'] ?? 1),
                'back_neck_depth' => 2,
                'armhole_depth_extra' => 0,
            ], (array) ($a['schema'] ?? [])),
            array_merge($extra, (array) ($a['extra'] ?? [])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $a = $this->active();
        $prefix = (string) ($a['prefix'] ?? static::key()).'-';

        $stretch = $this->readStretch($params, (float) ($a['stretch'] ?? 0.9));
        $ease = $this->activeEase($ease, $measurements, $stretch);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $sleeved = (string) ($a['sleeve'] ?? 'none') !== 'none';

        // لباسِ کوتاه (سوتین، کراپ) بلندی‌اش را از خطِ زیر بغل می‌گیرد نه از خطِ
        // کمر: روی بدنِ کوتاه‌قد، همان «ده سانتی‌متر بالای کمر» قطعه را تا زیر
        // بغل بالا می‌کشد و چیزی برای دوختن نمی‌ماند
        $length = isset($a['height'])
            ? $this->lengthToBottom($g, (float) $this->param($params, 'body_height', $a['height']), 12.0)
            : (float) $this->param($params, 'body_length', $a['length'] ?? 10);
        $backDrop = (float) $this->param($params, 'back_drop', $a['back_drop'] ?? 3);

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
        ];

        // آستین‌دار سرشانهٔ کامل و حلقهٔ سالم می‌خواهد؛ بندِ باریک و حلقهٔ بالا
        // آمده فقط روی مدلِ بی‌آستین معنا دارد
        if (! $sleeved) {
            $strap = (float) $this->param($params, 'strap_width', $a['strap'] ?? 5);

            $shared = array_merge($shared, [
                'shoulder_extra' => $this->strapShoulder($g, $strap),
                'across_extra' => -(float) $this->param($params, 'armhole_narrow', $a['armhole_narrow'] ?? 2),
                'armhole_drop' => -(float) $this->param($params, 'armhole_lift', $a['armhole_lift'] ?? 1.5),
            ]);
        }

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => $prefix.'front',
            'name' => 'تنه جلو',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => $prefix.'back',
            'name' => 'تنه پشت',
            'neck_depth_extra' => (float) ($a['back_neck_depth_extra'] ?? 1.0),
            'meta' => ($a['mesh_back'] ?? false) === true
                ? ['fabric' => 'mesh', 'notes' => ['از پارچهٔ توری بریده می‌شود؛ درزها را نواردوزی کنید تا لبهٔ توری نساید.']]
                : [],
        ]));

        $back = $this->lengthenCenterHem($back, $backDrop);

        $pieces = [$front, $back];

        if ($sleeved) {
            $sleeves = $this->sleevePieces($measurements, $ease, $params, [
                'armhole_length' => (float) ($front['meta']['armhole_length'] ?? 0)
                    + (float) ($back['meta']['armhole_length'] ?? 0),
                'length' => max(10.0, (float) $this->param($params, 'sleeve_length', $a['sleeve_length'] ?? 22)),
                'prefix' => $prefix,
                'sleeve_name' => 'آستین',
            ]);

            foreach ($sleeves as $index => $sleeve) {
                $sleeves[$index]['meta']['girth_role'] = 'sleeve';
            }

            $pieces = array_merge($pieces, $sleeves);
        }

        $height = (float) $this->param($params, 'binding_height', $a['binding_height'] ?? 1.75);
        $ratio = (float) $this->param($params, 'binding_ratio', $a['binding_ratio'] ?? 0.88);

        if (($a['binding'] ?? true) === true) {
            $pieces[] = $this->edgeBinding(
                $prefix.'neck-binding',
                'نوار خط بالا',
                $this->edgeTotal([$front, $back], 'neck'),
                $height,
                $ratio,
            );

            if (! $sleeved) {
                $pieces[] = $this->edgeBinding(
                    $prefix.'armhole-binding',
                    'نوار حلقه',
                    $this->edgeTotal([$front, $back], 'armhole') / 2,
                    $height,
                    $ratio,
                    2,
                );
            }
        }

        if (($a['inner'] ?? false) === true) {
            $pieces[] = $this->innerLayer($front, 'لایه دوم جلو (شلف)');
        }

        if (($a['band'] ?? false) === true) {
            $under = ((float) ($measurements['under_bust'] ?? (($measurements['bust'] ?? 92) - 14))) * $stretch;

            $pieces[] = $this->compressionBand(
                $prefix.'band',
                'نوار زیرسینه',
                $under,
                (float) $this->param($params, 'band_height', $a['band_height'] ?? 4.5),
                (float) $this->param($params, 'band_ratio', $a['band_ratio'] ?? 0.8),
            );
        }

        $notes = array_merge($this->compressionNotes($stretch), (array) ($a['notes'] ?? []));

        if ($backDrop > 0.1) {
            $notes[] = 'دم پشت '.$this->fa(round($backDrop, 1))
                .' سانتی‌متر بلندتر از جلوست؛ هنگام خم شدن کمر بیرون نمی‌ماند.';
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        foreach ($pieces as $index => $piece) {
            $pieces[$index]['meta']['active'] = [
                'model' => (string) ($a['prefix'] ?? static::key()),
                'use' => (string) $this->param($params, 'garment_use', $a['use'] ?? 'gym'),
                'stretch' => round($stretch, 3),
            ];
        }

        return $this->finishBlock($pieces, $g);
    }
}
