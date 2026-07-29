<?php

namespace App\Services\Pattern\Generators;

/**
 * خمار (مقنعه بلند).
 *
 * یک تکه پارچه است و بس: دایره‌ای که یک سوراخ بیضی برای صورت دارد. سر از سوراخ
 * رد می‌شود و باقی پارچه از هر طرف روی سر، شانه، سینه و پشت می‌ریزد.
 *
 * دو عدد کل کار را تعیین می‌کنند و هر دو در الگو سنجیده می‌شوند:
 *
 *   دور جای صورت — اگر از دور سر کوچک‌تر باشد اصلاً پوشیده نمی‌شود، و اگر خیلی
 *   بزرگ باشد از سر می‌افتد. الگو خودش دور سوراخ را حساب می‌کند و اگر از دور سر
 *   کوچک‌تر درآمد، بزرگش می‌کند.
 *
 *   جابه‌جایی سوراخ به جلو — اگر سوراخ درست وسط دایره باشد، جلو و پشت هم‌اندازه
 *   می‌ریزند و خمار مثل پانچو دیده می‌شود. سوراخ باید جلوتر بنشیند تا پشت
 *   بلندتر از جلو بیفتد.
 *
 * پارچه روی تای خودش بریده می‌شود و جای صورت هم از همان تا بریده می‌شود؛ باز که
 * شود یک دایره کامل با سوراخ صورت است و هیچ درزی ندارد.
 */
class TradKhimarGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_khimar';
    }

    public function label(): string
    {
        return 'خمار (مقنعه بلند)';
    }

    public function paramsSchema(): array
    {
        return [
            'length' => [
                'label' => 'شعاع خمار (از فرق سر تا لبه)', 'min' => 40, 'max' => 110, 'step' => 1,
                'default' => 72, 'unit' => 'سانتی‌متر',
                'hint' => 'هفتاد سانتی‌متر یعنی پشت تا زیر کمر و جلو تا روی شکم می‌آید.',
            ],
            'face_width' => [
                'label' => 'پهنای جای صورت', 'min' => 12, 'max' => 30, 'step' => 0.5,
                'default' => 17, 'unit' => 'سانتی‌متر',
            ],
            'face_height' => [
                'label' => 'بلندی جای صورت', 'min' => 16, 'max' => 38, 'step' => 0.5,
                'default' => 24, 'unit' => 'سانتی‌متر',
            ],
            'face_offset' => [
                'label' => 'جابه‌جایی جای صورت به جلو', 'min' => 0, 'max' => 26, 'step' => 1,
                'default' => 11, 'unit' => 'سانتی‌متر',
                'hint' => 'هرچه بیشتر باشد، پشتِ خمار از جلویش بلندتر می‌شود.',
            ],
            'face_finish' => [
                'label' => 'تمام‌کردن لبه صورت', 'type' => 'select', 'default' => 'binding',
                'options' => [
                    'binding' => 'نوار اریب',
                    'elastic' => 'کش داخل جای کش',
                ],
                'hint' => 'کش، لبه صورت را روی پیشانی نگه می‌دارد؛ نوار اریب نرم‌تر می‌افتد.',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $head = round($this->m($measurements, 'neck', 37) * 1.55, 1);
        $radius = (float) $this->param($params, 'length', 72);
        $finish = (string) $this->param($params, 'face_finish', 'binding');

        $cover = $this->headCoverPiece([
            'code' => 'khimar',
            'name' => 'خمار (یک‌تکه، روی تای پارچه)',
            'part' => 'head_cover',
            'radius' => $radius,
            'head' => $head,
            'face_width' => (float) $this->param($params, 'face_width', 17),
            'face_height' => (float) $this->param($params, 'face_height', 24),
            'face_offset' => (float) $this->param($params, 'face_offset', 11),
        ]);

        $opening = (float) ($cover['meta']['face_opening'] ?? 60);

        if ($finish === 'elastic') {
            $cover = $this->addNotion(
                $cover,
                [
                    'type' => 'elastic',
                    'label' => 'کش لبه صورت',
                    'count' => 1,
                    'length' => round($opening * 0.88, 1),
                ],
                'کش لبه صورت '.$this->fa(round($opening * 0.88))
                    .' سانتی‌متر است، یعنی حدود دوازده درصد کوتاه‌تر از خود لبه؛ همین کوتاهی خمار را روی سر نگه می‌دارد.',
            );
        }

        $cover = $this->markCoverage($cover, [
            'neck' => 'ندارد؛ فقط صورت از سوراخ بیرون می‌ماند و گردن و سینه و شانه زیر پارچه‌اند',
            'head' => 'از لبه صورت تا لبه پایین، جلو '
                .$this->fa($cover['meta']['front_drop'] ?? 0).' و پشت '
                .$this->fa($cover['meta']['back_drop'] ?? 0)
                .' سانتی‌متر؛ پهنای پارچه از هر پهلو '.$this->fa(round($radius)).' سانتی‌متر',
        ]);

        $cover['meta']['notes'] = array_merge($cover['meta']['notes'] ?? [], $this->modestNotes([
            'هیچ درزی ندارد؛ فقط لبه صورت و لبه دور تا دور تمیز می‌شوند.',
        ]));

        $pieces = [$cover];

        if ($finish === 'binding') {
            $pieces[] = $this->bandPiece('khimar-face-binding', 'نوار اریب لبه صورت', $opening + 4, 4, [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'bias' => true,
                    'girth_role' => 'trim',
                    'target_neck' => round($opening, 1),
                    'notes' => [
                        'روی اریب بریده می‌شود؛ نوار راستا روی منحنی صورت چین می‌خورد.',
                        'اگر پارچه نازک است، دو لایه بریده شود تا لبه صورت فرم بگیرد.',
                    ],
                ],
            ]);
        }

        return $this->finish($pieces);
    }
}
