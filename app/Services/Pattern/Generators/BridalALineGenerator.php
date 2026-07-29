<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس عروس خط A.
 *
 * پرکاربردترین لباس عروس دنیا، و دلیلش فنی است: خط A فقط کمر را اندازه
 * می‌گیرد و بقیهٔ بدن را آزاد می‌گذارد. پس هم روی هر اندامی می‌نشیند، هم
 * اجازهٔ نشستن و راه رفتن می‌دهد، هم اگر اندازه چند سانتی‌متر جابه‌جا شود
 * روی تن دیده نمی‌شود.
 *
 * دنبالهٔ کوتاه (سویپ) پیش‌فرض است: بلندترین دنباله‌ای که هنوز می‌شود با آن
 * راه رفت.
 */
class BridalALineGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'bridal_a_line';
    }

    public function label(): string
    {
        return 'لباس عروس خط A';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(116), [
                    'skirt_flare' => [
                        'label' => 'گشادی دم دامن', 'min' => 20, 'max' => 110, 'step' => 5,
                        'default' => 55, 'unit' => 'سانتی‌متر',
                    ],
                    'train' => [
                        'label' => 'بلندی دنباله', 'min' => 0, 'max' => 200, 'step' => 10,
                        'default' => 40, 'unit' => 'سانتی‌متر',
                        'hint' => 'چهل سانتی‌متر «سویپ» است: بلندترین دنباله‌ای که با آن راحت راه می‌روید.',
                    ],
                    'overskirt' => [
                        'label' => 'رودامن جداشدنی', 'type' => 'toggle', 'default' => false,
                        'hint' => 'برای مراسم پوشیده و برای جشن برداشته می‌شود.',
                    ],
                ]),
                ['neckline' => 'sweetheart'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'bridal-aline']);

        $train = (float) $this->param($params, 'train', 40);
        $length = (float) $this->param($params, 'skirt_length', 116);

        $skirt = $train > 1
            ? $this->gownSkirt('skirt_train', $measurements, $ease, [
                'length' => $length,
                'train' => $train,
                // نام این پارامتر در دامن دنباله‌دار hem_flare است و در خط A، flare
                'hem_flare' => (float) $this->param($params, 'skirt_flare', 55),
            ], 'bridal-aline')
            : $this->gownSkirt('skirt_a_line', $measurements, $ease, [
                'length' => $length,
                'flare' => (float) $this->param($params, 'skirt_flare', 55),
            ], 'bridal-aline');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        $notes = [];

        if ($this->flag($params, 'overskirt', false)) {
            foreach ($this->gownSkirt('skirt_overlay', $measurements, $ease, [
                'length' => $length,
                'overlay_style' => 'open_front',
                'overlay_length' => 25,
                'overlay_flare' => 45,
            ], 'bridal-over') as $piece) {
                if (($piece['meta']['overlay'] ?? false) !== true) {
                    continue;
                }

                $piece['name'] = 'رودامن جداشدنی — '.($piece['name'] ?? '');
                $piece['meta']['detachable'] = true;
                $piece['meta']['notions'][] = ['type' => 'hook', 'label' => 'قزن رودامن', 'count' => 3];
                $pieces[] = $piece;
            }

            $notes[] = 'رودامن با قزن به کمر وصل می‌شود؛ برای مراسم پوشیده و برای جشن برداشته می‌شود.';
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $notes, $this->gownNotes($params), [
            'خط A فقط کمر را اندازه می‌گیرد و بقیه را آزاد می‌گذارد؛ برای همین روی هر اندامی می‌نشیند.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
