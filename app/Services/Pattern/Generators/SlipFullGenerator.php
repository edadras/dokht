<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * کمبینزون (زیرپوش لباس).
 *
 * زیرپوشی که از خط بالای سینه تا بالای زانو می‌رود و کارش این است که لباس روی
 * تن سُر بخورد و به بدن نچسبد.
 *
 * تمام تصمیم‌های این الگو از یک چیز می‌آید: **روی اریب بریده می‌شود.**
 *
 *   - اریب یعنی خودِ پارچه کش می‌آید، پس کمبینزون بدون هیچ کش و زیپی از تن رد
 *     می‌شود و روی بدن می‌نشیند. برای همین ساسون سینه پیش‌فرض خاموش است: اریب
 *     همان کاری را می‌کند که ساسون می‌کرد، و ساسون روی اریب چروک می‌اندازد.
 *   - اریب یعنی لبه‌ها با نوار اریب تمام می‌شوند نه با کش. کش روی پارچهٔ بافته
 *     چین می‌اندازد و همان سُر خوردنی را که می‌خواستیم از بین می‌برد.
 *   - اریب یعنی دم لباس پس از دوخت آویزان می‌ماند تا پارچه بنشیند و بعد صاف
 *     می‌شود؛ وگرنه دم لباس کج در می‌آید.
 *
 * بند باریک است و جدا بریده می‌شود: بند سرخود روی اریب زیر وزن لباس دراز می‌شود.
 */
class SlipFullGenerator extends UnderwearBaseGenerator
{
    public static function key(): string
    {
        return 'slip_full';
    }

    public function label(): string
    {
        return 'کمبینزون (زیرپوش لباس)';
    }

    public function paramsSchema(): array
    {
        return $this->underwearSchema([
            'length' => [
                'label' => 'بلندی از خط کمر', 'min' => 10, 'max' => 70, 'step' => 1,
                'default' => 32, 'unit' => 'سانتی‌متر',
                'hint' => 'سی و دو سانتی‌متر تقریباً بالای زانو می‌ایستد.',
            ],
            'top_drop' => [
                'label' => 'گودی خط بالای جلو از زیر بغل', 'min' => 2, 'max' => 20, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
            ],
            'back_drop' => [
                'label' => 'گودی خط بالای پشت', 'min' => 2, 'max' => 24, 'step' => 0.5,
                'default' => 11, 'unit' => 'سانتی‌متر',
            ],
            'top_shape' => [
                'label' => 'شکل خط بالای جلو', 'type' => 'select', 'default' => 'straight',
                'options' => ['straight' => 'صاف', 'sweetheart' => 'قلبی', 'scoop' => 'گرد'],
            ],
            'hem_flare' => [
                'label' => 'باز شدن دم لباس', 'min' => 0, 'max' => 20, 'step' => 1,
                'default' => 6, 'unit' => 'سانتی‌متر',
                'hint' => 'کمبینزون باید جای راه رفتن بدهد؛ بدون آن دم لباس روی زانو می‌کشد.',
            ],
            'strap_width' => [
                'label' => 'پهنای بند', 'min' => 0.6, 'max' => 3, 'step' => 0.1,
                'default' => 1.2, 'unit' => 'سانتی‌متر',
            ],
            'bust_dart' => [
                'label' => 'ساسون سینه', 'type' => 'toggle', 'default' => false,
                'hint' => 'روی اریب لازم نیست و معمولاً چروک می‌اندازد؛ فقط برای سینهٔ بسیار درشت روشنش کنید.',
            ],
        ], stretch: 0.96);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->negativeEaseFor($ease, $measurements, $params);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $length = (float) $this->param($params, 'length', 32);
        $frontDrop = (float) $this->param($params, 'top_drop', 8);
        $backDrop = (float) $this->param($params, 'back_drop', 11);

        $shared = [
            'shape' => 'fitted',
            'length' => $length,
            'hem_flare' => (float) $this->param($params, 'hem_flare', 6),
            'bottom_tag' => 'hem',
            'waist_dart' => true,
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'slip-front',
            'name' => 'کمبینزون — جلو',
            'bust_dart' => $this->flag($params, 'bust_dart', false),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'slip-back',
            'name' => 'کمبینزون — پشت',
        ]));

        // جای برخورد خط بالا با درز پهلو روی جلو و پشت یکی است، وگرنه دو درز
        // پهلو هم‌اندازه نمی‌شوند و لباس دوخته نمی‌شود.
        $frontTop = (float) ($front['meta']['bust_y'] ?? 18) - $frontDrop;
        $backTop = (float) ($back['meta']['bust_y'] ?? 18) - $backDrop;
        $sideTop = min($frontTop, $backTop) + 1.5;

        $front = $this->cutTop($front, [
            'center' => $frontTop,
            'side' => $sideTop,
            'shape' => (string) $this->param($params, 'top_shape', 'straight'),
            'apex' => 0.6,
        ]);

        $back = $this->cutTop($back, [
            'center' => $backTop,
            'side' => $sideTop,
            'shape' => 'straight',
        ]);

        $front = $this->onBias($front);
        $back = $this->onBias($back);

        // خط بالای بریده‌شده و خط مرکز هر دو برچسب default دارند؛ نوار اریب فقط
        // روی آن یکی می‌رود که تای پارچه نیست.
        $topLine = Geometry::edgesLength($front['outline'], $this->openEdges($front, 'default'))
            + Geometry::edgesLength($back['outline'], $this->openEdges($back, 'default'));

        $strapLength = $this->strapLength($g, $frontDrop + 4, $backDrop + 4, extra: 8);

        $strap = $this->strapPiece($strapLength, (float) $this->param($params, 'strap_width', 1.2), [
            'code' => 'slip-strap',
            'name' => 'بند کمبینزون',
            'cut' => 2,
            'meta' => [
                'adjustable' => true,
                'strap_path' => round($strapLength, 1),
            ],
        ]);

        $strap['meta']['notes'] = array_merge($strap['meta']['notes'] ?? [], [
            'بند '.$this->fa($strapLength).' سانتی‌متر بریده شده و عمداً بلندتر است؛ اندازهٔ نهایی در پرو بسته می‌شود.',
            'بند را در راستای پارچه ببرید نه روی اریب؛ بندِ اریب زیر وزن لباس دراز می‌شود.',
        ]);

        $binding = $this->bandPiece(
            'slip-binding',
            'نوار اریب خط بالا',
            max(30.0, $topLine + 8.0),
            2.4,
            [
                'cut' => 2,
                'fold_line' => true,
                'part' => 'binding',
                'meta' => [
                    'girth_role' => 'trim',
                    'bias' => true,
                    'notes' => [
                        'نوار روی اریب بریده می‌شود؛ فقط اریب روی خط منحنی می‌خوابد.',
                        'خط بالا و لبهٔ پشت با همین نوار تمام می‌شوند، نه با کش.',
                    ],
                ],
            ],
        );

        return $this->finishUnderwear([$front, $back, $strap, $binding], $this->underwearNotes($params, [
            'هر دو پنل روی اریب بریده می‌شوند؛ راستای پارچه روی الگو عمداً مورب کشیده شده.',
            'کمبینزون کش ندارد: لبه‌ها با نوار اریب تمام می‌شوند و خودِ اریب جای کش را می‌گیرد.',
            'پس از دوختن درزها، لباس را یک شب آویزان بگذارید و بعد دمش را صاف کنید؛ اریب می‌نشیند.',
        ]));
    }
}
