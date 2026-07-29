<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * شلوار پالازو.
 *
 * پالازو با «دم‌پا گشاد» یکی نیست: شلوار دم‌پا گشاد از زانو باز می‌شود ولی پالازو
 * از همان خط باسن شروع به باز شدن می‌کند و تا پایین یک‌نواخت پهن می‌ماند، طوری که
 * ایستاده شبیه دامن دیده شود. دو تصمیم این را می‌سازد:
 *
 *   ۱. پهنای زانو تقریباً به اندازه پهنای دم پا گرفته می‌شود (hem_vs_knee کوچک)،
 *      پس بین زانو و دم پا شکستی در خط پهلو نیست و پارچه راست می‌ریزد.
 *   ۲. فاق بلند است و کاهش کمر به پیلی می‌رود نه ساسون؛ پیلیِ باز‌شونده همان
 *      حجمی است که پالازو را از کمر تا پایین یک‌دست نگه می‌دارد.
 *
 * چون پارچه پهن است و روی زمین کشیده می‌شود، دم پا عمداً کمی کوتاه‌تر از قد داخل
 * پا بریده می‌شود و یادداشتش روی قطعه می‌ماند.
 */
class PantsPalazzoGenerator extends PantsBaseGenerator
{
    use PieceRoles;

    public static function key(): string
    {
        return 'pants_palazzo';
    }

    public function label(): string
    {
        return 'شلوار پالازو';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(34, 46),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 10, 'max' => 36, 'step' => 0.5,
                    'default' => 20, 'unit' => 'سانتی‌متر',
                ],
                'front_pleats' => [
                    'label' => 'تعداد پیلی جلو', 'min' => 1, 'max' => 3, 'step' => 1, 'default' => 2,
                ],
                'hem_lift' => [
                    'label' => 'کوتاه‌کردن دم پا', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 2, 'unit' => 'سانتی‌متر',
                    'hint' => 'پارچه پهن روی زمین کشیده می‌شود؛ این عدد از قد داخل پا کم می‌شود.',
                ],
            ],
            $this->bandParams(5),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 20),
            // زانو و دم پا تقریباً هم‌پهنا: خط پهلو از باسن تا پایین یک‌سره است
            'hem_vs_knee' => 6.0,
            'front_waist' => 'pleat',
            'pleat_count' => (int) $this->param($params, 'front_pleats', 2),
            'pleat_style' => 'knife',
            'back_waist' => 'dart',
            'dart_count' => 2,
            'waist_balance' => 0.5,
            'side_share' => 0.24,
            'lean_share' => 0.1,
            'length_offset' => -(float) $this->param($params, 'hem_lift', 2),
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $lift = (float) $this->param($params, 'hem_lift', 2);

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $pieces[$index]['meta']['silhouette'] = 'palazzo';
            $pieces[$index]['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
                'دم پا '.$this->fa(round($lift, 1)).' سانتی‌متر بالاتر از قد داخل پا بریده شده؛'
                    .' پارچه پهن روی زمین کشیده می‌شود.',
                'خط اتوی پا را از خط باسن تا دم پا اتو کنید؛ همین خط است که پالازو را راست نگه می‌دارد.',
            ]);
        }

        return $this->finish($this->withGirthRoles(array_merge($pieces, [$this->hemFacing($measurements, $params)])));
    }

    /**
     * سجاف دم پا.
     *
     * لبه پایین پالازو پهن است و برگردان ساده روی آن موج می‌اندازد؛ نوارِ هم‌شکلِ
     * لبه این مشکل را ندارد چون همان پهنا را با خودش می‌آورد.
     *
     * @return array<string, mixed>
     */
    protected function hemFacing(array $m, array $params): array
    {
        $hip = $this->m($m, 'hip', 98);
        $width = max(20.0, ($hip + (float) $this->param($params, 'hem_ease', 46)) / 4);
        $height = 6.0;

        return $this->piece([
            'code' => 'palazzo-hem-facing',
            'name' => 'سجاف دم پا',
            'cut_quantity' => 4,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 0),
                Geometry::point($width, $height),
                Geometry::point(0, $height),
            ],
            'grainline' => $this->grainline($width * 0.5, 0.8, $height - 0.8),
            'meta' => [
                'part' => 'facing',
                'edges' => ['hem', 'side', 'default', 'side'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'چهار تکه (دو تکه برای هر پا)؛ به‌جای برگردان ساده دوخته می‌شود تا لبه پهن موج نیندازد.',
                ],
            ],
        ]);
    }
}
