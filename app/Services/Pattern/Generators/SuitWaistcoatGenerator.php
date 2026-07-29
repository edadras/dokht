<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * جلیقه رسمی (جلیقهٔ کت‌وشلوار).
 *
 * جلیقهٔ رسمی با جلیقهٔ معمولی چهار فرق دارد و هر چهار تا در الگو هست:
 *
 *   ۱. لبهٔ جلو نوک‌دار است: خط مرکز جلو چند سانتی‌متر پایین‌تر از درز پهلو تمام
 *      می‌شود. این نوک روی خودِ لبهٔ پایین ساخته می‌شود، نه با بلندکردن کل قطعه؛
 *      وگرنه درز پهلوی جلو از پشت بلندتر می‌شد.
 *   ۲. حلقه و یقه هر دو بالاتر و تنگ‌تر از لباس آستین‌دارند، وگرنه از زیر کت
 *      بیرون می‌زنند.
 *   ۳. پشت از آستر بریده می‌شود و تسمهٔ تنظیم با سگک دارد؛ همان چیزی که جلیقه را
 *      روی تن جمع می‌کند بی‌آنکه از جلو چروک بیندازد.
 *   ۴. چهار جیب فیلتاب: دو تا روی سینه و دو تا پایین.
 *
 * ساسون سینه روی درز پهلو می‌نشیند و پیش از دوخت، درز «راست» می‌شود؛ پس بعد از
 * بستن ساسون، درز پهلوی جلو دقیقاً هم‌اندازهٔ درز پهلوی پشت است.
 */
class SuitWaistcoatGenerator extends SuitBaseGenerator
{
    public static function key(): string
    {
        return 'suit_waistcoat';
    }

    public function label(): string
    {
        return 'جلیقه رسمی';
    }

