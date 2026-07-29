<?php

namespace App\Services\Pattern\Generators;

/**
 * چادر نماز (دوتکه).
 *
 * چادر نمازِ دوتکه از دو چیز ساخته می‌شود که هیچ‌کدام به تنهایی کار نمی‌کند:
 *
 *   سرانداز — یک دایره پارچه با سوراخ صورت که تا پایین‌تر از کمر می‌ریزد. لبه
 *   صورتش کش دارد، وگرنه هنگام سجده عقب می‌رود و صورت را باز می‌گذارد. همین کش
 *   است که چادر نمازِ دوتکه را از یک روسری بزرگ جدا می‌کند.
 *
 *   دامن — دامنی بسیار چین‌دار با کمر کشی که از خط کمر تا روی زمین می‌آید. کمرش
 *   هیچ بستی ندارد؛ کش، هم بستِ لباس است و هم چین کمر را می‌سازد.
 *
 * دوتکه بودن عمدی است: هنگام نماز دست‌ها آزاد می‌ماند و پارچه از سر نمی‌افتد،
 * چیزی که در چادر یک‌تکه همیشه دردسر است.
 */
class TradPrayerDressGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_prayer_dress';
    }

    public function label(): string
    {
        return 'چادر نماز';
    }

    public function paramsSchema(): array
    {
        return [
            'cover_radius' => [
                'label' => 'شعاع سرانداز (از فرق سر تا لبه)', 'min' => 40, 'max' => 95, 'step' => 1,
                'default' => 62, 'unit' => 'سانتی‌متر',
                'hint' => 'شصت سانتی‌متر یعنی پشت تا روی باسن و جلو تا روی شکم می‌آید.',
            ],
            'face_width' => [
                'label' => 'پهنای جای صورت', 'min' => 12, 'max' => 28, 'step' => 0.5,
                'default' => 16, 'unit' => 'سانتی‌متر',
            ],
            'face_height' => [
                'label' => 'بلندی جای صورت', 'min' => 16, 'max' => 34, 'step' => 0.5,
                'default' => 23, 'unit' => 'سانتی‌متر',
            ],
            'face_offset' => [
                'label' => 'جابه‌جایی جای صورت به جلو', 'min' => 0, 'max' => 22, 'step' => 1,
                'default' => 9, 'unit' => 'سانتی‌متر',
            ],
            'face_elastic' => [
                'label' => 'کوتاهی کش لبه صورت', 'min' => 0.75, 'max' => 1, 'step' => 0.01,
                'default' => 0.86,
                'hint' => 'کش به این نسبت از لبه صورت کوتاه‌تر بریده می‌شود؛ همین کوتاهی نگهش می‌دارد.',
            ],
            'skirt_length' => [
                'label' => 'بلندی دامن از خط کمر', 'min' => 60, 'max' => 130, 'step' => 1,
                'default' => 100, 'unit' => 'سانتی‌متر',
            ],
            'skirt_fullness' => [
                'label' => 'نسبت پُری چین دامن', 'min' => 1.6, 'max' => 3, 'step' => 0.1,
                'default' => 2.2,
            ],
            'casing' => [
                'label' => 'بلندی نیفه کمر', 'min' => 2.5, 'max' => 7, 'step' => 0.5,
                'default' => 4, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $head = round($this->m($measurements, 'neck', 37) * 1.55, 1);
        $radius = (float) $this->param($params, 'cover_radius', 62);
        $ratio = min(1.0, max(0.75, (float) $this->param($params, 'face_elastic', 0.86)));

        $cover = $this->headCoverPiece([
            'code' => 'prayer-cover',
            'name' => 'سرانداز (یک‌تکه، روی تای پارچه)',
            'part' => 'head_cover',
            'radius' => $radius,
            'head' => $head,
            'face_width' => (float) $this->param($params, 'face_width', 16),
            'face_height' => (float) $this->param($params, 'face_height', 23),
            'face_offset' => (float) $this->param($params, 'face_offset', 9),
        ]);

        $opening = (float) ($cover['meta']['face_opening'] ?? 60);

        $cover = $this->addNotion(
            $cover,
            [
                'type' => 'elastic',
                'label' => 'کش لبه صورت سرانداز',
                'count' => 1,
                'length' => round($opening * $ratio, 1),
            ],
            'کش لبه صورت '.$this->fa(round($opening * $ratio)).' سانتی‌متر برای لبه‌ای به بلندی '
                .$this->fa(round($opening)).' سانتی‌متر؛ بدون این کش، سرانداز در سجده عقب می‌رود.',
        );

        $cover = $this->markCoverage($cover, [
            'neck' => 'ندارد؛ فقط صورت از سوراخ بیرون می‌ماند',
            'head' => 'از لبه صورت تا لبه پایین، جلو '
                .$this->fa($cover['meta']['front_drop'] ?? 0).' و پشت '
                .$this->fa($cover['meta']['back_drop'] ?? 0).' سانتی‌متر',
        ]);

        $cover['meta']['notes'] = array_merge($cover['meta']['notes'] ?? [], $this->modestNotes([
            'دوتکه بودن عمدی است: در سجده دست‌ها آزاد می‌ماند و پارچه از سر نمی‌افتد.',
        ]));

        $pieces = [$cover];

        foreach ($this->skirtPieces($measurements, $g, $params) as $piece) {
            $pieces[] = $piece;
        }

        return $this->finish($pieces);
    }

    /**
     * دامن کمرکشی چین‌دار و نیفه‌اش.
     *
     * @param  array<string, float>  $g
     * @return array<int, array<string, mixed>>
     */
    protected function skirtPieces(array $m, array $g, array $params): array
    {
        $waist = $this->m($m, 'waist', 74);
        $ratio = max(1.4, (float) $this->param($params, 'skirt_fullness', 2.2));
        $length = max(40.0, (float) $this->param($params, 'skirt_length', 100));
        $casing = (float) $this->param($params, 'casing', 4);

        $quarter = $g['quarter_waist'];
        $gather = round($quarter * ($ratio - 1), 2);
        $fabric = round(($quarter + $gather) * 4, 1);
        $pieces = [];

        foreach ([['front', 'جلو'], ['back', 'پشت']] as [$side, $name]) {
            $panel = $this->lowerPanel($g, [
                'side' => $side,
                'shape' => 'straight',
                'top_width' => $quarter,
                'top_y' => $g['side_waist_y'],
                'length' => $length,
                'gather' => $gather,
                'flare' => 6,
                'top_tag' => 'waist',
                'code' => 'prayer-skirt-'.$side,
                'name' => 'دامن چادر نماز — '.$name,
                'meta' => [
                    'gather_ratio' => round($ratio, 2),
                    'notes' => [
                        'کمر هیچ بستی ندارد؛ کش داخل نیفه هم بستِ لباس است و هم چین کمر را می‌سازد.',
                    ],
                ],
            ]);

            $pieces[] = $this->recordGathers($panel, $gather, 'چین کمر که کش جمعش می‌کند');
        }

        $band = $this->bandPiece('prayer-skirt-casing', 'نیفه کمر دامن', ($fabric / 2) + 3, $casing * 2, [
            'cut' => 2, 'part' => 'waistband', 'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'notions' => [[
                    'type' => 'elastic',
                    'label' => 'کش کمر دامن',
                    'count' => 1,
                    'length' => round($waist * 0.92, 1),
                ]],
                'notes' => [
                    'دو تکه نیفه که روی درز پهلو به هم می‌رسند و صاف روی لبه کمر دوخته می‌شوند.',
                    'کش، لبه کمر را از '.$this->fa($fabric).' سانتی‌متر پارچه تا '
                        .$this->fa(round($waist * 0.92)).' سانتی‌متر جمع می‌کند.',
                ],
            ],
        ]);

        $pieces[] = $band;

        return $pieces;
    }
}
