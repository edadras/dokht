<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب ساتن بندی (اسلیپ).
 *
 * لباسی که تمامش روی اریب بریده می‌شود. همین یک جمله همهٔ تفاوت‌هایش را
 * می‌سازد:
 *
 *   - اریب خودش کشسان است، پس این لباس ساسون ندارد و نمی‌خواهد؛ فرم را خودِ
 *     پارچه می‌گیرد.
 *   - اریب پارچهٔ خیلی بیشتری می‌خورد، چون قطعه‌ها روی زاویهٔ ۴۵ درجه چیده
 *     می‌شوند و بین‌شان پرت می‌ماند.
 *   - اریب با وزن خودش دراز می‌شود. لباس باید بیست‌وچهار ساعت آویزان بماند و
 *     بعد دمش صاف شود، وگرنه چند روز بعد کج می‌شود.
 *
 * بند نازک و قابل تنظیم است و بست پهلو دارد، نه پشت؛ زیپ پشت روی اریب موج
 * می‌اندازد.
 */
class EveningSlipGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_slip';
    }

    public function label(): string
    {
        return 'لباس شب ساتن بندی (اسلیپ)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(108), [
                    'cowl' => [
                        'label' => 'یقه دلبری (کول)', 'type' => 'toggle', 'default' => false,
                    ],
                    'back_drop' => [
                        'label' => 'گودی پشت', 'min' => 4, 'max' => 40, 'step' => 1,
                        'default' => 22, 'unit' => 'سانتی‌متر',
                    ],
                ]),
                ['neckline' => 'strap', 'strap_width' => 1.5, 'boning' => false, 'bust_cups' => false, 'closure' => 'zip'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params['neckline'] = 'strap';
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, [
            'prefix' => 'slip',
            'neck_drop' => $this->flag($params, 'cowl', false) ? 14 : 8,
            'back_drop' => (float) $this->param($params, 'back_drop', 22),
        ]);

        $skirt = $this->gownSkirt('skirt_a_line', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 108),
            'hem_flare' => 22,
        ], 'slip');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        // همه‌چیز روی اریب بریده می‌شود
        foreach ($pieces as $index => $piece) {
            if (in_array($piece['meta']['girth_role'] ?? '', ['shell', ''], true)) {
                $pieces[$index]['meta']['bias'] = true;
            }
        }

        $pieces[] = $this->strapPiece(46, (float) $this->param($params, 'strap_width', 1.5), [
            'code' => 'slip-strap',
            'name' => 'بند قابل تنظیم',
            'cut' => 2,
            'meta' => ['notes' => ['با سگک تنظیم می‌شود؛ اندازهٔ نهایی را در پرو ببندید.']],
        ]);

        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, [
            'همهٔ قطعه‌ها روی اریب بریده می‌شوند؛ فرم را خودِ پارچه می‌گیرد و لباس ساسون نمی‌خواهد.',
            'اریب پارچهٔ بیشتری می‌خورد: قطعه‌ها روی زاویهٔ ۴۵ درجه چیده می‌شوند و بینشان پرت می‌ماند.',
            'پس از دوخت، لباس بیست‌وچهار ساعت آویزان بماند و بعد دمش صاف شود؛ اریب با وزن خودش دراز می‌شود.',
            'بست روی درز پهلوست، نه پشت؛ زیپ پشت روی اریب موج می‌اندازد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