    public function paramsSchema(): array
    {
        $schema = $this->suitSchema([
            'armhole_depth_extra' => 0,
            'front_neck_depth_extra' => 8,
            'waist_dart_share' => 0.6,
        ], [
            'armhole_lift' => [
                'label' => 'بالا آمدن حلقه آستین', 'min' => 0, 'max' => 6, 'step' => 0.5,
                'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'armhole_narrow' => [
                'label' => 'تنگ‌تر شدن حلقه از سرشانه', 'min' => 0, 'max' => 6, 'step' => 0.5,
                'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
            'hem_point' => [
                'label' => 'بلندی نوک لبه جلو', 'min' => 0, 'max' => 10, 'step' => 0.5,
                'default' => 4.5, 'unit' => 'سانتی‌متر',
            ],
            'back_strap' => [
                'label' => 'تسمه تنظیم پشت', 'type' => 'toggle', 'default' => true,
            ],
            'pocket_opening' => [
                'label' => 'دهانه جیب', 'min' => 7, 'max' => 14, 'step' => 0.5,
                'default' => 10, 'unit' => 'سانتی‌متر',
            ],
        ]);

        // جلیقه آستین ندارد و یقه‌اش هم یقهٔ کت نیست
        unset(
            $schema['sleeve_length'], $schema['cap_ease'], $schema['sleeve_buttons'],
            $schema['collar_height'], $schema['back_vent'],
        );

        $schema['buttons']['default'] = 5;
        $schema['length']['default'] = 22;
        $schema['lapel_width']['default'] = 6;
        $schema['button_stand']['default'] = 1.75;

        return $schema;
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $grow = $this->fitGrow($params, ['fitted' => 0.5, 'regular' => 1.5, 'loose' => 3.0]);

        $length = (float) $this->param($params, 'length', 22);
        $stand = (float) $this->param($params, 'button_stand', 1.75);
        $point = (float) $this->param($params, 'hem_point', 4.5);
        $bottom = $g['side_waist_y'] + $length;

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'grow' => $grow,
            'bottom_tag' => 'hem',
            'waist_dart' => true,
            'armhole_drop' => -(float) $this->param($params, 'armhole_lift', 2.5),
            'shoulder_extra' => -(float) $this->param($params, 'armhole_narrow', 2.5),
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'bust_dart' => true,
            'code' => 'waistcoat-front',
            'name' => 'تنه جلوی جلیقه',
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'waistcoat-back',
            'name' => 'تنه پشت جلیقه',
            'layer' => 'lining',
            'girth_role' => 'shell',
            'meta' => ['notes' => ['پشت جلیقه از آستر بریده می‌شود؛ زیر کت دیده نمی‌شود و کت را هم لیز نمی‌کند.']],
        ]));

        // نوکِ لبهٔ جلو فقط روی خط مرکز جلو می‌نشیند، نه روی درز پهلو
        $front = $this->pointFrontHem($front, $point);
        $front = $this->markButtons(
            $front,
            $stand,
            $g['front_neck_depth'] + 2,
            min($bottom - 4, $g['side_waist_y'] + $length - 5),
            (int) $this->param($params, 'buttons', 5),
            'جای دکمه جلو',
        );

        $armhole = $this->armholeOf([$front, $back]);
        $pieces = [$front, $back];

        $pieces[] = $this->frontFacingPiece($g, $stand, $bottom, [
            'prefix' => 'waistcoat-',
            'width' => 8.0,
            'name' => 'سجاف جلوی جلیقه',
        ]);

        $pieces[] = $this->armholeBindingPiece($armhole, ['prefix' => 'waistcoat-']);

        $opening = (float) $this->param($params, 'pocket_opening', 10);
        $pieces = array_merge($pieces, $this->jettedPocketSet($opening, [
            'prefix' => 'waistcoat-',
            'key' => 'hip',
            'name' => 'جیب پایین',
            'flap' => false,
            'depth' => 13.0,
        ]));

        $pieces = array_merge($pieces, $this->jettedPocketSet($opening * 0.8, [
            'prefix' => 'waistcoat-',
            'key' => 'chest',
            'name' => 'جیب سینه',
            'flap' => false,
            'depth' => 10.0,
        ]));

        if ($this->flag($params, 'back_strap', true)) {
            $pieces[] = $this->backStrap($measurements);
        }

        if ($this->flag($params, 'lining', true)) {
            $pieces[] = $this->frontLining($g, $shared, $stand);
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'] ?? [], [
            'لبهٔ جلو از درز پهلو تا خط مرکز جلو '.$this->fa(round($point, 1)).' سانتی‌متر پایین می‌آید و نوک می‌سازد.',
            'حلقه '.$this->fa(round((float) $this->param($params, 'armhole_lift', 2.5), 1))
                .' سانتی‌متر بالاتر و '.$this->fa(round((float) $this->param($params, 'armhole_narrow', 2.5), 1))
                .' سانتی‌متر تنگ‌تر از حلقهٔ کت است تا از زیر کت بیرون نزند.',
            'دکمهٔ آخر جلیقه هرگز بسته نمی‌شود؛ همین است که نوکِ لبه را باز نگه می‌دارد.',
        ]);

        return $this->finishBlock($pieces, $g, $grow, ['shell']);
    }

    /**
     * نوک‌دار کردن لبهٔ پایین جلو.
     *
     * فقط رأسِ روی خط مرکز جلو پایین می‌آید؛ درز پهلو دست‌نخورده می‌ماند تا با درز
     * پهلوی پشت هم‌اندازه بماند.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function pointFrontHem(array $piece, float $extra): array
    {
        if ($extra < 0.1) {
            return $piece;
        }

        $outline = array_values($piece['outline']);
        $tags = Geometry::edgeTags($piece);
        $count = count($outline);
        $centre = null;
        $centreX = INF;

        for ($i = 0; $i < $count; $i++) {
            if (($tags[$i] ?? '') !== 'hem') {
                continue;
            }

            foreach ([$i, ($i + 1) % $count] as $index) {
                if ((float) $outline[$index]['x'] < $centreX) {
                    $centreX = (float) $outline[$index]['x'];
                    $centre = $index;
                }
            }
        }

        if ($centre === null) {
            return $piece;
        }

        $outline[$centre]['y'] = round(((float) $outline[$centre]['y']) + $extra, 2);

        if (isset($outline[$centre]['cy'])) {
            $outline[$centre]['cy'] = round(((float) $outline[$centre]['cy']) + ($extra * 0.5), 2);
        }

        $piece['outline'] = $outline;
        $piece['meta']['hem_point'] = round($extra, 2);

        return $piece;
    }

    /** تسمهٔ تنظیم پشت، با سگک. */
    protected function backStrap(array $m): array
    {
        $length = round(max(20.0, $this->m($m, 'waist', 74) * 0.42), 1);

        return $this->bandPiece('waistcoat-back-strap', 'تسمه تنظیم پشت', $length, 7.0, [
            'cut' => 4,
            'part' => 'belt',
            'fold_line' => true,
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notions' => [[
                    'type' => 'buckle',
                    'label' => 'سگک تسمه پشت',
                    'count' => 1,
                ]],
                'notes' => [
                    'چهار تکه: دو تکه برای هر نیمهٔ تسمه (رو و آستر).',
                    'یک سرِ تسمه سگک می‌گیرد و سرِ دیگر از آن رد می‌شود؛ روی درز پهلو، هم‌تراز خط کمر دوخته می‌شود.',
                ],
            ],
        ]);
    }

    /**
     * آستر جلو تا خط سجاف.
     *
     * @param  array<string, float>  $g
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    protected function frontLining(array $g, array $shared, float $stand): array
    {
        return $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'extension' => $stand,
            'on_fold' => false,
            'cut' => 2,
            'mirror' => true,
            'layer' => 'lining',
            'part' => 'lining',
            'waist_dart' => false,
            'code' => 'waistcoat-lining-front',
            'name' => 'آستر جلو',
            'meta' => ['notes' => ['تا خط سجاف بریده می‌شود؛ لبهٔ داخلی‌اش به سجاف دوخته می‌شود.']],
        ]));
    }
}
