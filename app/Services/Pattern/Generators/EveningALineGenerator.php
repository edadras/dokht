<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب خط A.
 *
 * بالاتنهٔ جذب و دامنی که از کمر به‌آرامی باز می‌شود. پرکاربردترین سیلوئت
 * لباس مجلسی است، و دلیلش هم فنی است نه سلیقه‌ای: خط A تنها فرمی است که روی
 * تقریباً هر اندامی می‌نشیند، چون فقط کمر را اندازه می‌گیرد و بقیه را آزاد
 * می‌گذارد.
 *
 * برای همین این مدل ساده‌ترین برای دوخت است و کمترین جای خطا را دارد: اگر
 * دامن کمی گشادتر یا تنگ‌تر درآید، روی تن دیده نمی‌شود.
 */
class EveningALineGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_a_line';
    }

    public function label(): string
    {
        return 'لباس شب خط A';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(array_merge($this->gownLengthParam(110), [
                'skirt_flare' => [
                    'label' => 'گشادی دم دامن', 'min' => 10, 'max' => 90, 'step' => 5,
                    'default' => 35, 'unit' => 'سانتی‌متر',
                ],
                'pockets' => [
                    'label' => 'جیب درزی', 'type' => 'toggle', 'default' => false,
                    'hint' => 'جیب در درز پهلوی دامن؛ در لباس مجلسی کاربردی‌تر از چیزی است که به نظر می‌رسد.',
                ],
            ])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // یک آزادی برای هر دو، وگرنه دو کمر به هم نمی‌رسند
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'aline']);

        $skirt = $this->gownSkirt('skirt_a_line', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 110),
            'flare' => (float) $this->param($params, 'skirt_flare', 35),
        ], 'aline');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        if ($this->flag($params, 'pockets', false)) {
            $pieces[] = $this->bandPiece('aline-pocket', 'کیسه جیب درزی', 16, 18, [
                'cut' => 4, 'part' => 'pocket',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دو کیسه برای هر جیب؛ در درز پهلوی دامن دوخته می‌شود.'],
                ],
            ]);
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params));

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
