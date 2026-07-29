<?php

namespace App\Services\Pattern\Generators;

/**
 * دامن توتو (چند لایه تور چین‌دار).
 *
 * فرقش با دامن طبقه‌ای بنیادی است و همیشه هم اشتباه می‌شود: در دامن طبقه‌ای
 * هر طبقه به طبقهٔ بالای خودش دوخته می‌شود و طبقه‌ها *پشت سر هم* پایین می‌روند؛
 * در توتو همهٔ لایه‌ها از یک جا — خط کمر — آویزان می‌شوند و *روی هم* می‌افتند.
 *
 * پس در توتو:
 *   - همهٔ لایه‌ها یک بالا دارند و طول‌هایشان فرق می‌کند (پله‌ای یا یک‌اندازه).
 *   - پُری هر لایه می‌تواند فرق کند؛ لایه‌های زیرین پُرتر، حجم می‌سازند.
 *   - هیچ لایه‌ای به لایهٔ دیگر دوخته نمی‌شود، پس اگر یکی کوتاه یا بلند بریده
 *     شود بقیه خراب نمی‌شوند.
 *
 * تور آستر نمی‌خواهد ولی زیرش لازم است؛ یک زیردامن ساتن اختیاری هست که هم
 * می‌پوشاند و هم نمی‌گذارد تور روی پوست بخورد.
 */
class SkirtTutuGenerator extends SkirtBaseGenerator
{
    public static function key(): string
    {
        return 'skirt_tutu';
    }

    public function label(): string
    {
        return 'دامن توتو (چند لایه تور)';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->lengthParam(55, 20, 120),
            [
                'layers' => [
                    'label' => 'تعداد لایه تور', 'min' => 2, 'max' => 8, 'step' => 1, 'default' => 4,
                ],
                'fullness' => [
                    'label' => 'نسبت پُری هر لایه', 'min' => 1.5, 'max' => 6, 'step' => 0.25, 'default' => 3,
                    'hint' => 'سه برابر یعنی توتوی نرم؛ پنج برابر یعنی توتوی ایستاده.',
                ],
                'layer_step' => [
                    'label' => 'اختلاف بلندی هر لایه با لایهٔ زیرش', 'min' => 0, 'max' => 12, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی همهٔ لایه‌ها هم‌قد.',
                ],
                'fuller_below' => [
                    'label' => 'لایه‌های زیرین پُرتر باشند', 'type' => 'toggle', 'default' => true,
                ],
                'underskirt' => [
                    'label' => 'زیردامن ساتن', 'type' => 'toggle', 'default' => true,
                ],
                'elastic_width' => [
                    'label' => 'پهنای کش کمر', 'min' => 1.5, 'max' => 6, 'step' => 0.5,
                    'default' => 3, 'unit' => 'سانتی‌متر',
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $mx = $this->skirtMetrics($measurements, $ease, $params, ['dart_share' => 0]);
        $length = (float) $this->param($params, 'length', 55);
        $layers = max(2, min(8, (int) $this->param($params, 'layers', 4)));
        $fullness = max(1.5, (float) $this->param($params, 'fullness', 3));
        $step = max(0.0, (float) $this->param($params, 'layer_step', 3));
        $fullerBelow = $this->flag($params, 'fuller_below', true);
        $elastic = (float) $this->param($params, 'elastic_width', 3);

        $waist = $mx['waist_target'];
        $pieces = [];

        for ($i = 0; $i < $layers; $i++) {
            // لایهٔ ۱ بلندترین (رویی‌ترین) است و هر لایهٔ بعدی کوتاه‌تر
            $layerLength = max(8.0, $length - ($step * $i));
            $layerFullness = $fullerBelow ? $fullness * (1 + ($i * 0.12)) : $fullness;
            $width = $waist * $layerFullness;

            $pieces[] = $this->rectPanel([
                'side' => 'front',
                'part' => 'skirt_layer',
                'code' => 'tutu-layer-'.($i + 1),
                'name' => 'لایه تور '.$this->fa($i + 1),
                'width' => $width / 2,
                'length' => $layerLength,
                'cut_quantity' => 2,
                'on_fold' => false,
                'top_edge' => 'waist',
                'fullness' => [
                    $this->fullness('gather', 0, $width / 2, $waist / 2, [
                        'label' => 'چین لایه '.$this->fa($i + 1),
                    ]),
                ],
                'notes' => [
                    'لایه '.$this->fa($i + 1).': پارچه '.$this->fa(round($width))
                        .' سانتی‌متر با چین به '.$this->fa(round($waist))
                        .' سانتی‌متر می‌رسد ('.$this->fa(round($layerFullness, 2)).' برابر).',
                    'لبهٔ دم تور دوخته نمی‌شود؛ تور ریش نمی‌شود.',
                ],
            ]);
        }

        if ($this->flag($params, 'underskirt', true)) {
            $pieces[] = $this->rectPanel([
                'side' => 'front',
                'part' => 'lining',
                'code' => 'tutu-underskirt',
                'name' => 'زیردامن ساتن',
                'width' => max($waist * 1.2, $mx['hip_target'] + 8) / 2,
                'length' => max(10.0, $length - $step - 2),
                'cut_quantity' => 2,
                'on_fold' => false,
                'top_edge' => 'waist',
                'fullness' => [
                    $this->fullness('gather', 0, max($waist * 1.2, $mx['hip_target'] + 8) / 2, $waist / 2, [
                        'label' => 'چین زیردامن',
                    ]),
                ],
                'notes' => ['دو سانتی‌متر کوتاه‌تر از کوتاه‌ترین لایهٔ تور، تا از زیرش بیرون نزند.'],
            ]);
        }

        $pieces[] = $this->bandPiece($waist, [
            'code' => 'tutu-casing',
            'name' => 'نوار جای کش کمر',
            'height' => $elastic + 1.5,
            'overlap' => 1.5,
            'interfacing' => false,
            'notes' => ['همهٔ لایه‌ها روی همین نوار چین داده می‌شوند و کش از داخلش رد می‌شود.'],
        ]);

        $pieces[0]['meta']['notions'] = [[
            'type' => 'elastic',
            'label' => 'کش کمر '.$this->fa($elastic).' سانتی‌متری',
            'length' => round($waist * 0.9, 1),
        ]];

        $pieces[0]['meta']['notes'][] = 'همهٔ لایه‌ها از خط کمر آویزان می‌شوند و روی هم می‌افتند؛ '
            .'هیچ لایه‌ای به لایهٔ دیگر دوخته نمی‌شود.';

        return $this->finishSkirt($pieces, ['zip' => 'none']);
    }
}
