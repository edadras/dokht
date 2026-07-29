<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب امپایر.
 *
 * خط کمرِ لباس زیر سینه می‌نشیند و دامن از همان‌جا آزاد می‌ریزد.
 *
 * سود این فرم روشن است: هرچه پایین‌تر از سینه است پوشیده و آزاد می‌ماند. ولی
 * یک بهای فنی دارد که همیشه فراموش می‌شود — تمام وزن لباس از یک نوار باریک
 * زیر سینه آویزان است. اگر آن نوار به تن نچسبد، لباس پایین می‌آید و خط کمر
 * روی شکم می‌افتد.
 *
 * پس این‌جا نوار زیرسینه لایی می‌خورد، کش اختیاری دارد، و آزادی‌اش عمداً کم
 * است.
 */
class EveningEmpireGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_empire';
    }

    public function label(): string
    {
        return 'لباس شب امپایر';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(120, 'بلندی دامن از زیر سینه'), [
                    'band_height' => [
                        'label' => 'بلندی نوار زیرسینه', 'min' => 2, 'max' => 10, 'step' => 0.5,
                        'default' => 4, 'unit' => 'سانتی‌متر',
                    ],
                    'band_elastic' => [
                        'label' => 'کش داخل نوار زیرسینه', 'type' => 'toggle', 'default' => false,
                    ],
                    'skirt_fullness' => [
                        'label' => 'پُری دامن', 'min' => 1, 'max' => 2.5, 'step' => 0.05,
                        'default' => 1.5,
                    ],
                ]),
                ['bodice_length' => 'empire'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params['bodice_length'] = 'empire';

        // یک آزادی برای هر دو، وگرنه دو کمر به هم نمی‌رسند
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'empire']);

        // خط کمرِ این لباس زیر سینه است و دور آن‌جا از دور کمر بیشتر است؛ دامن
        // باید برای همان دور درفت شود، وگرنه کمرش به بالاتنه نمی‌رسد.
        $skirtEase = array_merge($ease, [
            'waist' => max(0.0, $waist - ((float) ($measurements['waist'] ?? 74))),
        ]);

        // دامن خط A با پُری خواسته‌شده روی دم؛ اضافهٔ کمر را joinWaist چین می‌دهد
        $fullness = max(1.0, (float) $this->param($params, 'skirt_fullness', 1.5));

        $skirt = $this->gownSkirt('skirt_a_line', $measurements, $skirtEase, [
            'length' => (float) $this->param($params, 'skirt_length', 120),
            'hem_flare' => round(($waist * ($fullness - 1)) + 25, 1),
            'dart_share' => 0,
        ], 'empire');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $height = (float) $this->param($params, 'band_height', 4);
        $band = $this->bandPiece('empire-band', 'نوار زیرسینه', max(20.0, $waist / 2), $height, [
            'cut' => 2, 'part' => 'binding',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notes' => [
                    'تمام وزن لباس از همین نوار آویزان است؛ لایی بزنید و گشاد نبُرید.',
                ],
            ],
        ]);

        if ($this->flag($params, 'band_elastic', false)) {
            $band['meta']['notions'] = [[
                'type' => 'elastic',
                'label' => 'کش داخل نوار زیرسینه',
                'length' => round($waist * 0.92, 1),
            ]];
        }

        $pieces = array_merge($bodice, [$band], $skirt);
        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'خط کمر لباس زیر سینه است؛ اگر نوار زیرسینه شل باشد، لباس پایین می‌آید و خط کمر روی شکم می‌افتد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
