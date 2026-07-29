<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب ماهی.
 *
 * تا زانو جذب و از آن‌جا به پایین باز. تنگ‌ترین سیلوئت این خانواده و همان‌جا
 * هم دشوارترینش:
 *
 *   - قدم برداشتن با دامن جذب تا زانو ممکن نیست مگر اینکه از جایی به بعد باز
 *     شود؛ جای «شروع کلوش» مهم‌ترین عدد این لباس است. بالاتر از زانو یعنی
 *     راحت‌تر ولی کمتر ماهی، پایین‌تر یعنی زیباتر و سخت‌تر.
 *   - چون همه‌جا به تن می‌چسبد، آستر هم باید کشسان یا اریب باشد؛ آستر راسته
 *     زیر لباس جذب پاره می‌شود.
 *   - نشستن با این لباس تقریباً ممکن نیست؛ همین را الگو باید بگوید، نه اینکه
 *     خیاط سر پرو بفهمد.
 */
class EveningMermaidGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_mermaid';
    }

    public function label(): string
    {
        return 'لباس شب ماهی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(array_merge($this->gownLengthParam(118), [
                'flare_from' => [
                    'label' => 'شروع کلوش از کمر', 'min' => 40, 'max' => 90, 'step' => 1,
                    'default' => 58, 'unit' => 'سانتی‌متر',
                    'hint' => 'حدود زانو؛ پایین‌تر یعنی ماهی‌تر و سخت‌تر برای راه رفتن.',
                ],
                'hem_flare' => [
                    'label' => 'گشادی دم', 'min' => 20, 'max' => 120, 'step' => 5,
                    'default' => 55, 'unit' => 'سانتی‌متر',
                ],
            ])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'mermaid']);

        $flareFrom = (float) $this->param($params, 'flare_from', 58);

        $skirt = $this->gownSkirt('skirt_mermaid', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 118),
            'flare_from' => $flareFrom,
            'hem_flare' => (float) $this->param($params, 'hem_flare', 55),
        ], 'mermaid');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'کلوش از '.$this->fa($flareFrom).' سانتی‌متری کمر شروع می‌شود؛ بالاتر از زانو راحت‌تر است و پایین‌تر، ماهی‌تر.',
            'آستر این لباس باید اریب یا کشسان باشد؛ آستر راسته زیر لباس جذب پاره می‌شود.',
            'هشدار: نشستن با لباس ماهیِ جذب تقریباً ممکن نیست. اگر مشتری قرار است بنشیند، شروع کلوش را بالاتر بیاورید.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
