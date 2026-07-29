<?php

namespace App\Services\Pattern\Generators;

/**
 * پیراهن سلیپ (بندی حریر).
 *
 * تمام این پیراهن روی اریب بریده می‌شود و همان یک جمله همهٔ تفاوت‌هایش را می‌سازد:
 *
 *   - اریب خودش کشسان است، پس این پیراهن ساسون ندارد و نمی‌خواهد؛ فرم را خودِ
 *     پارچه می‌گیرد و کمرگیری روی درز پهلو انجام می‌شود.
 *   - اریب پارچهٔ بیشتری می‌خورد، چون قطعه‌ها روی زاویهٔ ۴۵ درجه چیده می‌شوند.
 *   - اریب با وزن خودش دراز می‌شود. لباس باید بیست‌وچهار ساعت آویزان بماند و بعد
 *     دمش صاف شود، وگرنه چند روز بعد کج می‌شود.
 *
 * بندش باریک و قابل تنظیم است و بست ندارد: پیراهن اریب از سر پوشیده می‌شود و
 * زیپ روی اریب موج می‌اندازد.
 *
 * تفاوتش با لباس شبِ اسلیپ همین دو چیز است — این یکی کوتاه‌تر است و نه فنجان
 * سینه دارد نه آستر کامل؛ پیراهنی است که هر روز پوشیده و شسته می‌شود.
 */
class DressSlipGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_slip';
    }

    public function label(): string
    {
        return 'پیراهن سلیپ (بندی حریر)';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge($this->skirtLengthParam(66, 35, 105), [
                'strap_width' => [
                    'label' => 'پهنای بند', 'min' => 0.8, 'max' => 4, 'step' => 0.2,
                    'default' => 1.6, 'unit' => 'سانتی‌متر',
                ],
                'neck_drop' => [
                    'label' => 'گودی یقهٔ جلو', 'min' => 2, 'max' => 22, 'step' => 1,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط یقهٔ پایه به پایین؛ برای یقهٔ دلبری عدد بزرگ‌تر بگذارید.',
                ],
                'back_drop' => [
                    'label' => 'گودی پشت', 'min' => 2, 'max' => 26, 'step' => 1,
                    'default' => 12, 'unit' => 'سانتی‌متر',
                ],
                'side_slit' => [
                    'label' => 'بلندی چاک پهلو', 'min' => 0, 'max' => 40, 'step' => 1,
                    'default' => 18, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن دم در هر پهلو', 'min' => 0, 'max' => 20, 'step' => 1,
                    'default' => 6, 'unit' => 'سانتی‌متر',
                ],
            ]),
            ['fit' => 'regular', 'back_closure' => 'none', 'lining' => 'none'],
            ['armhole_depth_extra' => 2, 'neck_width_extra' => 1],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        // اریب کشسان است، پس آزادی کمتر از پیراهن بافته لازم دارد
        $ease = $this->dressEase($ease, $params, ['bust' => 4.0, 'waist' => 5.0, 'hip' => 4.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 1.6);
        $length = (float) $this->param($params, 'skirt_length', 66);

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'slip',
            // بند باریک به‌جای سرشانه: نوک سرشانه تا پهنای یقه به‌اضافهٔ بند تو می‌آید
            'panel' => [
                'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
                'across_extra' => -min(4.0, $strap * 0.6),
                'armhole_drop' => 2.0,
            ],
            // روی اریب ساسون معنا ندارد؛ کمرگیری همه روی درز پهلوست
            'waist_dart' => false,
            'bust_dart' => false,
            'back_seam' => false,
            'neck_drop' => (float) $this->param($params, 'neck_drop', 9),
            'back_drop' => (float) $this->param($params, 'back_drop', 12),
            'front_name' => 'بالاتنه جلو (اریب)',
            'back_name' => 'بالاتنه پشت (اریب)',
        ]);

        $skirt = $this->attachedSkirt($g, [
            'bodice_waist' => $waist,
            'prefix' => 'slip',
            'type' => 'a_line',
            'length' => $length,
            'flare' => (float) $this->param($params, 'hem_flare', 6),
            'dart' => 0.0,
            'back_seam' => false,
            'front_name' => 'دامن جلو (اریب)',
            'back_name' => 'دامن پشت (اریب)',
        ]);

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $slit = (float) $this->param($params, 'side_slit', 18);

        if ($slit > 1) {
            $skirt[0] = $this->markSideVent($skirt[0], $slit, 'چاک پهلو');
            $skirt[1] = $this->markSideVent($skirt[1], $slit, 'چاک پهلو');
        }

        $pieces = $this->cutOnBias(array_merge($bodice, $skirt));

        // بلندی بند از خودِ پنل خوانده می‌شود: از خط بالای بالاتنه تا سرشانه، جلو
        // و پشت، به‌اضافهٔ جای تنظیم
        $strapLength = round(($g['neck_width'] + $g['shoulder_drop']) * 2 + 26, 1);

        $pieces[] = $this->dressStrapPiece('slip-strap', 'بند قابل تنظیم', $strapLength, $strap, [
            'cut' => 2,
            'meta' => ['notes' => ['با سگک تنظیم می‌شود؛ عمداً بلندتر بریده شده و اندازهٔ نهایی در پرو بسته می‌شود.']],
        ]);

        $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'slip-']);

        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params);
        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'همهٔ قطعه‌ها روی اریب بریده می‌شوند؛ فرم را خودِ پارچه می‌گیرد و این پیراهن ساسون نمی‌خواهد.',
            'اریب پارچهٔ بیشتری می‌خورد: قطعه‌ها روی زاویهٔ ۴۵ درجه چیده می‌شوند و بینشان پرت می‌ماند.',
            'پس از دوخت، لباس بیست‌وچهار ساعت آویزان بماند و بعد دمش صاف شود؛ اریب با وزن خودش دراز می‌شود.',
            'درز کمر روی اریب باید با نوار راستهٔ نازک محکم شود، وگرنه دو لبه هنگام دوخت کشیده می‌شوند و کمر موج می‌افتد.',
            'لبهٔ یقه و حلقه با نوار اریب تمام می‌شود، نه با سجاف؛ سجاف روی اریب سفت می‌ایستد.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }
}
