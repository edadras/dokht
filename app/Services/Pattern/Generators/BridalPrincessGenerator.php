<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس عروس پرنسسی با دنباله.
 *
 * بالاتنهٔ کرستی، دامن پرحجم روی زیردامن، و دنباله‌ای که روی زمین کشیده
 * می‌شود.
 *
 * سه چیز که این لباس را از لباس شب پرنسسی جدا می‌کند و هر سه در الگو هستند:
 *
 *   دنباله    وزن اضافه‌ای که از خط کمر آویزان است و باید جمع‌شونده باشد،
 *             وگرنه عروس در مراسم نمی‌تواند حرکت کند.
 *   بالاتنه   کرستی و پنل‌بندی‌شده، نه ساسون‌دار؛ لباس عروس ساعت‌ها روی تن
 *             می‌ماند و ساسون به‌تنهایی فرم را نگه نمی‌دارد.
 *   لایه‌ها   پارچهٔ رو، آستر، و زیردامن. هر سه در الگو هستند چون هر سه باید
 *             بریده شوند.
 *
 * بست پیش‌فرض بند کشی است، نه زیپ: لباس عروس معمولاً چند بار پرو می‌شود و بند
 * کشی چند سانتی‌متر تنظیم می‌دهد که زیپ نمی‌دهد.
 */
class BridalPrincessGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_princess';
    }

    public function label(): string
    {
        return 'لباس عروس پرنسسی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(118), [
                    'volume' => [
                        'label' => 'حجم دامن', 'type' => 'select', 'default' => 'grand',
                        'options' => [
                            'soft' => 'ملایم (دو برابر دور باسن)',
                            'medium' => 'متوسط (سه برابر)',
                            'grand' => 'پرحجم (چهار برابر)',
                        ],
                    ],
                    'train' => [
                        'label' => 'بلندی دنباله', 'min' => 0, 'max' => 300, 'step' => 10,
                        'default' => 90, 'unit' => 'سانتی‌متر',
                        'hint' => 'تا ۹۰ دنبالهٔ کوتاه، ۱۵۰ کاتدرال، بالای ۲۰۰ سلطنتی.',
                    ],
                    'front_panels' => [
                        'label' => 'تعداد پنل نیم‌جلوی بالاتنه', 'min' => 2, 'max' => 4, 'step' => 1, 'default' => 3,
                    ],
                ]),
                ['closure' => 'lacing', 'neckline' => 'sweetheart'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'bridal']);

        $skirt = $this->gownSkirt('skirt_ball_gown', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 118),
            'volume' => (string) $this->param($params, 'volume', 'grand'),
            'petticoat' => true,
        ], 'bridal');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        $train = (float) $this->param($params, 'train', 90);
        $notes = [];

        if ($train > 1) {
            foreach ($this->gownSkirt('skirt_train', $measurements, $ease, [
                'length' => (float) $this->param($params, 'skirt_length', 118),
                'train' => $train,
            ], 'bridal-train') as $piece) {
                if (($piece['meta']['side'] ?? '') !== 'back') {
                    continue;
                }

                $piece['name'] = 'دنباله';
                // نقشش «پشت دامن» نیست: دنباله روی دامن می‌نشیند و کمرش با
                // کمر بالاتنه سنجیده نمی‌شود
                $piece['meta']['part'] = 'train';
                $piece['meta']['train'] = round($train, 1);
                $pieces[] = $piece;
            }

            $notes[] = 'دنباله '.$this->fa($train).' سانتی‌متر است و روی دامن پشت می‌نشیند؛'
                .' حلقهٔ جمع‌کننده و دکمهٔ زیر کمر پشت لازم است تا عروس بتواند حرکت کند.';
        }

        $pieces[] = $this->bandPiece('bridal-waist-stay', 'نوار کمر داخلی', max(20.0, $waist / 2), 2.5, [
            'cut' => 1, 'part' => 'facing',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notions' => [['type' => 'hook', 'label' => 'قزن نوار کمر داخلی', 'count' => 2]],
                'notes' => ['وزن دامن و دنباله روی این نوار می‌افتد، نه روی درزها.'],
            ],
        ]);

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $notes, $this->gownNotes($params), [
            'بست پیش‌فرض بند کشی است چون لباس عروس چند بار پرو می‌شود و بند کشی چند سانتی‌متر تنظیم می‌دهد که زیپ نمی‌دهد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
