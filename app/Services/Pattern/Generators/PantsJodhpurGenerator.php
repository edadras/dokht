<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * شلوار سوارکاری (جودپور).
 *
 * جودپور دو ناحیه دارد و هر دو باید در خودِ الگو دیده شوند، نه فقط در نام:
 *
 *   بالای زانو  ران به‌شدت گشاد است تا سوارکار بتواند روی زین پا باز کند. این
 *               گشادی از «آزادی دور ران» می‌آید که خط فاق را نسبت به خط باسن به
 *               بیرون می‌برد؛ پس پهنای قطعه در تراز فاق به‌روشنی از پهنای آن در
 *               تراز باسن بیشتر است.
 *   زیر زانو    پا چسبان می‌شود تا زیر چکمه برود. پهنای زانو با آزادی نزدیک صفر
 *               گرفته می‌شود و دم پا سه سانتی‌متر از زانو هم تنگ‌تر است.
 *
 * چون این دو ناحیه در یک قطعه‌اند، درفت هر دو را اندازه می‌گیرد و در
 * meta.jodhpur ثبت می‌کند: پهنای واقعی در تراز فاق، زانو و دم پا. اگر روزی
 * پارامترها طوری عوض شوند که ران گشادتر از زانو نباشد، همان‌جا پیداست.
 *
 * زیر زانو چون تنگ است، شلوار بدون بست پوشیده نمی‌شود؛ برای همین زیپ مچ روی درز
 * داخل پا علامت می‌خورد و طولش در صورت مواد می‌آید.
 */
class PantsJodhpurGenerator extends PantsBaseGenerator
{
    use PieceRoles;

    public static function key(): string
    {
        return 'pants_jodhpur';
    }

