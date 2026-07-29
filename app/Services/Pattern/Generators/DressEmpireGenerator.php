<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن امپایر روزمره.
 *
 * خط کمر لباس زیر سینه می‌نشیند و از همان‌جا دامن آزاد می‌ریزد. برخلاف لباس شبِ
 * امپایر، این یکی نه فنر دارد نه فنجان سینه؛ پیراهنی است که پوشیده و شسته
 * می‌شود.
 *
 * ولی یک نکتهٔ فنی دارد که همیشه فراموش می‌شود و هر بار همان‌جا خراب می‌شود:
 * **خط کمرِ این لباس، خط کمر نیست.** دور بدن زیر سینه از دور کمر بیشتر است. اگر
 * بالاتنه با دور کمر درفت شود، زیر سینه تنگ می‌افتد و اگر دامن با دور کمر درفت
 * شود، به بالاتنه نمی‌رسد. پس این‌جا:
 *
 *   بالاتنه با دورِ زیر سینه درفت می‌شود، نه با دور کمر.
 *   بلندی بالاتنه از خودِ بدن می‌آید: سرشانه تا سینه، به‌اضافهٔ چند سانتی‌متر.
 *   دامن با آزادی‌ای درفت می‌شود که دور کمرش دقیقاً به همان عدد برسد.
 *
 * و چون تمام وزن لباس از یک درزِ باریک زیر سینه آویزان است، آن درز لایی می‌خورد؛
 * درز شل یعنی لباسی که پایین می‌آید و خط کمرش روی شکم می‌افتد.
 */
class DressEmpireGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_empire';
    }

    public function label(): string
    {
        return 'پیراهن امپایر روزمره';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge(
                $this->skirtLengthParam(78, 35, 115, 'بلندی دامن از زیر سینه'),
                $this->sleeveParam('set_in', 20, [
                    'none' => 'بدون آستین',
                    'set_in' => 'آستین حلقه‌ای کوتاه',
                ]),
                [
                    'under_bust_drop' => [
                        'label' => 'فاصلهٔ درز از نوک سینه', 'min' => 2, 'max' => 12, 'step' => 0.5,
                        'default' => 5, 'unit' => 'سانتی‌متر',
                        'hint' => 'جای درز زیر سینه از نوک سینه به پایین؛ کمتر از سه سانتی‌متر روی خودِ سینه می‌افتد.',
                    ],
                    'under_bust' => [
                        'label' => 'فرم زیر سینه', 'type' => 'select', 'default' => 'gather',
                        'options' => [
                            'gather' => 'چین زیر سینه',
                            'dart' => 'ساسون زیر سینه',
                        ],
                    ],
                    'skirt_fullness' => [
                        'label' => 'نسبت پُری دامن', 'min' => 1.2, 'max' => 2.6, 'step' => 0.1,
                        'default' => 1.7,
                        'hint' => 'پارچهٔ دامن چند برابر درزِ زیر سینه بریده شود.',
                    ],
                    'band' => [
                        'label' => 'نوار زیرسینه', 'type' => 'toggle', 'default' => true,
                    ],
                ],
            ),
            ['fit' => 'regular', 'back_closure' => 'zip', 'lining' => 'none'],
            ['waist_dart_share' => 0.55, 'armhole_depth_extra' => 1],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->dressEase($ease, $params, ['bust' => 6.0, 'waist' => 4.0, 'hip' => 6.0]);

        // بالاتنه با دورِ زیر سینه درفت می‌شود، نه با دور کمر
        $bust = $this->m($measurements, 'bust', 92);
        $underBust = $this->m($measurements, 'under_bust', $bust - 14);
        $forBodice = array_merge($measurements, ['waist' => $underBust]);

        // بلندی بالاتنه از خودِ بدن: سرشانه تا سینه، به‌اضافهٔ فاصلهٔ خواسته‌شده
        $lift = max(4.0, min(22.0,
            $this->m($measurements, 'front_length', 43)
            - ($this->m($measurements, 'shoulder_to_bust', 26) + (float) $this->param($params, 'under_bust_drop', 5)),
        ));
        $params['bodice_length_extra'] = -$lift;

        $g = $this->blockMetrics($forBodice, $ease, $params);
        $seam = (string) $this->param($params, 'back_closure', 'zip') !== 'none';

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'empire',
            'shape' => 'fitted',
            'bust_dart' => true,
            'waist_dart' => true,
            'back_seam' => $seam,
            'front_name' => 'بالاتنه جلو (تا زیر سینه)',
            'back_name' => $seam ? 'بالاتنه پشت (درز مرکزی)' : 'بالاتنه پشت',
        ]);

        // چین زیر سینه به‌جای ساسون: همان پارچه، راه بستن دیگر
        if ((string) $this->param($params, 'under_bust', 'gather') === 'gather') {
            $bodice[0] = $this->dartsToGathers($bodice[0], 'waist', 'چین زیر سینه');
            $waist = $this->edgeGirth($bodice, 'waist');
        }

        // دامن با آزادی‌ای که دور کمرش دقیقاً به درزِ زیر سینه برسد
        $skirtEase = array_merge($ease, [
            'waist' => max(0.0, $waist - $this->m($measurements, 'waist', 74)),
        ]);

        $skirt = $this->catalogSkirt('skirt_gathered', $measurements, $skirtEase, [
            'length' => (float) $this->param($params, 'skirt_length', 78),
            'fullness' => (float) $this->param($params, 'skirt_fullness', 1.7),
            'dart_share' => 0,
        ], 'empire');

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params, [
            'zip_reason' => 'تا از سر و شانه رد شود؛ پایین‌تر از این لازم نیست چون لباس از زیر سینه به پایین آزاد است.',
        ]);

        $pieces = array_merge(
            $pieces,
            $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'empire-']),
            [$this->backNeckFacingPiece($g, ['prefix' => 'empire-', 'width' => 5])],
        );

        if ($this->flag($params, 'band', true)) {
            $pieces[] = $this->bandPiece('empire-under-bust-band', 'نوار زیرسینه', max(20.0, $waist / 2), 3.5, [
                'cut' => 2, 'part' => 'binding',
                'meta' => [
                    'girth_role' => 'trim',
                    'interfacing' => true,
                    'target_waist' => round($waist, 2),
                    'notes' => ['تمام وزن لباس از همین درز آویزان است؛ نوار را لایی بزنید و گشادتر از '.$this->fa(round($waist, 1)).' سانتی‌متر نبُرید.'],
                ],
            ]);
        }

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'empire-']);
        }

        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'بالاتنه با دورِ زیر سینه ('.$this->fa(round($underBust, 1)).' سانتی‌متر) درفت شده است، نه با دور کمر؛ اگر با دور کمر ببُرید، زیر سینه تنگ می‌افتد.',
            'درز زیر سینه '.$this->fa(round($lift, 1)).' سانتی‌متر بالاتر از خط کمر طبیعی می‌نشیند؛ این عدد از قد بالاتنه و جای سینهٔ همین بدن آمده، نه از جدول.',
            'اگر درزِ زیر سینه شل باشد، لباس پایین می‌آید و خط کمرش روی شکم می‌افتد.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
