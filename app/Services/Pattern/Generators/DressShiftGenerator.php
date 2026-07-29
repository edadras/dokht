<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن شیفت.
 *
 * راست از سرشانه تا دم، بدون خط کمر و بدون ساسون کمر. تنها ساسونی که دارد ساسون
 * سینه است و کارش هم فرم دادن به کمر نیست؛ فقط جا باز کردن برای سینه است تا
 * لباس روی شکم بالا نزند.
 *
 * پس این مدل تنها عضو این خانواده است که «خط کمر» ندارد و بنابراین اصلاً نمی‌تواند
 * از جای همیشگی بشکند. در عوض جای خطای خودش را دارد: چون هیچ‌جا به تن نمی‌چسبد،
 * تمام تکیه‌گاهش سرشانه است. سرشانهٔ گشاد یعنی لباسی که از تن می‌افتد و حلقهٔ
 * زیادی گود یعنی لباسی که از پهلو باز است.
 */
class DressShiftGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_shift';
    }

    public function label(): string
    {
        return 'پیراهن شیفت';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge(
                [
                    'length' => [
                        'label' => 'بلندی از خط کمر', 'min' => 25, 'max' => 95, 'step' => 1,
                        'default' => 42, 'unit' => 'سانتی‌متر',
                    ],
                    'hem_flare' => [
                        'label' => 'باز شدن دم در هر پهلو', 'min' => 0, 'max' => 14, 'step' => 0.5,
                        'default' => 2, 'unit' => 'سانتی‌متر',
                        'hint' => 'صفر یعنی کاملاً راست؛ دو تا چهار سانتی‌متر شیفت را روی باسن راحت‌تر می‌کند.',
                    ],
                    'bust_dart' => [
                        'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                        'hint' => 'بدون آن، لباس روی سینه بالا می‌رود و دمش جلو کوتاه می‌شود.',
                    ],
                ],
                $this->sleeveParam('none', 20, [
                    'none' => 'بدون آستین',
                    'set_in' => 'آستین حلقه‌ای کوتاه',
                ]),
            ),
            ['fit' => 'regular', 'back_closure' => 'zip', 'lining' => 'none'],
            ['neck_width_extra' => 1.5, 'front_neck_depth_extra' => 1.5, 'armhole_depth_extra' => 2],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->dressEase($ease, $params, ['bust' => 7.0, 'waist' => 7.0, 'hip' => 5.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 42);
        $seam = (string) $this->param($params, 'back_closure', 'zip') !== 'none';

        // یک‌تکه: خط کمر اصلاً وجود ندارد، پس لبهٔ پایین «دم» است نه «کمر»
        [$pieces] = $this->dressBodice($g, $params, [
            'prefix' => 'shift',
            'shape' => 'straight',
            'bottom_tag' => 'hem',
            'length' => $length,
            'hem_flare' => (float) $this->param($params, 'hem_flare', 2),
            'waist_dart' => false,
            'bust_dart' => $this->flag($params, 'bust_dart', true),
            'back_seam' => $seam,
            'front_name' => 'جلوی پیراهن شیفت',
            'back_name' => $seam ? 'پشت پیراهن شیفت (درز مرکزی)' : 'پشت پیراهن شیفت',
        ]);

        $bodice = $pieces;
        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params, ['below' => 0.0]);

        $pieces = array_merge(
            $pieces,
            $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'shift-']),
            [$this->backNeckFacingPiece($g, ['prefix' => 'shift-', 'width' => 6])],
        );

        if ((string) $this->param($params, 'sleeve_style', 'none') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'shift-']);
        }

        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($closureNotes, [
            'این پیراهن ساسون کمر ندارد و از سرشانه تا دم راست می‌آید؛ اگر کمرگیری خواستید، مدل غلافی را انتخاب کنید نه این را.',
            'همهٔ وزن لباس روی سرشانه است؛ سرشانهٔ گشاد یعنی لباسی که روی تن نمی‌ایستد.',
            $this->flag($params, 'bust_dart', true)
                ? 'ساسون سینه روی درز پهلوست؛ بدون آن دم لباس در جلو بالا می‌آید.'
                : 'هشدار: ساسون سینه برداشته شده است؛ روی بدنی با سینهٔ درشت، دم لباس در جلو بالا می‌زند.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
