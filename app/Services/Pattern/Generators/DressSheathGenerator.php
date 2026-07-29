<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن غلافی.
 *
 * جذب‌ترین پیراهن این خانواده: از سرشانه تا دم، همه‌جا به تن می‌چسبد. تفاوتش با
 * پیراهن کوتاهِ یک‌تکه این است که این‌جا بالاتنه و دامن جدا درفت می‌شوند و در خط
 * کمر به هم دوخته می‌گردند؛ همان جایی که فرم کمر واقعاً ساخته می‌شود.
 *
 * سه چیز بدون آن‌ها این پیراهن پوشیده نمی‌شود و هر سه در الگو هست:
 *
 *   ساسون  سینه روی پهلو و کمر روی هر دو پنل؛ بدون آن‌ها «غلافی» فقط یک لوله است.
 *   چاک    دامن جذبِ بلندتر از زانو بدون چاک پشت، قدم برداشتن را ممکن نمی‌کند.
 *   بست    زیپ باید از باسن رد شود، وگرنه لباس از روی باسن بالا نمی‌آید.
 */
class DressSheathGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_sheath';
    }

    public function label(): string
    {
        return 'پیراهن غلافی';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge(
                $this->skirtLengthParam(64, 40, 105),
                $this->sleeveParam('none', 24, [
                    'none' => 'بدون آستین',
                    'set_in' => 'آستین حلقه‌ای',
                ]),
                [
                    'hem_flare' => [
                        'label' => 'باز شدن دم دامن در هر پهلو', 'min' => -3, 'max' => 12, 'step' => 0.5,
                        'default' => 1, 'unit' => 'سانتی‌متر',
                        'hint' => 'عدد منفی یعنی دم از باسن هم تنگ‌تر؛ آن‌وقت چاک پشت باید بلندتر شود.',
                    ],
                    'back_vent' => [
                        'label' => 'بلندی چاک مرکز پشت', 'min' => 0, 'max' => 35, 'step' => 1,
                        'default' => 14, 'unit' => 'سانتی‌متر',
                    ],
                ],
            ),
            ['fit' => 'fitted', 'lining' => 'full'],
            ['waist_dart_share' => 0.65, 'armhole_depth_extra' => 0.5],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // یک آزادی برای بالاتنه و دامن؛ آزادی غلافی عمداً کم است
        $ease = $this->dressEase($ease, $params, ['bust' => 5.0, 'waist' => 3.0, 'hip' => 4.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'skirt_length', 64);
        $seam = (string) $this->param($params, 'back_closure', 'zip') !== 'none';

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'sheath',
            'bust_dart' => true,
            'waist_dart' => true,
            'back_seam' => $seam,
            'front_name' => 'بالاتنه جلو',
            'back_name' => $seam ? 'بالاتنه پشت (درز مرکزی)' : 'بالاتنه پشت',
        ]);

        $skirt = $this->attachedSkirt($g, [
            'bodice_waist' => $waist,
            'prefix' => 'sheath',
            'type' => 'a_line',
            'length' => $length,
            'flare' => (float) $this->param($params, 'hem_flare', 1),
            'back_seam' => $seam,
            'front_name' => 'دامن غلافی جلو',
            'back_name' => 'دامن غلافی پشت',
        ]);

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $vent = (float) $this->param($params, 'back_vent', 14);

        if ($vent > 1 && $seam) {
            $skirt[1] = $this->markBackVent($skirt[1], $vent);
        }

        $pieces = array_merge($bodice, $skirt);
        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params);

        $pieces = array_merge(
            $pieces,
            $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'sheath-']),
            [$this->backNeckFacingPiece($g, ['prefix' => 'sheath-', 'width' => 6])],
        );

        if ((string) $this->param($params, 'sleeve_style', 'none') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'sheath-']);
        }

        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'فرم این پیراهن از ساسون می‌آید نه از کش پارچه؛ اگر ساسون‌ها را بردارید، غلافی به لوله تبدیل می‌شود.',
            $vent > 1 && $seam
                ? 'چاک مرکز پشت باز است؛ بدون آن دامن جذبِ بلندتر از زانو اجازهٔ قدم برداشتن نمی‌دهد.'
                : 'هشدار: این دامن چاک ندارد. اگر بلندی از زانو گذشت، چاک پشت را باز کنید.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
