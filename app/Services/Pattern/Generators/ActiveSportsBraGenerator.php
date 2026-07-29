<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * سوتین ورزشی.
 *
 * چسبان‌ترین قطعهٔ این دسته و تنها قطعه‌ای که کارش «نگه‌داشتن» است نه «پوشاندن».
 * سه تصمیم کل الگو را می‌سازد:
 *
 *   ۱. آزادی منفی و زیاد. با ضریب کشسانی پیش‌فرض ۰٫۸ الگو بیست درصد کوچک‌تر از
 *      بدن بریده می‌شود؛ سوتین ورزشیِ اندازهٔ بدن، هنگام دویدن کار نمی‌کند.
 *   ۲. نوار زیرسینه از بقیهٔ لبه‌ها محسوس‌تر کوتاه می‌شود، چون تمام وزن روی همان
 *      نوار است نه روی بندها. برای همین کوتاهی‌اش پارامتر جداگانه دارد.
 *   ۳. لایهٔ دوم جلو (شلف) اختیاری نیست بلکه بخشی از سازه است: دو لایهٔ نازک
 *      باهم بیشتر از یک لایهٔ ضخیم نگه می‌دارند و جیب فنجان هم روی همان می‌نشیند.
 *
 * پشتِ کمانی بندها را به وسط پشت می‌آورد؛ آن‌وقت بند از شانه لیز نمی‌خورد ولی
 * لباس دیگر از سر رد نمی‌شود مگر پارچه واقعاً کش بیاید — و همین در یادداشت آمده.
 */
class ActiveSportsBraGenerator extends ActiveBaseGenerator
{
    public static function key(): string
    {
        return 'active_sports_bra';
    }

    public function label(): string
    {
        return 'سوتین ورزشی';
    }

