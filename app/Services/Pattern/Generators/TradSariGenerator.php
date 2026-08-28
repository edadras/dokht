<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * ساری.
 *
 * خودِ ساری بریده نمی‌شود — یک تختهٔ پارچهٔ پنج‌ونیم تا نُه متری است که دور بدن
 * پیچیده می‌شود. پس چیزی که الگو باید بدهد دو قطعهٔ دیگر است:
 *
 *   چولی      بالاتنهٔ کوتاهِ قالبِ تن با آستینِ کوتاه، تا زیر سینه.
 *   زیردامنی  دامنِ راستِ بنددارِ ساده که ساری روی آن سنجاق می‌شود.
 *
 * و خودِ تخته، با اندازه و جای «پالو» (سرِ تزیینیِ روی شانه) به‌عنوان یک قطعهٔ
 * مستطیل که فقط لبه‌دوزی می‌خواهد. بی زیردامنی، ساری روی تن نمی‌ایستد.
 */
class TradSariGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_sari';
    }

    public function label(): string
    {
        return 'ساری (چولی و زیردامنی)';
    }

    public static function group(): string
    {
        return 'traditional';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->outerSchema([
                'shoulder_slope' => 4,
                'neck_width_extra' => 1.5,
                'front_neck_depth_extra' => 3,
                'back_neck_depth' => 6,
                'armhole_depth_extra' => 1,
                'waist_dart_share' => 0.6,
            ]),
            [
                'choli_length' => [
                    'label' => 'بلندی چولی از سرشانه', 'min' => 28, 'max' => 46, 'step' => 1,
                    'default' => 36, 'unit' => 'سانتی‌متر',
                    'hint' => 'چولی تا زیر سینه می‌آید؛ میان آن و زیردامنی کمر باز می‌ماند.',
                ],
                'choli_sleeve' => [
                    'label' => 'بلندی آستین چولی', 'min' => 0, 'max' => 30, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'petticoat_length' => [
                    'label' => 'قد زیردامنی', 'min' => 85, 'max' => 115, 'step' => 1,
                    'default' => 100, 'unit' => 'سانتی‌متر',
                ],
                'petticoat_flare' => [
                    'label' => 'باز شدن زیردامنی در هر پهلو', 'min' => 0, 'max' => 20, 'step' => 1,
                    'default' => 8, 'unit' => 'سانتی‌متر',
                ],
                'sari_length' => [
                    'label' => 'طول تخته ساری', 'min' => 500, 'max' => 900, 'step' => 10,
                    'default' => 630, 'unit' => 'سانتی‌متر',
                ],
                'sari_width' => [
                    'label' => 'پهنای تخته ساری', 'min' => 100, 'max' => 125, 'step' => 1,
                    'default' => 115, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = 0.0;
        $length = (float) $this->param($params, 'choli_length', 36);

        $pieces = $this->outerGarment($measurements, $ease, $params, $g, [
            'prefix' => 'choli-',
            'grow' => $grow,
            'shape' => 'fitted',
            'length' => $length,
            'opening' => 'button',
            'stand' => 1.5,
            'facing' => false,
            'bust_dart' => true,
            'front_name' => 'تنه جلوی چولی',
            'back_name' => 'تنه پشت چولی',
            'panel' => ['waist_dart' => true, 'bottom_tag' => 'hem'],
            'sleeve' => [
                'sleeve_name' => 'آستین چولی',
                'length' => (float) $this->param($params, 'choli_sleeve', 14),
            ],
        ]);

        $waist = $this->m($measurements, 'waist', 74);
        $petti = (float) $this->param($params, 'petticoat_length', 100);
        $flare = (float) $this->param($params, 'petticoat_flare', 8);

        $pieces[] = $this->piece([
            'code' => 'sari-petticoat',
            'name' => 'زیردامنی',
            'cut_quantity' => 2,
            'on_fold' => true,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($waist / 4 + 1, 0),
                Geometry::point($waist / 4 + 1 + $flare, $petti),
                Geometry::point(0, $petti),
            ],
            'grainline' => $this->grainline($waist / 8, 3, $petti - 3),
            'meta' => [
                'part' => 'skirt_front',
                'side' => 'front',
                'edges' => ['waist', 'side', 'hem', 'side'],
                'fold_edges' => [3],
                'girth' => ['waist'],
                'girth_factor' => 0.25,
                'notes' => [
                    'نیفهٔ بندی روی خط کمر؛ ساری روی همین زیردامنی سنجاق می‌شود.',
                    'رنگش باید به رنگ ساری بخورد، چون از زیرِ پارچهٔ نازک دیده می‌شود.',
                ],
            ],
        ]);

        $pieces[] = $this->bandPiece('sari-petticoat-casing', 'نیفه بند زیردامنی', $waist + 8, 5, [
            'cut' => 1, 'part' => 'waistband',
            'meta' => [
                'edges' => ['waist', 'side', 'waist', 'side'],
                'girth_role' => 'trim',
                'notes' => ['بندِ کشیدنی از داخلِ نیفه رد می‌شود.'],
            ],
        ]);

        $sari = (float) $this->param($params, 'sari_length', 630);
        $wide = (float) $this->param($params, 'sari_width', 115);

        $pieces[] = $this->piece([
            'code' => 'sari-drape',
            'name' => 'تخته ساری',
            'cut_quantity' => 1,
            'mirror' => false,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($sari, 0),
                Geometry::point($sari, $wide),
                Geometry::point(0, $wide),
            ],
            'grainline' => $this->grainline($sari * 0.5, 3, $wide - 3),
            'markers' => [
                $this->marker('pallu', 'پالو (سرِ روی شانه)', $sari - 110, 0, $sari, 0),
                $this->marker('pleats', 'ناحیهٔ پیلی‌های جلو', 90, 0, 220, 0),
            ],
            'meta' => [
                'part' => 'drape',
                'edges' => ['hem', 'default', 'hem', 'default'],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'cover',
                'notes' => [
                    'بریده نمی‌شود؛ فقط چهار لبه‌اش تمیز می‌شود.',
                    'یک لبهٔ بلند حاشیهٔ پهن دارد و همان لبه پایین می‌افتد.',
                    'پالو سرِ تزیینی است و روی شانهٔ چپ می‌افتد.',
                ],
            ],
        ]);

        $pieces[0] = $this->markCoverage($pieces[0], [
            'hem' => $length,
            'hem_at' => 'زیر سینه',
            'sleeve' => $this->fa(round((float) $this->param($params, 'choli_sleeve', 14))).' سانتی‌متر',
            'neck' => 'گرد، جلو و پشت باز',
        ]);

        return $this->finishBlock($pieces, $g, $grow);
    }
}
