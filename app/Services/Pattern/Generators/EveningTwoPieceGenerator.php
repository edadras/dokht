<?php

namespace App\Services\Pattern\Generators;

/**
 * ست دوتکهٔ مجلسی.
 *
 * تاپ کوتاه به‌علاوهٔ دامن بلند، به‌جای یک لباس یک‌تکه.
 *
 * سود واقعی‌اش همان چیزی است که در تانکینی هم بود: چون دو تکه است، «قد تنه»
 * — سخت‌ترین اندازهٔ لباس یک‌تکه — اصلاً مسئله نیست، و هر تکه می‌تواند سایز
 * خودش را داشته باشد.
 *
 * تنها نکتهٔ فنی‌اش فاصلهٔ میان دو تکه است: اگر تاپ کوتاه و دامن کمرکوتاه
 * باشد، فاصله باز می‌ماند. این‌جا هر دو عدد جداست تا خودتان تصمیم بگیرید چقدر
 * باز بماند.
 */
class EveningTwoPieceGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_two_piece';
    }

    public function label(): string
    {
        return 'ست دوتکهٔ مجلسی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(105), [
                    'top_length' => [
                        'label' => 'بلندی تاپ از خط کمر', 'min' => -20, 'max' => 6, 'step' => 1,
                        'default' => -10, 'unit' => 'سانتی‌متر',
                        'hint' => 'عدد منفی یعنی تاپ بالاتر از خط کمر تمام می‌شود.',
                    ],
                    'skirt_style' => [
                        'label' => 'فرم دامن', 'type' => 'select', 'default' => 'a_line',
                        'options' => [
                            'a_line' => 'خط A',
                            'gathered' => 'چین‌دار',
                            'pencil' => 'راسته',
                            'tiered' => 'طبقه‌ای',
                        ],
                    ],
                ]),
                ['boning' => false],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'twopiece']);

        // تاپ بالاتر از خط کمر تمام می‌شود
        $gap = abs(min(0.0, (float) $this->param($params, 'top_length', -10)));

        foreach ($bodice as $index => $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            $bodice[$index]['name'] = str_replace('بالاتنه', 'تاپ', (string) $piece['name']);
            $bodice[$index]['meta']['notes'][] = 'دم تاپ '.$this->fa($gap)
                .' سانتی‌متر بالاتر از خط کمر تمام می‌شود؛ همین فاصله میان دو تکه دیده می‌شود.';
        }

        $skirtKey = match ((string) $this->param($params, 'skirt_style', 'a_line')) {
            'gathered' => 'skirt_gathered',
            'pencil' => 'skirt_pencil',
            'tiered' => 'skirt_tiered',
            default => 'skirt_a_line',
        };

        $skirt = $this->gownSkirt($skirtKey, $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 105),
            'waistband' => true,
            'zip' => 'side',
        ], 'twopiece');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $this->gownNotes($params), [
            'چون دو تکه است، قد تنه مسئله نیست و هر تکه می‌تواند سایز خودش را داشته باشد.',
            'دامن کمربند و زیپ خودش را دارد؛ تاپ جدا بسته می‌شود.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
