<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;

/**
 * لباس عقد.
 *
 * پوشیده‌تر از لباس عروس و آستین‌دار؛ معمولاً در مجلسی کوچک‌تر و نشسته پوشیده
 * می‌شود، و همین دو نکته کل الگو را شکل می‌دهد:
 *
 *   نشستن   عروس در مراسم عقد ساعت‌ها می‌نشیند. پس دامن نباید جذب باشد و
 *           بالاتنه نباید تیغهٔ فنر داشته باشد؛ فنر در حالت نشسته به دنده
 *           فشار می‌آورد.
 *   پوشش    آستین بلند و یقهٔ بسته‌تر، معمولاً از تور یا دانتل روی آستر.
 *
 * برای همین پیش‌فرض‌های این مدل با لباس عروس فرق دارند: دامن خط A، بدون فنر،
 * با آستین بلند.
 */
class EngagementDressGenerator extends EveningBaseGenerator
{
    // آستین واقعی می‌خواهد، نه مستطیل: سرآستین باید در حلقه بنشیند
    use BuildsSleeve;

    public static function key(): string
    {
        return 'engagement_dress';
    }

    public function label(): string
    {
        return 'لباس عقد';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(112), [
                    'sleeve' => [
                        'label' => 'آستین', 'type' => 'select', 'default' => 'long',
                        'options' => ['long' => 'بلند', 'three_quarter' => 'سه‌ربع', 'cap' => 'حلقه‌ای'],
                    ],
                    'sleeve_fabric' => [
                        'label' => 'پارچهٔ آستین', 'type' => 'select', 'default' => 'lace',
                        'options' => ['lace' => 'دانتل یا تور', 'same' => 'همان پارچهٔ لباس'],
                    ],
                    'skirt_flare' => [
                        'label' => 'گشادی دم دامن', 'min' => 15, 'max' => 80, 'step' => 5,
                        'default' => 40, 'unit' => 'سانتی‌متر',
                    ],
                ]),
                ['neckline' => 'scoop', 'boning' => false, 'closure' => 'buttons'],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, [
            'prefix' => 'engagement',
            'neck_drop' => 5,
            'back_drop' => 6,
        ]);

        $skirt = $this->gownSkirt('skirt_a_line', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 112),
            'hem_flare' => (float) $this->param($params, 'skirt_flare', 40),
        ], 'engagement');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $sleeve = (string) $this->param($params, 'sleeve', 'long');
        $lace = $this->param($params, 'sleeve_fabric', 'lace') === 'lace';

        $armLength = (float) ($measurements['arm_length'] ?? 58);
        $length = match ($sleeve) {
            'cap' => 14.0,
            'three_quarter' => $armLength * 0.75,
            default => $armLength,
        };

        $armhole = 0.0;

        foreach ($bodice as $piece) {
            $armhole += (float) ($piece['meta']['armhole_length'] ?? 0);
        }

        $sleeves = $armhole > 5
            ? $this->sleevePieces($measurements, $ease, array_merge($params, ['cap_ease' => 1.0]), [
                'armhole_length' => $armhole,
                'length' => $length,
                'no_cuff' => true,
                'sleeve_name' => 'آستین'.($lace ? ' (دانتل)' : ''),
            ])
            : [];

        foreach ($sleeves as $index => $piece) {
            $sleeves[$index]['meta']['notes'][] = $lace
                ? 'از دانتل یا تور بریده می‌شود و آستر ندارد؛ درزش را نوار باریک بپوشانید.'
                : 'از همان پارچهٔ لباس بریده می‌شود.';
        }

        $pieces = array_merge($bodice, $sleeves, $skirt);
        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'عروس در مراسم عقد ساعت‌ها می‌نشیند؛ برای همین این مدل تیغهٔ فنر ندارد و دامنش جذب نیست.',
            'اگر آستین از دانتل است، لبه‌اش را با خودِ نقش دانتل تمام کنید، نه با دوخت برگردان.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