    public function paramsSchema(): array
    {
        return $this->activeSchema(
            ['neck_width_extra' => 1.5, 'armhole_depth_extra' => 0],
            array_merge(
                $this->stretchParam(0.8, 'سوتین ورزشی باید محسوس کوچک‌تر از بدن بریده شود؛ ۰٫۸ یعنی بیست درصد کوچک‌تر.'),
                $this->elasticParam(0.9),
                [
                    'body_height' => [
                        'label' => 'بلندی از خط زیر بغل', 'min' => 10, 'max' => 30, 'step' => 0.5,
                        'default' => 17, 'unit' => 'سانتی‌متر',
                        'hint' => 'لبهٔ پایین این‌قدر پایین‌تر از خط زیر بغل می‌ایستد.',
                    ],
                    'band_height' => [
                        'label' => 'بلندی نوار زیرسینه', 'min' => 2.5, 'max' => 9, 'step' => 0.5,
                        'default' => 4.5, 'unit' => 'سانتی‌متر',
                    ],
                    'band_ratio' => [
                        'label' => 'کوتاهی نوار زیرسینه', 'min' => 0.7, 'max' => 0.95, 'step' => 0.01,
                        'default' => 0.8,
                        'hint' => 'کوتاه‌تر از بقیهٔ لبه‌ها، چون همهٔ وزن روی همین نوار است.',
                    ],
                    'neck_drop' => [
                        'label' => 'گودی یقه جلو', 'min' => 2, 'max' => 18, 'step' => 0.5,
                        'default' => 7, 'unit' => 'سانتی‌متر',
                    ],
                    'strap_width' => [
                        'label' => 'پهنای بند سرشانه', 'min' => 2, 'max' => 8, 'step' => 0.5,
                        'default' => 4, 'unit' => 'سانتی‌متر',
                    ],
                    'racer_back' => [
                        'label' => 'پشت کمانی', 'type' => 'toggle', 'default' => true,
                    ],
                    'cup_pocket' => [
                        'label' => 'جیب فنجان سینه', 'type' => 'toggle', 'default' => true,
                    ],
                ],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $stretch = $this->readStretch($params, 0.8);
        $ease = $this->activeEase($ease, $measurements, $stretch);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $racer = $this->flag($params, 'racer_back', true);
        $strap = (float) $this->param($params, 'strap_width', 4) * ($racer ? 0.8 : 1.0);
        $length = $this->lengthToBottom($g, (float) $this->param($params, 'body_height', 17), 12.0);
        $bottom = (float) $g['side_waist_y'] + $length;

        $shared = [
            'shape' => 'straight',
            'length' => $length,
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => $this->strapShoulder($g, $strap),
            'across_extra' => -3.0,
            'armhole_drop' => 2.5,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'sportsbra-front',
            'name' => 'تنه جلو',
            // یقه هرگز آن‌قدر گود نمی‌شود که به لبهٔ پایین برسد
            'neck_depth_extra' => max(0.0, min(
                (float) $this->param($params, 'neck_drop', 7),
                $bottom - (float) $g['front_neck_depth'] - 8,
            )),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'sportsbra-back',
            'name' => 'تنه پشت',
            'neck_depth_extra' => $racer ? 3.0 : 1.0,
        ]));

        $pieces = [];

        foreach ([$front, $back] as $piece) {
            $piece = $this->edgeElastic($piece, 'neck', 'کش خط بالا', $params);
            $piece = $this->edgeElastic($piece, 'armhole', 'کش حلقه', $params);
            $pieces[] = $piece;
        }

        $pieces[] = $this->innerLayer($front, 'لایه دوم جلو (شلف)');

        $under = ((float) ($measurements['under_bust'] ?? (($measurements['bust'] ?? 92) - 14))) * $stretch;

        $pieces[] = $this->compressionBand(
            'sportsbra-band',
            'نوار زیرسینه',
            $under,
            (float) $this->param($params, 'band_height', 4.5),
            (float) $this->param($params, 'band_ratio', 0.8),
        );

        if ($racer) {
            $pieces[] = $this->racerStrap($strap);
        }

        if ($this->flag($params, 'cup_pocket', true)) {
            $pieces[] = $this->cupPocket($measurements, $stretch);
        }

        $notes = array_merge($this->compressionNotes($stretch), [
            'نوار زیرسینه '.$this->fa(round((1 - (float) $this->param($params, 'band_ratio', 0.8)) * 100))
                .' درصد کوتاه‌تر از دور زیرسینه است؛ نگه‌دارندهٔ اصلی همین نوار است، نه بندها.',
            $racer
                ? 'پشت کمانی است: بندها در وسط پشت به هم می‌رسند، پس از شانه لیز نمی‌خورند ولی لباس فقط با کشیدن پارچه از سر رد می‌شود.'
                : 'بندهای پشت جدا از هم‌اند؛ اگر روی شانه لیز خوردند، پهنای بند را زیاد کنید.',
        ]);

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], $notes);

        return $this->finishBlock($pieces, $g);
    }

    /** بند پشت کمانی: نواری که دو بند پشت را در وسط پشت به هم می‌رساند. */
    protected function racerStrap(float $strap): array
    {
        $width = max(3.0, $strap * 2);
        $length = 14.0;

        return $this->piece([
            'code' => 'sportsbra-racer',
            'name' => 'بند پشت کمانی',
            'cut_quantity' => 1,
            'on_fold' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::curve($width, 2.5, $width * 0.7, -0.6),
                Geometry::point($width, $length - 2.5),
                Geometry::curve(0, $length, $width * 0.7, $length + 0.6),
            ],
            'grainline' => $this->grainline($width * 0.5, 1.5, $length - 1.5),
            'meta' => [
                'part' => 'strap',
                'edges' => ['strap', 'side', 'strap', 'default'],
                'fold_edges' => [3],
                'girth_role' => 'trim',
                'notes' => [
                    'روی تای مرکز پشت بریده می‌شود؛ دو سرش به بندهای سرشانه دوخته می‌شود.',
                ],
            ],
        ]);
    }

    /** جیب فنجان سینه: یک لایهٔ دیگر که یک طرفش باز می‌ماند. */
    protected function cupPocket(array $m, float $stretch): array
    {
        $width = max(20.0, ((float) ($m['bust'] ?? 92)) * $stretch * 0.34);

        return $this->bandPiece('sportsbra-cup-pocket', 'جیب فنجان سینه', $width, 9.0, [
            'cut' => 1,
            'part' => 'lining',
            'layer' => 'lining',
            'meta' => [
                'girth_role' => 'lining',
                'notes' => [
                    'روی لایهٔ دوم جلو دوخته می‌شود و لبهٔ بالایش باز می‌ماند تا فنجان درآید و لباس شسته شود.',
                ],
            ],
        ]);
    }
}
