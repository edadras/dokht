<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن چادری (تِنت / تراپز).
 *
 * از سرشانه و حلقه به بعد هیچ‌جا به تن نمی‌چسبد: درز پهلو از همان خط زیر بغل
 * شروع به باز شدن می‌کند و تا دم می‌رود. تفاوتش با شیفت همین است — شیفت راست
 * می‌آید، این یکی مثلث می‌شود.
 *
 * دو چیز این مدل را می‌سازد و هر دو در الگو صریح‌اند:
 *
 *   نقطهٔ شروعِ باز شدن باید بالا بماند. اگر حلقه گود شود، مثلث از پایین شروع
 *   می‌شود و لباس به‌جای چادری، پیراهنِ گشادِ بی‌فرم درمی‌آید.
 *
 *   سرشانه و حلقه باید روی بدن بمانند. تمام وزن این لباس از سرشانه آویزان است و
 *   هیچ نقطهٔ اتکای دیگری ندارد؛ سرشانهٔ گشاد یعنی لباسی که از تن می‌افتد.
 */
class DressTentGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_tent';
    }

    public function label(): string
    {
        return 'پیراهن چادری';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge(
                [
                    'length' => [
                        'label' => 'بلندی از خط کمر', 'min' => 25, 'max' => 105, 'step' => 1,
                        'default' => 55, 'unit' => 'سانتی‌متر',
                    ],
                    'swing' => [
                        'label' => 'باز شدن دم در هر پهلو', 'min' => 8, 'max' => 45, 'step' => 1,
                        'default' => 24, 'unit' => 'سانتی‌متر',
                        'hint' => 'از خط زیر بغل تا دم؛ عدد کمتر از هشت دیگر چادری نیست، شیفت است.',
                    ],
                    'pocket' => [
                        'label' => 'جیب درزی روی پهلو', 'type' => 'toggle', 'default' => true,
                    ],
                ],
                $this->sleeveParam('set_in', 18, [
                    'none' => 'بدون آستین',
                    'set_in' => 'آستین حلقه‌ای کوتاه',
                ]),
            ),
            ['fit' => 'regular', 'back_closure' => 'none', 'lining' => 'none'],
            ['neck_width_extra' => 2, 'front_neck_depth_extra' => 2.5, 'armhole_depth_extra' => 0.5],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->dressEase($ease, $params, ['bust' => 8.0, 'waist' => 8.0, 'hip' => 6.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 55);
        $swing = (float) $this->param($params, 'swing', 24);
        $seam = (string) $this->param($params, 'back_closure', 'none') !== 'none';

        [$pieces] = $this->dressBodice($g, $params, [
            'prefix' => 'tent',
            'shape' => 'straight',
            'bottom_tag' => 'hem',
            'length' => $length,
            'hem_flare' => $swing,
            'waist_dart' => false,
            'bust_dart' => false,
            'back_seam' => $seam,
            'front_name' => 'جلوی پیراهن چادری',
            'back_name' => 'پشت پیراهن چادری',
        ]);

        // این لباس روی خط باسن دور بدن نمی‌پیچد؛ از کنارش رد می‌شود. پس خط نشانهٔ
        // باسن برداشته می‌شود تا کسی «دور باسن تمام‌شده»ی صد و پنجاه سانتی را
        // اندازهٔ لباس نخواند — همان قراری که عبا و کافتان این کاتالوگ دارند.
        foreach ($pieces as $index => $piece) {
            $pieces[$index] = $this->dropMarker($piece, 'hip');
        }

        $bodice = $pieces;

        if ($seam) {
            [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params, ['below' => 0.0]);
        } else {
            $closureNotes = ['این پیراهن بست ندارد و از سر پوشیده می‌شود؛ چون هیچ‌جا به تن نمی‌چسبد، بست هم لازم ندارد.'];
        }

        $pieces = array_merge(
            $pieces,
            $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'tent-']),
            [$this->backNeckFacingPiece($g, ['prefix' => 'tent-', 'width' => 6])],
        );

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'tent-']);
        }

        if ($this->flag($params, 'pocket', true)) {
            // جیب درزی روی پهلو: در درزی که خودش باز است می‌نشیند، پس کیسه‌اش
            // ساده است و روی لباس هیچ دوختِ دیده‌شدنی نمی‌گذارد.
            $pieces[] = $this->bandPiece('tent-side-pocket', 'کیسهٔ جیب درزی', 16, 34, [
                'cut' => 4, 'part' => 'pocket',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دو کیسه برای هر جیب؛ دهانهٔ جیب روی درز پهلو، چهار سانتی‌متر پایین‌تر از خط کمر شروع می‌شود.'],
                ],
            ]);
        }

        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($closureNotes, [
            'باز شدن از خط زیر بغل شروع می‌شود، نه از کمر؛ همین است که این لباس را چادری می‌کند.',
            'هشدار: چون هیچ‌جای این لباس به تن نمی‌چسبد، تنها تکیه‌گاهش سرشانه است. عرض سرشانه را در پرو تنگ کنید، نه دم لباس را.',
            'دم لباس در پهلو به اندازهٔ '.$this->fa(round($swing, 1)).' سانتی‌متر از خط زیر بغل بازتر است؛ روی پارچهٔ سنگین این عدد را کم کنید تا لباس مثلثی نایستد.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
