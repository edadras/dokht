<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * پیراهن راپ.
 *
 * دو جلو که روی هم می‌افتند و با بند کمر بسته می‌شوند. هیچ زیپ و دکمه‌ای ندارد و
 * همین باعث شده تنها پیراهن این خانواده باشد که روی چند اندازه بدن جواب می‌دهد.
 *
 * سه چیز این مدل را می‌سازد و هر سه در الگو صریح‌اند:
 *
 *   هم‌پوشانی  هر جلو باید تا آن‌طرف خط مرکز برود. کمتر از ده سانتی‌متر یعنی
 *              لباسی که با هر قدم باز می‌شود؛ همان ایراد همیشگی پیراهن راپ.
 *   بند کمر    دو بند: یکی از پهلوی راست بیرون می‌آید و دور کمر می‌چرخد و دیگری
 *              روی همان‌جا گره می‌خورد. برای بند اول باید *سوراخی روی درز پهلو*
 *              باز شود؛ بدون آن پیراهن راپ بسته نمی‌شود.
 *   چین راپ    فرم سینه به‌جای ساسون، روی درز کمر چین داده می‌شود. مقدارش دقیقاً
 *              همان دهانهٔ ساسون است، پس دور کمر تغییر نمی‌کند — فقط راه بستنش.
 */
class DressWrapGenerator extends DressBaseGenerator
{
    public static function key(): string
    {
        return 'dress_wrap';
    }

    public function label(): string
    {
        return 'پیراهن راپ';
    }

    public function paramsSchema(): array
    {
        return $this->dressSchema(
            array_merge($this->skirtLengthParam(64, 35, 110), [
                'overlap' => [
                    'label' => 'هم‌پوشانی جلو', 'min' => 8, 'max' => 30, 'step' => 1,
                    'default' => 15, 'unit' => 'سانتی‌متر',
                    'hint' => 'هر جلو این‌قدر از خط مرکز جلو رد می‌شود؛ کمتر از ده سانتی‌متر با هر قدم باز می‌شود.',
                ],
                'neck_drop' => [
                    'label' => 'گودی یقهٔ هفت', 'min' => 6, 'max' => 26, 'step' => 1,
                    'default' => 14, 'unit' => 'سانتی‌متر',
                ],
                'belt_width' => [
                    'label' => 'پهنای بند کمر', 'min' => 2, 'max' => 8, 'step' => 0.5,
                    'default' => 4, 'unit' => 'سانتی‌متر',
                ],
                'hem_flare' => [
                    'label' => 'باز شدن دم در هر پهلو', 'min' => 2, 'max' => 25, 'step' => 1,
                    'default' => 10, 'unit' => 'سانتی‌متر',
                ],
            ]),
            // راپ بست ندارد؛ خودِ هم‌پوشانی و بند کمر بستِ لباس‌اند
            ['fit' => 'regular', 'back_closure' => 'none', 'lining' => 'none'],
            ['waist_dart_share' => 0.7, 'front_neck_depth_extra' => 0],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->dressEase($ease, $params, ['bust' => 6.0, 'waist' => 4.0, 'hip' => 5.0]);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $overlap = (float) $this->param($params, 'overlap', 15);
        $length = (float) $this->param($params, 'skirt_length', 64);

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => 'wrap',
            'extension' => $overlap,
            'bust_dart' => true,
            'waist_dart' => true,
            'back_seam' => false,
            'neck_drop' => (float) $this->param($params, 'neck_drop', 14),
            'front_name' => 'جلوی راپ (دو تکهٔ قرینه)',
            'back_name' => 'بالاتنه پشت',
        ]);

        // لبهٔ راپ: از نقطهٔ یقه روی خط مرکز، مورب پایین می‌آید و از زیر سینه به
        // بعد صاف تا کمر می‌رود. بدون این شیب، دو جلو مثل ردا روی هم می‌افتند نه
        // مثل راپ.
        $bodice[0] = $this->slantWrapEdge($bodice[0], (float) ($bodice[0]['meta']['bust_y'] ?? 20) + 3.0);

        // فرم سینه روی درز کمر چین می‌شود، نه ساسون
        $bodice[0] = $this->dartsToGathers($bodice[0], 'waist', 'چین درز راپ');
        $bodice[0] = $this->crossesCenter($bodice[0], $overlap);
        $waist = $this->edgeGirth($bodice, 'waist');

