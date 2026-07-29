<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب پرنسسی.
 *
 * بالاتنهٔ جذب روی دامن پرحجم. حجم دامن از زیردامن می‌آید نه از پارچهٔ رو —
 * همان قاعده‌ای که در دامن پرنسسی کاتالوگ هم هست.
 *
 * چیزی که این لباس را از یک «بالاتنه به‌علاوهٔ دامن پُر» جدا می‌کند، وزن است.
 * دامن پرحجم چند کیلو می‌شود و همهٔ آن وزن از خط کمر آویزان است. اگر بالاتنه
 * تیغهٔ فنر و نوار کمر نداشته باشد، لباس روی تن پایین می‌آید و خط کمر جابه‌جا
 * می‌شود. برای همین این‌جا نوار کمر داخلی پیش‌فرض روشن است.
 */
class EveningBallGownGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'evening_ball_gown';
    }

    public function label(): string
    {
        return 'لباس شب پرنسسی';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(array_merge($this->gownLengthParam(115), [
                'volume' => [
                    'label' => 'حجم دامن', 'type' => 'select', 'default' => 'medium',
                    'options' => [
                        'soft' => 'ملایم (دو برابر دور باسن)',
                        'medium' => 'متوسط (سه برابر)',
                        'grand' => 'پرحجم (چهار برابر)',
                    ],
                ],
                'petticoat' => [
                    'label' => 'زیردامن حجم‌دهنده', 'type' => 'toggle', 'default' => true,
                ],
                'waist_stay' => [
                    'label' => 'نوار کمر داخلی', 'type' => 'toggle', 'default' => true,
                    'hint' => 'وزن دامن را می‌گیرد تا لباس روی تن پایین نیاید.',
                ],
            ])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'ballgown']);

        $skirt = $this->gownSkirt('skirt_ball_gown', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 115),
            'volume' => (string) $this->param($params, 'volume', 'medium'),
            'petticoat' => $this->flag($params, 'petticoat', true),
        ], 'ballgown');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        if ($this->flag($params, 'waist_stay', true)) {
            $pieces[] = $this->bandPiece('ballgown-waist-stay', 'نوار کمر داخلی', max(20.0, $waist / 2), 2.5, [
                'cut' => 1, 'part' => 'facing',
                'meta' => [
                    'girth_role' => 'trim',
                    'interfacing' => true,
                    'notions' => [['type' => 'hook', 'label' => 'قزن نوار کمر داخلی', 'count' => 2]],
                    'notes' => [
                        'از نوار گروگرن بریده می‌شود و پیش از زیپ بسته می‌شود؛ وزن دامن روی این نوار می‌افتد، نه روی درزها.',
                    ],
                ],
            ]);
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'حجم از زیردامن می‌آید نه از پارچهٔ رو؛ پارچهٔ روی سنگین به‌جای ایستادن، می‌افتد.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
