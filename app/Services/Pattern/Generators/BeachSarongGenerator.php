<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * ساروَنگ (لُنگ ساحلی).
 *
 * ساده‌ترین الگوی کاتالوگ و همان‌جا که ساده بودن، بی‌دقتی را پنهان می‌کند: یک
 * ذوزنقه که دور بدن می‌پیچد و دو سرش گره می‌خورد. سه عدد کل کار را تعیین
 * می‌کنند و هر سه باید نوشته شوند، وگرنه پارچه یا کم می‌آید یا لُنگ باز می‌شود:
 *
 *   دور پیچش — دور باسن به‌علاوه آزادی. اگر کم باشد، ساروَنگ اصلاً دور بدن
 *   نمی‌رسد؛ گره هم نمی‌تواند جبرانش کند.
 *   هم‌پوشانی — چقدر پارچه روی خودش می‌افتد. کمتر از بیست سانتی‌متر یعنی با هر
 *   قدم، پهلو باز می‌شود.
 *   سرِ گره — چقدر پارچه در خود گره خورده می‌شود. گره پارچه‌ای دست‌کم بیست
 *   سانتی‌متر از هر سر می‌خورد؛ اگر این را حساب نکنید، پهنای تمام‌شده چهل
 *   سانتی‌متر کمتر از چیزی می‌شود که فکر می‌کردید.
 *
 * لبه پایین بازتر از لبه بالاست تا هنگام راه رفتن پا آزاد باشد.
 */
class BeachSarongGenerator extends BeachBaseGenerator
{
    public static function key(): string
    {
        return 'beach_sarong';
    }

    public function label(): string
    {
        return 'ساروَنگ (لُنگ ساحلی)';
    }

    public function paramsSchema(): array
    {
        return [
            'girth_ease' => [
                'label' => 'آزادی روی دور باسن', 'min' => 0, 'max' => 20, 'step' => 1,
                'default' => 4, 'unit' => 'سانتی‌متر',
                'hint' => 'ساروَنگ روی مایو پوشیده می‌شود؛ کمی آزادی لازم دارد.',
            ],
            'overlap' => [
                'label' => 'هم‌پوشانی', 'min' => 12, 'max' => 60, 'step' => 1,
                'default' => 26, 'unit' => 'سانتی‌متر',
                'hint' => 'کمتر از بیست سانتی‌متر، با هر قدم پهلو را باز می‌گذارد.',
            ],
            'knot' => [
                'label' => 'سرِ گره در هر طرف', 'min' => 10, 'max' => 40, 'step' => 1,
                'default' => 20, 'unit' => 'سانتی‌متر',
                'hint' => 'پارچه‌ای که در خودِ گره خورده می‌شود و دیگر دور بدن نمی‌پیچد.',
            ],
            'length' => [
                'label' => 'بلندی از خط بستن', 'min' => 35, 'max' => 130, 'step' => 1,
                'default' => 95, 'unit' => 'سانتی‌متر',
                'hint' => 'از جایی که گره می‌خورد تا لبه پایین.',
            ],
            'hem_flare' => [
                'label' => 'باز شدن لبه پایین در هر طرف', 'min' => 0, 'max' => 20, 'step' => 1,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'sewn_ties' => [
                'label' => 'بند دوخته‌شده به‌جای گره پارچه‌ای', 'type' => 'toggle', 'default' => false,
                'hint' => 'با بند، دیگر پارچه‌ای در گره خورده نمی‌شود و پهنای لُنگ کمتر می‌شود.',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $hip = $this->m($measurements, 'hip', 98) + $this->ease($ease, 'hip', 0);
        $girth = $hip + (float) $this->param($params, 'girth_ease', 4);
        $overlap = (float) $this->param($params, 'overlap', 26);
        $sewn = $this->flag($params, 'sewn_ties', false);
        $knot = $sewn ? 0.0 : (float) $this->param($params, 'knot', 20);
        $length = max(20.0, (float) $this->param($params, 'length', 95));
        $flare = (float) $this->param($params, 'hem_flare', 8);

        $width = $girth + $overlap + (2 * $knot);
        $hemWidth = $width + (2 * $flare);

        $outline = [
            Geometry::point($flare, 0),
            Geometry::point($flare + $width, 0),
            Geometry::point($hemWidth, $length),
            Geometry::point(0, $length),
        ];

        $markers = [
            $this->marker('wrap', 'بخشی که دور بدن می‌پیچد', $flare + $knot, 0, $flare + $knot + $girth, 0),
        ];

        $notches = [
            $this->notch($flare + $knot, 0, 0, 'سر گره سمت راست', 'sarong_knot'),
            $this->notch($flare + $knot + $girth, 0, 0, 'سر هم‌پوشانی', 'sarong_overlap'),
        ];

        if ($knot > 0.5) {
            $markers[] = $this->marker('knot', 'سرِ گره', $flare, 0, $flare + $knot, 0);
        }

        $notes = [
            'پهنای کل پارچه '.$this->fa(round($width)).' سانتی‌متر است: '
                .$this->fa(round($girth)).' سانتی‌متر دور پیچش، '
                .$this->fa(round($overlap)).' سانتی‌متر هم‌پوشانی'
                .($knot > 0.5 ? '، و '.$this->fa(round($knot)).' سانتی‌متر برای هر سرِ گره' : '').'.',
            'دور تمام‌شده پس از بستن '.$this->fa(round($girth))
                .' سانتی‌متر است، یعنی دور باسن به‌علاوه آزادی.',
            'لبه پایین '.$this->fa(round($hemWidth)).' سانتی‌متر است؛ '
                .$this->fa(round(2 * $flare)).' سانتی‌متر بازتر از لبه بالا تا پا آزاد باشد.',
            $knot > 0.5
                ? 'دو سرِ پارچه روی پهلو به هم گره می‌خورند؛ گره خودش پارچه می‌خورد و در پهنا حساب شده است.'
                : 'بندهای دوخته‌شده دور کمر می‌پیچند و گره می‌خورند؛ پارچه‌ای در گره خورده نمی‌شود.',
            'هر چهار لبه تودوزی باریک می‌شوند؛ ساروَنگ درز ندارد.',
        ];

        $sarong = $this->piece([
            'code' => 'sarong',
            'name' => 'ساروَنگ (یک‌تکه)',
            'cut_quantity' => 1,
            'outline' => $outline,
            'grainline' => $this->grainline($flare + ($width * 0.5), 2, $length - 2),
            'markers' => $markers,
            'notches' => $notches,
            'meta' => [
                'part' => 'wrap_skirt',
                'edges' => ['waist', 'side', 'hem', 'side'],
                'fold_edges' => [],
                'girth_role' => 'skirt',
                'girth' => ['waist' => round($girth, 2)],
                'girth_factor' => 1,
                'finished_waist' => round($girth, 2),
                'overlap' => round($overlap, 2),
                'knot' => round($knot, 2),
                'fabric_width' => round($width, 2),
                'hem_width' => round($hemWidth, 2),
                'notes' => array_merge($notes, $this->beachNotes()),
            ],
        ]);

        $pieces = [$sarong];

        if ($sewn) {
            $pieces[] = $this->bandPiece('sarong-tie', 'بند ساروَنگ', ($girth / 2) + 45, 6, [
                'cut' => 2, 'part' => 'belt', 'fold_line' => true,
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => [
                        'دو بند روی دو سر لبه بالا دوخته می‌شوند؛ یکی دور کمر می‌رود و روی دیگری گره می‌خورد.',
                    ],
                ],
            ]);
        }

        return $this->finish($pieces);
    }
}