        $skirt = $this->attachedSkirt($g, [
            'bodice_waist' => $waist,
            'prefix' => 'wrap',
            'type' => 'a_line',
            'length' => $length,
            'flare' => (float) $this->param($params, 'hem_flare', 10),
            'extension' => $overlap,
            'back_seam' => false,
            'front_name' => 'دامن راپ جلو (دو تکهٔ قرینه)',
            'back_name' => 'دامن پشت',
        ]);

        $skirt[0] = $this->crossesCenter($skirt[0], $overlap);

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        $beltWidth = (float) $this->param($params, 'belt_width', 4);
        $bodyWaist = $this->m($measurements, 'waist', 74);

        // بند بلند از سوراخ درز پهلو بیرون می‌آید، دور کمر می‌چرخد و جلو گره
        // می‌خورد؛ بند کوتاه از همان پهلو مستقیم به گره می‌رسد.
        $pieces[] = $this->dressStrapPiece('wrap-belt-long', 'بند کمر بلند', ($bodyWaist * 0.75) + 45, $beltWidth, [
            'cut' => 1, 'part' => 'belt',
            'meta' => ['notes' => ['از سوراخ درز پهلوی راست بیرون می‌آید و از پشت دور کمر می‌چرخد.']],
        ]);

        $pieces[] = $this->dressStrapPiece('wrap-belt-short', 'بند کمر کوتاه', ($bodyWaist * 0.35) + 45, $beltWidth, [
            'cut' => 1, 'part' => 'belt',
            'meta' => ['notes' => ['روی جلوی زیرین دوخته می‌شود و مستقیم به گره می‌رسد.']],
        ]);

        $pieces[] = $this->bandPiece('wrap-side-opening', 'پاکتیِ سوراخ درز پهلو', max(8.0, $beltWidth + 4), 5, [
            'cut' => 2, 'part' => 'facing',
            'meta' => [
                'girth_role' => 'trim',
                'interfacing' => true,
                'notes' => ['روی درز پهلوی راست، هم‌تراز خط کمر، سوراخی به پهنای بند باز می‌شود و با این تکه تمیزدوزی می‌گردد.'],
            ],
        ]);

