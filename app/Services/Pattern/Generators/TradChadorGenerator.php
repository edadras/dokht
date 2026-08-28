<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * چادر.
 *
 * چادر برش ندارد — یک نیم‌دایرهٔ بزرگ است که از بالای سر می‌افتد و با دست یا
 * بند نگه داشته می‌شود. تنها سه عددش را باید درست گرفت:
 *
 *   شعاع        از بالای سر تا لبه؛ همان است که بلندی را می‌سازد.
 *   قوسِ سر     لبهٔ بالا صاف نیست: یک قوسِ کم‌عمق می‌خورد تا روی سر بنشیند و
 *               جلوی صورت باز بماند. بی این قوس، چادر از پشتِ سر می‌سُرد.
 *   نوارِ لبه   دور تا دورِ لبهٔ منحنی نوار می‌خورد؛ روی منحنی، نوارِ راست
 *               چروک می‌کند، پس اریب بریده می‌شود.
 *
 * دو گونه دارد: چادرِ ملی که با کش یا بندِ زیرِ چانه بسته می‌شود و دست را آزاد
 * می‌گذارد، و چادرِ عربی که جلوباز است و با دست نگه داشته می‌شود.
 */
class TradChadorGenerator extends TraditionalBaseGenerator
{
    public static function key(): string
    {
        return 'trad_chador';
    }

    public function label(): string
    {
        return 'چادر';
    }

    public static function group(): string
    {
        return 'traditional';
    }

    public function paramsSchema(): array
    {
        return [
            'style' => [
                'label' => 'گونه', 'type' => 'select', 'default' => 'meli',
                'options' => [
                    'meli' => 'چادر ملی (بنددار، جلو بسته)',
                    'arabi' => 'چادر عربی (جلو باز)',
                    'namaz' => 'چادر نماز (کوتاه)',
                ],
            ],
            'radius' => [
                'label' => 'شعاع چادر از بالای سر', 'min' => 100, 'max' => 190, 'step' => 1,
                'default' => 155, 'unit' => 'سانتی‌متر',
                'hint' => 'قد کاربر منهای حدود ده سانتی‌متر؛ لبه نباید روی زمین بکشد.',
            ],
            'head_arc' => [
                'label' => 'گودی قوس سر', 'min' => 4, 'max' => 20, 'step' => 0.5,
                'default' => 11, 'unit' => 'سانتی‌متر',
                'hint' => 'بی این قوس، چادر از پشت سر می‌سُرد.',
            ],
            'face_width' => [
                'label' => 'پهنای باز جلوی صورت', 'min' => 18, 'max' => 44, 'step' => 1,
                'default' => 30, 'unit' => 'سانتی‌متر',
            ],
            'binding' => [
                'label' => 'پهنای نوار اریب لبه', 'min' => 1.5, 'max' => 5, 'step' => 0.5,
                'default' => 2.5, 'unit' => 'سانتی‌متر',
            ],
        ];
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $style = (string) $this->param($params, 'style', 'meli');
        $radius = max(80.0, (float) $this->param($params, 'radius', 155));

        if ($style === 'namaz') {
            $radius = min($radius, 120.0);
        }

        $arc = max(2.0, (float) $this->param($params, 'head_arc', 11));
        $face = max(14.0, (float) $this->param($params, 'face_width', 30));
        $band = max(1.0, (float) $this->param($params, 'binding', 2.5));

        /*
         * ربعِ دایره روی دو تا: خطِ تا همان مرکزِ پشت است و مرکزِ دایره بالای سر.
         *
         * مسیر از روی خطِ تا شروع می‌شود، با قوسِ کمِ سر به لبهٔ جلو می‌رسد، از
         * آن‌جا تا لبهٔ بیرونی می‌رود، ربعِ دایرهٔ لبهٔ پایین را می‌پیماید و از خطِ
         * تا برمی‌گردد. نخستین نسخه از *مرکزِ* دایره شروع می‌شد و مسیر خودش را
         * قطع می‌کرد.
         */
        $outline = [Geometry::point(0, $arc)];
        $edges = ['neck'];

        $outline[] = Geometry::curve($face / 2, 0, $face / 4, $arc * 0.2);
        $edges[] = 'default';

        $outline[] = Geometry::point($radius, 0);
        $edges[] = 'hem';

        foreach (array_slice($this->arcPoints(0, 0, $radius, $radius, 0, M_PI / 2, 24), 1) as $point) {
            $outline[] = Geometry::point($point['x'], $point['y']);
            $edges[] = 'hem';
        }

        $edges[count($edges) - 1] = 'default'; // لبهٔ آخر همان خطِ تاست

        $piece = $this->piece([
            'code' => 'chador-body',
            'name' => 'چادر',
            'cut_quantity' => 1,
            'on_fold' => true,
            'mirror' => false,
            'outline' => $outline,
            'grainline' => $this->grainline($radius * 0.3, $arc + 6, $radius * 0.7),
            'markers' => [
                $this->marker('head', 'خط قوس سر', 0, $arc, $face / 2, 0),
            ],
            'meta' => [
                'part' => 'veil',
                'edges' => $edges,
                'fold_edges' => [count($outline) - 1],
                'girth' => [],
                'girth_factor' => 0,
                'girth_role' => 'cover',
                'notes' => [
                    'روی دو تا بریده می‌شود: تای مرکزِ پشت و تای پهنا.',
                    'قوسِ سر جلوی سُر خوردن چادر از پشتِ سر را می‌گیرد.',
                ],
            ],
        ]);

        $pieces = [$piece];

        $pieces[] = $this->bandPiece(
            'chador-binding',
            'نوار اریب لبه',
            ($radius * M_PI) + 20,
            $band * 2 + 1,
            [
                'cut' => 1, 'part' => 'binding',
                'meta' => [
                    'bias' => true,
                    'girth_role' => 'trim',
                    'notes' => ['روی اریب بریده می‌شود؛ نوارِ راست روی منحنی چروک می‌کند.'],
                ],
            ],
        );

        if ($style === 'meli') {
            $pieces[] = $this->bandPiece('chador-tie', 'بند زیر چانه', 70, 4, [
                'cut' => 2, 'part' => 'strap',
                'meta' => [
                    'girth_role' => 'trim',
                    'notes' => ['دو بند روی دو سوی قوسِ سر دوخته می‌شود و زیر چانه گره می‌خورد.'],
                ],
            ]);
        }

        $pieces[0]['meta']['notes'] = array_merge($pieces[0]['meta']['notes'], $this->modestNotes([
            match ($style) {
                'arabi' => 'چادر عربی جلو باز است و با دست نگه داشته می‌شود.',
                'namaz' => 'چادر نماز کوتاه‌تر است و تا زیر زانو می‌آید.',
                default => 'چادر ملی با بندِ زیر چانه بسته می‌شود و دست را آزاد می‌گذارد.',
            },
        ]));

        return $this->finish($pieces);
    }
}
