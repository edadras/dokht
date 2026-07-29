<?php

namespace App\Services\Pattern\Generators;

/**
 * سارافون (پیراهن پیشبندی).
 *
 * پیراهنی که روی لباس دیگر پوشیده می‌شود؛ همین یک جمله تمام تفاوت‌های الگویش را
 * توضیح می‌دهد:
 *
 *   آزادی   باید جای یک پیراهن یا بلوز زیرش باشد. آزادی این مدل عمداً از بقیهٔ
 *           خانواده بیشتر است و اگر کم شود، سارافون روی آستین بلوز می‌چسبد.
 *   حلقه    حلقهٔ آستین باید گودتر و بازتر باشد تا آستین لباسِ زیر از آن رد شود.
 *   بند     سرشانه باریک می‌شود و به بندِ پهن تبدیل می‌گردد؛ جلو یک پیشبند
 *           می‌ماند که از دو طرف با بند به پشت می‌رود.
 *
 * یقه و حلقه هیچ‌کدام درز نیستند، لبهٔ تمام‌شده‌اند؛ پس با سجاف یا نوار اریب تمام
 * می‌شوند و در الگو برایشان قطعه هست.
 */
class DressPinaforeGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_pinafore';
    }

    public function label(): string
    {
        return 'سارافون (پیراهن پیشبندی)';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge($this->skirtLengthParam(58, 30, 100), [
                'strap_width' => [
                    'label' => 'پهنای بند سرشانه', 'min' => 2.5, 'max' => 9, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                ],
                'bib_drop' => [
                    'label' => 'گودی خط بالای پیشبند', 'min' => 2, 'max' => 16, 'step' => 1,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
                'armhole_open' => [
                    'label' => 'گودی بیشتر حلقه برای آستین لباس زیر', 'min' => 0, 'max' => 8, 'step' => 0.5,
                    'default' => 3.5, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن دم در هر پهلو', 'min' => 2, 'max' => 25, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                ],
                'pocket' => [
                    'label' => 'جیب رودوزی روی دامن', 'type' => 'toggle', 'default' => true,
                ],
            ]),
            ['fit' => 'loose', 'back_closure' => 'buttons', 'lining' => 'none'],
            ['neck_width_extra' => 4, 'front_neck_depth_extra' => 5, 'back_neck_depth' => 7, 'armhole_depth_extra' => 4, 'waist_dart_share' => 0.5],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // جا برای لباس زیرش: آزادی این مدل عمداً از بقیهٔ خانواده بیشتر است
        $ease = $this->dressEase($ease, $params, ['bust' => 9.0, 'waist' => 9.0, 'hip' => 7.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 5);
        $open = (float) $this->param($params, 'armhole_open', 3.5);
        $seam = (string) $this->param($params, 'back_closure', 'buttons') !== 'none';

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'pinafore',
            'panel' => [
                // سرشانه به بندِ پهن تبدیل می‌شود و حلقه بازتر و گودتر می‌گردد
                'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
                'across_extra' => -min(5.0, $open),
                'armhole_drop' => $open,
            ],
            'bust_dart' => false,
            'waist_dart' => true,
            'back_seam' => $seam,
            'neck_drop' => (float) $this->param($params, 'bib_drop', 7),
            'back_drop' => (float) $this->param($params, 'bib_drop', 7) * 0.5,
            'front_name' => 'پیشبند جلو',
            'back_name' => $seam ? 'پشت سارافون (بستِ مرکزی)' : 'پشت سارافون',
        ]);

        $skirt = $this->attachedSkirt($g, [
            'bodice_waist' => $waist,
            'prefix' => 'pinafore',
            'type' => 'a_line',
            'length' => (float) $this->param($params, 'skirt_length', 58),
            'flare' => (float) $this->param($params, 'hem_flare', 9),
            'back_seam' => $seam,
            'front_name' => 'دامن سارافون جلو',
            'back_name' => 'دامن سارافون پشت',
        ]);

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);
        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params);

        // یقه و حلقه درز نیستند؛ لبهٔ تمام‌شده‌اند و قطعهٔ خودشان را می‌خواهند
        $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'pinafore-']);
        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'pinafore-', 'width' => 6]);

        if ($this->flag($params, 'pocket', true)) {
            $pieces[] = $this->patchPocketPiece(15, 16, ['prefix' => 'pinafore-', 'name' => 'جیب رودوزی دامن']);
        }

        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'این پیراهن روی لباس دیگر پوشیده می‌شود؛ آزادی سینه و کمرش عمداً بیشتر از بقیهٔ پیراهن‌هاست و حلقه‌اش '
                .$this->fa(round($open, 1)).' سانتی‌متر گودتر است تا آستین بلوزِ زیر از آن رد شود.',
            'بند سرشانه '.$this->fa(round($strap, 1)).' سانتی‌متر است؛ باریک‌تر از دو و نیم سانتی‌متر روی شانه می‌پیچد.',
            'یقه و حلقه درز نیستند و باید با نوار اریب یا سجاف تمام شوند؛ بدون آن‌ها لبهٔ خام روی لباسِ زیر ساییده می‌شود.',
            'ساسون سینه ندارد و نمی‌خواهد: سارافون روی لباس دیگر می‌نشیند و باید صاف بیفتد، نه قالبِ سینه.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
