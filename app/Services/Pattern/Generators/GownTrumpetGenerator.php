<?php

namespace App\Services\Pattern\Generators;

/**
 * لباس شب ترامپت.
 *
 * از کمر تا زیر زانو قالب بدن است و از آن‌جا کلوش می‌شود. روی کاغذ شبیه ماهی است
 * و در عمل نه؛ تفاوتشان دو عدد است و همان دو عدد لباس را عوض می‌کنند:
 *
 *   خط باز شدن  در ترامپت بالاتر است — حدود بالای زانو، نه زیر زانو. یعنی زانو
 *               آزاد می‌ماند و می‌شود با این لباس راه رفت و نشست؛ کاری که با
 *               لباس ماهی تقریباً ممکن نیست.
 *   تنگی ران    در ترامپت ملایم‌تر است. لباس ماهی ران و زانو را چند سانتی‌متر از
 *               باسن هم تنگ‌تر می‌گیرد؛ ترامپت تقریباً به همان اندازهٔ باسن می‌آید
 *               و فقط از خط کلوش باز می‌شود.
 *
 * نتیجه‌اش لباسی است با همان سیلوئتِ ساعت‌شنی ولی پوشیدنی برای یک شب کامل.
 */
class GownTrumpetGenerator extends EveningBaseGenerator
{
    public static function key(): string
    {
        return 'gown_trumpet';
    }

    public function label(): string
    {
        return 'لباس شب ترامپت';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->fitParam(),
            $this->eveningSchema(array_merge($this->gownLengthParam(112), [
                'flare_start' => [
                    'label' => 'شروع کلوش از کمر', 'min' => 35, 'max' => 80, 'step' => 1,
                    'default' => 52, 'unit' => 'سانتی‌متر',
                    'hint' => 'حدود بالای زانو. اگر پایین‌تر از زیر زانو ببرید، لباس ماهی می‌شود نه ترامپت.',
                ],
                'thigh_taper' => [
                    'label' => 'تنگی ران و زانو', 'min' => 0, 'max' => 4, 'step' => 0.5,
                    'default' => 1, 'unit' => 'سانتی‌متر',
                    'hint' => 'هر پهلو این‌قدر از خط باسن تنگ‌تر می‌شود؛ در ترامپت عمداً ملایم است.',
                ],
                'hem_flare' => [
                    'label' => 'گشادی دم', 'min' => 12, 'max' => 45, 'step' => 1,
                    'default' => 28, 'unit' => 'سانتی‌متر',
                ],
            ])),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // یک آزادی برای بالاتنه و دامن، وگرنه دو کمر به هم نمی‌رسند
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => 'trumpet']);

        $length = (float) $this->param($params, 'skirt_length', 112);
        $start = (float) $this->param($params, 'flare_start', 52);

        // نام پارامترهای دامن ترامپت همان‌هاست که خودِ آن دامن می‌شناسد
        $skirt = $this->gownSkirt('skirt_trumpet', $measurements, $ease, [
            'length' => $length,
            'flare_start' => $start,
            'thigh_taper' => (float) $this->param($params, 'thigh_taper', 1),
            'flare' => (float) $this->param($params, 'hem_flare', 28),
            'vent_length' => 0,
        ], 'trumpet');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, $this->gownNotes($params), [
            'کلوش از '.$this->fa(round($start, 1)).' سانتی‌متری کمر شروع می‌شود؛ همین بالا بودنِ خط باز شدن است که ترامپت را از ماهی جدا می‌کند.',
            'تنگی ران عمداً ملایم است تا زانو آزاد بماند؛ اگر آن را زیاد کنید، لباس عملاً ماهی می‌شود.',
            'خط باز شدن باید روی خودِ زانوی مشتری اندازه گرفته شود، نه از جدول؛ چند سانتی‌متر پایین‌تر یعنی قدم برداشتن سخت.',
        ]);

        return $this->finish($this->noted($pieces, array_map(
            fn (string $t) => ['type' => 'info', 'text' => $t],
            $notes,
        )));
    }
}