    public function label(): string
    {
        return 'شلوار سوارکاری (جودپور)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->riseParams('high'),
            $this->legParams(0, 2),
            [
                'thigh_ease' => [
                    'label' => 'آزادی دور ران', 'min' => 8, 'max' => 34, 'step' => 0.5,
                    'default' => 22, 'unit' => 'سانتی‌متر',
                    'hint' => 'همین عدد است که ناحیه گشادِ بالای زانو را می‌سازد.',
                ],
                'knee_patch' => [
                    'label' => 'وصله زانو', 'type' => 'toggle', 'default' => true,
                    'hint' => 'وصله چرمی داخل زانو که با زین سایش دارد.',
                ],
                'ankle_zip' => [
                    'label' => 'زیپ مچ روی درز داخل پا', 'min' => 0, 'max' => 22, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بدون زیپ؛ آن‌وقت پا باید از پارچه کشی بریده شود.',
                ],
            ],
            $this->bandParams(4),
        );
    }

    protected function shape(array $params, array $measurements): array
    {
        return [
            'thigh_ease' => (float) $this->param($params, 'thigh_ease', 22),
            // زیر زانو: دم پا سه سانتی‌متر از زانو هم تنگ‌تر
            'hem_vs_knee' => -3.0,
            'front_waist' => 'pleat',
            'pleat_count' => 1,
            'pleat_style' => 'knife',
            'back_waist' => 'dart',
            'dart_count' => 2,
            'waist_balance' => 0.5,
            'side_share' => 0.26,
            'lean_share' => 0.12,
            // فاق سوارکاری گودتر بریده می‌شود تا روی زین نکشد
            'scoop_back' => 0.44,
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $pieces = parent::generate($measurements, $ease, $params);
        $zip = (float) $this->param($params, 'ankle_zip', 14);

        foreach ($pieces as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_leg', 'back_leg'], true)) {
                continue;
            }

            $pieces[$index] = $this->measureZones($piece, $zip);
        }

        $extra = [];

        if ($this->flag($params, 'knee_patch', true)) {
            $extra[] = $this->kneePatch($measurements, $params);
        }

        return $this->finish($this->withGirthRoles(array_merge($pieces, $extra)));
    }

    /**
     * اندازه‌گیری دو ناحیه جودپور روی خود قطعه و علامت‌زدن زیپ مچ.
     *
     * پهنا در هر تراز از برخورد یک خط افقی با مسیر قطعه خوانده می‌شود، نه از
     * عددهای درفت؛ پس اگر مهارهای PantsBlock پهنای ران را کوچک کرده باشند،
     * همین‌جا معلوم می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function measureZones(array $piece, float $zip): array
    {
        $outline = $piece['outline'];
        [, $minY, , $maxY] = Geometry::bounds($outline);

        $crotchY = $minY + (float) ($piece['meta']['crotch_depth'] ?? 0);

        foreach ($piece['markers'] ?? [] as $marker) {
            if (($marker['key'] ?? null) === 'crotch') {
                $crotchY = (float) $marker['from']['y'];
            }
        }

        $kneeY = $piece['meta']['knee_y'] ?? null;
        $hemY = (float) ($piece['meta']['hem_y'] ?? $maxY);

        $piece['meta']['jodhpur'] = [
            'thigh_width' => round($this->widthAt($outline, $crotchY + 1.5), 2),
            'knee_width' => $kneeY === null ? null : round($this->widthAt($outline, (float) $kneeY), 2),
            'hem_width' => round($this->widthAt($outline, $hemY - 0.5), 2),
        ];

        $piece['meta']['notes'] = array_merge($piece['meta']['notes'] ?? [], [
            'ران بالای زانو گشاد و پا از زانو به پایین چسبان است؛ خط پهلو را با خط‌کش منحنی روان کنید'
                .' تا شکستِ بالای زانو تیز نماند.',
        ]);

        if ($zip < 1) {
            return $piece;
        }

        $inseam = $piece['meta']['inseam_edges'] ?? [];

        if ($inseam === []) {
            return $piece;
        }

        $edge = (int) $inseam[0];
        $length = round(min($zip, max(4.0, $hemY - (float) ($kneeY ?? $crotchY))), 1);
        $from = Geometry::pointOnEdge($outline, $edge, 0.0);

        $piece['markers'][] = $this->marker(
            'zip',
            'زیپ مچ روی درز داخل پا',
            (float) $from['x'],
            max($minY, $hemY - $length),
            (float) $from['x'],
            min($maxY, $hemY),
        );
        $piece['meta']['ankle_zip'] = $length;
        $piece['meta']['notions'][] = [
            'type' => 'zip',
            'label' => 'زیپ مچ جودپور',
            'count' => 1,
            'length' => $length,
        ];

        return $piece;
    }

    /** پهنای قطعه در یک تراز افقی. */
    protected function widthAt(array $outline, float $y): float
    {
        $points = Geometry::flatten($outline);
        $count = count($points);
        $xs = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % $count];

            if ((($a['y'] - $y) * ($b['y'] - $y)) > 0 || abs($b['y'] - $a['y']) < 1e-9) {
                continue;
            }

            $xs[] = $a['x'] + (($b['x'] - $a['x']) * (($y - $a['y']) / ($b['y'] - $a['y'])));
        }

        return $xs === [] ? 0.0 : max($xs) - min($xs);
    }

    /** وصله زانو (چرم یا پارچه ضخیم) روی درز داخل پا. */
    protected function kneePatch(array $m, array $params): array
    {
        $knee = ($this->m($m, 'knee', 37) + (float) $this->param($params, 'knee_ease', 0)) / 2;
        $width = max(10.0, min(20.0, $knee * 0.7));
        $height = 22.0;

        return $this->piece([
            'code' => 'jodhpur-knee-patch',
            'name' => 'وصله زانو',
            'cut_quantity' => 4,
            'mirror' => true,
            'outline' => [
                Geometry::point(0, 0),
                Geometry::point($width, 2.0),
                Geometry::curve($width, $height - 2, $width + 1.4, $height * 0.5),
                Geometry::curve(0, $height, $width * 0.5, $height + 1.2),
            ],
            'grainline' => $this->grainline($width * 0.5, 2.5, $height - 2.5),
            'meta' => [
                'part' => 'patch',
                'edges' => ['default', 'side', 'hem', 'default'],
                'fold_edges' => [],
                'girth_role' => 'trim',
                'notes' => [
                    'چهار تکه: دو تای هر پا، یکی روی پای جلو و یکی روی پای پشت، هم‌تراز خط زانو و چسبیده به درز داخل پا.',
                    'از چرم نازک یا پارچه ضخیم بریده و رودوزی می‌شود.',
                ],
            ],
        ]);
    }
}
