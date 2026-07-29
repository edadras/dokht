<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن زنانه جذب (بلوز).
 *
 * تفاوتش با پیراهن مردانه در دکمه و یقه نیست، در ساسون است: بلوز جذب ساسون
 * سینه دارد و بدون آن، پارچه روی سینه کشیده می‌شود و لای دکمه‌ها باز می‌ماند.
 *
 * همان «باز ماندن لای دکمه» مشکل شناخته‌شدهٔ این مدل است و دو علاج در الگو
 * دارد: ساسون سینه که پارچه را روی سینه جا می‌دهد، و یک دکمهٔ اضافه درست روی
 * خط سینه. دومی را خودِ الگو حساب می‌کند: جای دکمه‌ها طوری چیده می‌شود که یکی
 * دقیقاً روی پرحجم‌ترین جای سینه بیفتد.
 */
class ShirtFittedBlouseGenerator extends ShirtBaseGenerator
{
    public static function key(): string
    {
        return 'shirt_fitted_blouse';
    }

    public function label(): string
    {
        return 'بلوز جذب زنانه';
    }

    public function paramsSchema(): array
    {
        return $this->shirtSchema(
            ['fit' => 'fitted', 'sleeve_length' => 56, 'body_length' => 16, 'armhole_depth_extra' => 3],
            array_merge([
                'button_stand' => [
                    'label' => 'اضافه جای دکمه', 'min' => 1, 'max' => 3.5, 'step' => 0.25,
                    'default' => 1.5, 'unit' => 'سانتی‌متر',
                ],
                'collar_height' => [
                    'label' => 'بلندی یقه', 'min' => 4, 'max' => 11, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                ],
                'button_spacing' => [
                    'label' => 'فاصله دکمه‌ها', 'min' => 5, 'max' => 12, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                    'hint' => 'فاصلهٔ کمتر یعنی لای دکمه‌ها کمتر باز می‌ماند.',
                ],
            ], $this->pocketParam(false)),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $params = $this->withDropShoulder($params);
        $ease = $this->shirtEase($ease, $params);
        $g = $this->bodiceMetrics($measurements, $ease, $params);
        $stand = (float) $this->param($params, 'button_stand', 1.5);

        [$front, $back, $extras] = $this->shirtBody($g, $params, [
            'extension' => $stand,
            'prefix' => 'blouse',
        ]);

        $sleeves = $this->shirtSleeves($measurements, $ease, $params, [$front, $back], [
            'sleeve_name' => 'آستین بلوز',
        ]);

        $neckHalf = ($front['meta']['neck_length'] ?? 12) + ($back['meta']['neck_length'] ?? 9);

        $length = Geometry::height($front['outline']);
        $placket = $this->placket(
            $stand,
            $length,
            spacing: (float) $this->param($params, 'button_spacing', 7),
        );

        // یکی از دکمه‌ها باید دقیقاً روی پرحجم‌ترین جای سینه بیفتد
        $placket = $this->pinButtonToBust($placket, (float) ($g['bust_apex_y'] ?? $g['bust_y']));

        $pieces = array_merge([$front, $back], $extras, $sleeves, [
            $this->shirtCollar($neckHalf + $stand, (float) $this->param($params, 'collar_height', 7)),
            $placket,
        ]);

        if ($this->flag($params, 'chest_pocket', false)) {
            $pieces[] = $this->patchPocket(10, 11, ['name' => 'جیب سینه', 'radius' => 1.5]);
        }

        return $this->finish($this->noteOn($pieces, [
            'ساسون سینه پارچه را روی سینه جا می‌دهد؛ بدون آن لای دکمه‌ها باز می‌ماند.',
            'جای دکمه‌ها طوری چیده شده که یکی روی خط سینه بیفتد — همان‌جا که پیراهن باز می‌شود.',
        ]));
    }

    /**
     * جابه‌جا کردن جای دکمه‌ها تا نزدیک‌ترینشان دقیقاً روی خط سینه بنشیند.
     */
    protected function pinButtonToBust(array $placket, float $bustY): array
    {
        $drills = $placket['drills'] ?? [];

        if ($drills === []) {
            return $placket;
        }

        $closest = null;
        $best = INF;

        foreach ($drills as $index => $drill) {
            $distance = abs(((float) $drill['y']) - $bustY);

            if ($distance < $best) {
                $best = $distance;
                $closest = $index;
            }
        }

        if ($closest === null || $best < 0.2) {
            return $placket;
        }

        $shift = $bustY - (float) $drills[$closest]['y'];

        // همهٔ دکمه‌ها با هم جابه‌جا می‌شوند تا فاصله‌شان یکنواخت بماند
        foreach ($drills as $index => $drill) {
            $drills[$index]['y'] = round(((float) $drill['y']) + $shift, 2);
        }

        $placket['drills'] = array_values(array_filter(
            $drills,
            fn (array $drill) => (float) $drill['y'] > 1.0,
        ));

        $placket['meta']['button_count'] = count($placket['drills']);
        // جایی که یک دکمه عمداً رویش نشسته: پرحجم‌ترین نقطهٔ سینه
        $placket['meta']['button_at_bust'] = round($bustY, 2);
        $placket['meta']['notions'] = [[
            'type' => 'button',
            'label' => 'دکمهٔ جلو',
            'count' => count($placket['drills']),
        ]];

        return $placket;
    }
}
