<?php

namespace App\Services\Pattern\Generators;

/**
 * بالاتنه پایه (بلوک جذب).
 *
 * مادر همه مدل‌های بالاتنه: جلو و پشت با ساسون سینه و ساسون کمر، یقه، سرشانه و
 * حلقه آستین. نقطه کمرِ درز پهلو در جلو و پشت یکی است و افت جلو روی لبه کمر
 * دیده می‌شود، پس درز پهلوی جلو و پشت دقیقاً هم‌اندازه درمی‌آید و بدون
 * راست‌سازی به هم دوخته می‌شود.
 */
class BodiceBlockGenerator extends BodiceBaseGenerator
{
    public static function key(): string
    {
        return 'bodice_block';
    }

    public function label(): string
    {
        return 'بالاتنه پایه';
    }

    public function paramsSchema(): array
    {
        return array_merge(
            $this->baseSchema(),
            [
                'body_length' => [
                    'label' => 'بلندی از خط کمر', 'min' => 0, 'max' => 30, 'step' => 1, 'default' => 0,
                    'unit' => 'سانتی‌متر',
                    'hint' => 'صفر یعنی بلوک دقیقاً روی خط کمر تمام می‌شود.',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه روی پهلو', 'type' => 'toggle', 'default' => true,
                ],
            ],
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $g = $this->blockMetrics($measurements, $ease, $params);
        $length = (float) $this->param($params, 'body_length', 0);
        $shape = $length > 0.5 ? 'fitted' : 'waist';

        $shared = [
            'shape' => $shape,
            'length' => $length,
            'bottom_tag' => $length > 0.5 ? 'hem' : 'waist',
        ];

        return $this->finishBlock([
            $this->bodyPanel($g, array_merge($shared, [
                'side' => 'front',
                'bust_dart' => $this->flag($params, 'bust_dart', true),
                'code' => 'bodice-front',
                'name' => 'بالاتنه جلو',
            ])),
            $this->bodyPanel($g, array_merge($shared, [
                'side' => 'back',
                'code' => 'bodice-back',
                'name' => 'بالاتنه پشت',
            ])),
        ], $g);
    }
}