        $pieces = array_merge($pieces, $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => 'wrap-']));

        if ((string) $this->param($params, 'sleeve_style', 'set_in') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => 'wrap-']);
        }

        $pieces[] = $this->backNeckFacingPiece($g, ['prefix' => 'wrap-', 'width' => 6]);

        // لبهٔ راپ از یقه تا کمر باز است و باید با نوار اریب پوشیده شود
        $band = $this->armholeBindingPiece(
            round(Geometry::height($bodice[0]['outline']) + $g['neck_width'] + 10, 1),
            ['prefix' => 'wrap-edge-', 'name' => 'نوار لبهٔ راپ و یقه', 'height' => 3.0],
        );

        /*
         * این نوار «حلقهٔ افقی» نیست؛ مورب است.
         *
         * چیدنِ سه‌بعدی هر نواری را در یک ارتفاعِ ثابت دور بدن می‌پیچد، و این
         * یکی ۶۵ سانتی‌متر طول دارد — یعنی دورِ کاملِ سینه. نتیجه‌اش نواری بود
         * که در ارتفاع سرشانه دورِ تن می‌چرخید و مچاله می‌شد، در حالی که جایش
         * روی لبهٔ راپ است: از سرِ کمر (۱۵ سانتی‌متر آن‌سوی خط مرکز) مورب بالا
         * تا نقطهٔ یقه روی سرشانه.
         *
         * دو سرِ آن مسیر از خودِ درفت درمی‌آید: چارکِ سینه نیم‌دورِ جلو است، پس
         * هر سانتی‌متر پهنای الگو یک‌چهارمِ π بر چارکِ سینه زاویه می‌گیرد.
         */
        $turn = M_PI_2 / max(5.0, (float) $g['quarter_bust']);

        $band['meta']['drape_run'] = [
            'from' => ['level' => 'waist', 'u' => round(-$overlap * $turn, 4)],
            'to' => ['level' => 'shoulder', 'u' => round((float) $g['neck_width'] * $turn, 4)],
        ];

        $pieces[] = $band;

        [$pieces, $closureNotes] = $this->dressClosure($pieces, $g, $params);
        $pieces = $this->dressLining($pieces, $params);

        $notes = array_merge($waistNotes, $closureNotes, [
            'هر جلو '.$this->fa(round($overlap, 1)).' سانتی‌متر از خط مرکز جلو رد می‌شود؛ این اندازه هم روی بالاتنه و هم روی دامن یکسان است، وگرنه دو لبهٔ راپ روی هم نمی‌افتند.',
            'فرم سینه روی درز کمر چین داده می‌شود، نه ساسون؛ مقدارش همان دهانهٔ ساسون است و دور کمر عوض نمی‌شود.',
            'روی درز پهلوی راست، هم‌تراز خط کمر، سوراخی به پهنای بند باز می‌شود تا بند بلند از آن رد شود؛ بدون این سوراخ پیراهن راپ بسته نمی‌شود.',
            'هشدار: هم‌پوشانی کمتر از ده سانتی‌متر با هر قدم باز می‌شود. اگر مشتری نگران است، لبهٔ جلو را با یک دکمهٔ مخفی روی خط کمر هم بگیرید.',
        ]);

        return $this->finish($this->noted($pieces, $notes));
    }

    /**
     * اعلامِ اینکه این پنل از خط مرکز *رد* می‌شود، نه اینکه کنارش می‌ایستد.
     *
     * پنل با «اضافهٔ هم‌پوشانی» درفت شده و ۱۵ سانتی‌متر از خط مرکز جلو پهن‌تر
     * است، ولی خودِ الگو جایی نمی‌گوید این ۱۵ سانتی‌متر قرار است روی آن یکی جلو
     * بیفتد؛ از دیدِ هندسه فقط یک پنلِ چهل سانتی‌متری است. پس چیدنِ سه‌بعدی هر دو
     * جلو را مثل هر لباس دیگری نصف‌نصف می‌کرد — یکی از ۰ تا ۹۰ درجه و دیگری از
     * −۹۰ تا ۰ — و دو لبهٔ راپ به‌جای اینکه روی هم بیفتند، سرِ خط مرکز به هم
     * می‌رسیدند.
     *
     * نتیجه‌اش روی مانکن اندازه گرفته شد: یقهٔ راپ ۲۳٫۴ سانتی‌متر گود است، پس
     * بالای آن هر پنل تازه از x=۱۵ پارچه دارد — یعنی از ۳۴ درجه به بعد. میان
     * ۳۴− و ۳۴+ درجه هیچ پارچه‌ای نبود و سینه از یقه تا زیرِ سینه لخت می‌ماند:
     * لکه‌ای ۲۴۶۹ پیکسلی، ۱۳٪ کلِ پارچهٔ لباس.
     *
     * فقط راپ این را اعلام می‌کند. اضافهٔ دو سانتی‌متریِ جای دکمهٔ پیراهن و کت
     * هم‌پوشانی نیست: زیرِ خودش تا می‌خورد و لبه‌اش روی خط مرکز دوخته می‌شود.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function crossesCenter(array $piece, float $overlap): array
    {
        $piece['meta']['crosses_center'] = round(max(0.0, $overlap), 2);

        return $piece;
    }

    /**
     * شیب دادن به لبهٔ راپ.
     *
     * پنل جلو با «اضافهٔ هم‌پوشانی» درفت شده، پس لبهٔ سمت مرکزش عمودی است. لبهٔ
     * راپ واقعی از نقطهٔ یقه روی خط مرکز شروع می‌شود و مورب پایین می‌آید تا زیر
     * سینه، و از آن‌جا به بعد عمودی تا کمر. این متد همان کار را با پایین بردن
     * تنها رأس بالای همان لبه انجام می‌دهد؛ هیچ لبهٔ دیگری — درز پهلو، حلقه،
     * کمر — دست نمی‌خورد.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function slantWrapEdge(array $piece, float $topY): array
    {
        $outline = array_values($piece['outline'] ?? []);

        if (count($outline) < 5 || abs((float) ($outline[0]['x'] ?? 1)) > 0.01) {
            return $piece;
        }

        [, , , $maxY] = Geometry::bounds($outline);
        $outline[0]['y'] = round(max((float) $outline[0]['y'], min($topY, $maxY - 6)), 2);

        $piece['outline'] = $outline;
        $piece['meta']['wrap_edge'] = true;

        return $piece;
    }
}
