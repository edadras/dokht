<?php

namespace App\Services\Pattern\Generators;

/**
 * شنل.
 *
 * تنها لباس رویی این خانواده که آستین ندارد — و «نداشتن آستین» یعنی نداشتن حلقهٔ
 * آستین، نه اینکه آستین را نگذاشته باشیم. اگر شنل را با حلقهٔ گودِ لباس آستین‌دار
 * درفت کنید، زیر بغل باز می‌ماند و باد از آن‌جا تو می‌آید.
 *
 * پس درفت شنل از پایه فرق دارد: پارچه از نوک سرشانه با یک منحنی نرم به پهنای
 * کامل می‌رسد و از همان‌جا تا دم باز می‌شود. دست از یک شکاف عمودی روی پنل جلو
 * بیرون می‌آید، نه از حلقه.
 *
 * یک نکتهٔ ریز ولی تعیین‌کننده: شیب سرشانهٔ جلو و پشت عمداً یکی گرفته شده است.
 * شنل جز درز پهلو درزی ندارد؛ اگر شیب دو طرف فرق کند، همان یک درز هم‌اندازه
 * درنمی‌آید و شنل کج می‌افتد.
 */
class CoatCapeGenerator extends OuterwearBaseGenerator
{
    public static function key(): string
    {
        return 'coat_cape';
    }

    public function label(): string
    {
        return 'شنل';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerwearSchema([
                'armhole_depth_extra' => 3,
                'neck_width_extra' => 2,
                'front_neck_depth_extra' => 3,
                'shoulder_slope' => 4,
            ], [], 'regular', 'knit'),
            $this->garmentLengthParam(70, 25, 120),
            [
                'hem_flare' => [
                    'label' => 'باز شدن دم شنل در هر پهلو', 'min' => 8, 'max' => 60, 'step' => 1,
                    'default' => 30, 'unit' => 'سانتی‌متر',
                    'hint' => 'شنل فرمش را از همین باز شدن می‌گیرد؛ عدد کم یعنی شنلی که مثل کیسه می‌افتد.',
                ],
                'button_stand' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'buttons' => [
                    'label' => 'تعداد دکمهٔ جلو', 'min' => 0, 'max' => 8, 'step' => 1, 'default' => 3,
                ],
                'arm_slit' => [
                    'label' => 'بلندی شکاف حلقهٔ دست', 'min' => 12, 'max' => 40, 'step' => 1,
                    'default' => 24, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقهٔ ایستاده', 'min' => 3, 'max' => 12, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
            ],
            $this->liningParam(true),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->outerwearEase($ease, $params, 19.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 70);
        $flare = (float) $this->param($params, 'hem_flare', 30);
        $stand = (float) $this->param($params, 'button_stand', 5);
        $buttons = (int) $this->param($params, 'buttons', 3);
        $slit = (float) $this->param($params, 'arm_slit', 24);

        $shared = ['length' => $length, 'flare' => $flare, 'slit' => $slit, 'prefix' => 'cape-'];

        $front = $this->capePanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'code' => 'cape-front',
            'name' => 'تنه جلوی شنل',
            'meta' => ['front_opening' => 'button', 'button_stand' => round($stand, 2)],
        ]));

        $back = $this->capePanel($g, array_merge($shared, [
            'side' => 'back',
            'on_fold' => true,
            'cut' => 1,
            'code' => 'cape-back',
            'name' => 'تنه پشت شنل',
        ]));

        [$front, $back] = $this->walkSideSeams($front, $back);

        $front = $this->markButtons($front, $stand, $g['front_neck_depth'] + 2, $g['bust_y'] + 6, $buttons);

        $halfNeck = $this->neckOf([$front, $back]);
        $bottom = $g['side_waist_y'] + $length;

        $pieces = [
            $front,
            $back,
            $this->standCollarPiece($halfNeck, (float) $this->param($params, 'collar_height', 7), ['prefix' => 'cape-']),
            $this->frontFacingPiece($g, $stand, $bottom, ['prefix' => 'cape-', 'width' => max(8.0, $stand + 4)]),
            $this->backNeckFacingPiece($g, ['prefix' => 'cape-']),
        ];

        // نوار شکاف دست: لبهٔ شکاف باید تمیز و محکم شود، وگرنه سرِ شکاف پاره می‌شود
        $pieces[] = $this->bandPiece('cape-slit-binding', 'نوار لبهٔ شکاف دست', $slit + 4, 4, [
            'cut' => 4, 'part' => 'facing',
            'meta' => [
                'interfacing' => true,
                'notes' => ['دو نوار برای هر شکاف؛ سرِ بالا و پایین شکاف را با چند کوک برگشتی محکم کنید.'],
            ],
        ]);

        if ($this->flag($params, 'lining', true)) {
            foreach ([$front, $back] as $panel) {
                $liner = $panel;
                $liner['code'] = $panel['code'].'-lining';
                $liner['name'] = 'آستر '.$panel['name'];
                $liner['layer'] = 'lining';
                $liner['drills'] = [];
                $liner['meta']['girth_role'] = 'lining';
                $liner['meta']['part'] = 'lining';
                $liner['meta']['notions'] = [];
                $liner['meta']['notes'] = ['هم‌اندازهٔ قطعهٔ رو؛ شکاف دست روی آستر هم بریده می‌شود.'];
                $pieces[] = $liner;
            }
        }

        $notes = [
            'شنل حلقهٔ آستین ندارد و این عمدی است: پارچه از نوک سرشانه آزاد می‌ریزد و '
                .'دست از شکاف عمودی روی جلو بیرون می‌آید.',
            'شکاف حلقهٔ دست '.$this->fa(round($slit, 1)).' سانتی‌متر است و روی الگو فقط خط نشانه دارد؛ '
                .'مغزی و کیسهٔ جیب برایش درفت نشده، چون شکاف شنل جیب نیست و فقط لبه‌اش تمیزدوزی می‌شود.',
            'شیب سرشانهٔ جلو و پشت یکی گرفته شده است تا درز پهلو — تنها درز این لباس — هم‌اندازه دربیاید.',
        ];

        return $this->finishOuterwear($pieces, $g, $ease, $params, $notes);
    }
}
