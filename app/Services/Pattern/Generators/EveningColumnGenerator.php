<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب راسته (ستونی).
 *
 * ساده‌ترین سیلوئت لباس شب و بی‌رحم‌ترینشان: هیچ چین و حجمی نیست که ایراد
 * اندازه را بپوشاند. هر میلی‌متر اختلاف روی تن دیده می‌شود.
 *
 * دو چیز که این لباس بدون آن‌ها پوشیده نمی‌شود و هر دو این‌جا حساب شده‌اند:
 *
 *   چاک       دامن راسته به پا اجازهٔ قدم برداشتن نمی‌دهد. بدون چاک، گام از
 *             چهل سانتی‌متر کوتاه‌تر می‌شود. پس چاک اختیاری نیست، اندازه‌اش
 *             اختیاری است.
 *   زیپ بلند  زیپ باید از باسن رد شود، وگرنه لباس از سر و پا هیچ‌کدام
 *             پوشیده نمی‌شود.
 */
class EveningColumnGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_column';
    }

    public function label(): string
    {
        return 'لباس شب راسته (ستونی)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(array_merge($this->gownLengthParam(112), [
                'slit' => [
                    'label' => 'بلندی چاک', 'min' => 0, 'max' => 80, 'step' => 5,
                    'default' => 40, 'unit' => 'سانتی‌متر',
                    'hint' => 'بدون چاک، دامن راسته اجازهٔ قدم برداشتن نمی‌دهد.',
                ],
                'slit_place' => [
                    'label' => 'جای چاک', 'type' => 'select', 'default' => 'side',
                    'options' => ['side' => 'درز پهلوی چپ', 'center_back' => 'مرکز پشت', 'front' => 'جلو'],
                ],
            ])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // یک آزادی برای هر دو، وگرنه دو کمر به هم نمی‌رسند
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'column']);

        $skirt = $this->gownSkirt('skirt_pencil', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 112),
            'vent' => (float) $this->param($params, 'slit', 40),
        ], 'column');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $slit = (float) $this->param($params, 'slit', 40);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            $slit > 1
                ? 'چاک به بلندی '.$this->fa($slit).' سانتی‌متر روی '
                    .match ((string) $this->param($params, 'slit_place', 'side')) {
                        'center_back' => 'مرکز پشت',
                        'front' => 'جلو',
                        default => 'درز پهلوی چپ',
                    }.'؛ لبهٔ چاک را نوار تقویتی بزنید تا سرِ چاک پاره نشود.'
                : 'هشدار: بدون چاک، این دامن اجازهٔ قدم برداشتن نمی‌دهد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
