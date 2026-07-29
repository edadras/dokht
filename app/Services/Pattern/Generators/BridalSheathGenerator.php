<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Generators\Concerns\BuildsSleeve;

/**
 * لباس عروس راسته (کرپ).
 *
 * لباس عروسی که حجم ندارد: راسته، ساده، و کاملاً وابسته به دقت اندازه.
 *
 * انتخاب این مدل یعنی پذیرفتن یک معامله: هیچ چینی نیست که ایراد اندازه را
 * بپوشاند، ولی در عوض عروس می‌تواند بنشیند، برقصد و راه برود. برای همین
 * چاک پشت این‌جا اختیاری نیست، فقط اندازه‌اش اختیاری است.
 *
 * آستر باید کاملاً هم‌فرم باشد؛ در لباس راسته هر چروکِ آستر از روی پارچه
 * دیده می‌شود.
 */
class BridalSheathGenerator extends EveningBaseGenerator
{
    // آستین واقعی می‌خواهد، نه مستطیل: سرآستین باید در حلقه بنشیند
    use BuildsSleeve;

    public static function key(): string
    {
        return 'bridal_sheath';
    }

    public function label(): string
    {
        return 'لباس عروس راسته (کرپ)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam('fitted'),
            $this->eveningSchema(
                array_merge($this->gownLengthParam(116), [
                    'vent_length' => [
                        'label' => 'بلندی چاک پشت', 'min' => 10, 'max' => 70, 'step' => 5,
                        'default' => 35, 'unit' => 'سانتی‌متر',
                        'hint' => 'بدون چاک، لباس راسته اجازهٔ قدم برداشتن نمی‌دهد.',
                    ],
                    'sleeve' => [
                        'label' => 'آستین', 'type' => 'select', 'default' => 'none',
                        'options' => ['none' => 'بی‌آستین', 'cap' => 'آستین حلقه‌ای', 'long' => 'آستین بلند تور'],
                    ],
                ]),
                ['neckline' => 'v', 'boning' => false],
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'sheath']);

        $skirt = $this->gownSkirt('skirt_pencil', $measurements, $ease, [
            'length' => (float) $this->param($params, 'skirt_length', 116),
            'vent_length' => (float) $this->param($params, 'vent', 35),
        ], 'sheath');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        $sleeve = (string) $this->param($params, 'sleeve', 'none');
        $notes = [];

        if ($sleeve !== 'none') {
            $length = $sleeve === 'long' ? ((float) ($measurements['arm_length'] ?? 58)) : 14.0;

            $armhole = 0.0;

            foreach ($bodice as $piece) {
                $armhole += (float) ($piece['meta']['armhole_length'] ?? 0);
            }

            if ($armhole > 5) {
                foreach ($this->sleevePieces($measurements, $ease, array_merge($params, ['cap_ease' => 1.0]), [
                    'armhole_length' => $armhole,
                    'length' => $length,
                    'no_cuff' => true,
                    'sleeve_name' => $sleeve === 'long' ? 'آستین بلند تور' : 'آستین حلقه‌ای',
                ]) as $piece) {
                    $piece['meta']['notes'][] = $sleeve === 'long'
                        ? 'از تور بریده می‌شود؛ درزش را نوار باریک بپوشانید تا از رو دیده نشود.'
                        : 'آستین حلقه‌ای کوتاه که فقط سر شانه را می‌پوشاند.';
                    $pieces[] = $piece;
                }
            }

            $notes[] = 'آستین جدا بریده می‌شود و در حلقه دوخته می‌شود؛ اندازهٔ حلقه را در پرو بررسی کنید.';
        }

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $notes, $this->gownNotes($params), [
            'در لباس راسته هیچ چینی نیست که ایراد اندازه را بپوشاند؛ اندازه‌ها را دو بار بگیرید.',
            'آستر باید کاملاً هم‌فرم باشد؛ هر چروکِ آستر از روی پارچه دیده می‌شود.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
