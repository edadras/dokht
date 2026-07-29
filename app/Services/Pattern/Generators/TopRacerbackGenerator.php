<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;

/**
 * تاپ راسربک (پشت‌کمانی).
 *
 * جلویش تانک است و پشتش فرق می‌کند: حلقهٔ پشت خیلی گودتر بریده می‌شود و دو بند
 * به‌جای اینکه روی سرشانه بمانند، به مرکز پشت نزدیک می‌شوند. سودش سازه‌ای است،
 * نه ظاهری: بند نزدیک مرکز از روی استخوان شانه کنار می‌رود و هنگام حرکت دست
 * نمی‌افتد؛ برای همین این مدل لباس ورزشی است.
 *
 * بهایش هم هست: کتف و تیغهٔ شانه باز می‌ماند و بند روی گردن فشار می‌آورد، پس
 * پهنای بند پشت نباید از دو سانتی‌متر کمتر شود.
 */
class TopRacerbackGenerator extends TopBaseGenerator
{
    public static function key(): string
    {
        return 'top_racerback';
    }

    public function label(): string
    {
        return 'تاپ راسربک (پشت‌کمانی)';
    }

    public function paramsSchema(): array
    {
        return $this->topSchema(array_merge(
            $this->strapParam(3.5, 'پهنای بند روی سرشانه'),
            [
                'racer_width' => [
                    'label' => 'پهنای بند در مرکز پشت', 'min' => 2, 'max' => 12, 'step' => 0.5,
                    'default' => 5, 'unit' => 'سانتی‌متر',
                    'hint' => 'کمتر از دو سانتی‌متر روی گردن فشار می‌آورد.',
                ],
                'racer_depth' => [
                    'label' => 'گودی حلقه پشت', 'min' => 3, 'max' => 20, 'step' => 0.5,
                    'default' => 9, 'unit' => 'سانتی‌متر',
                    'hint' => 'از خط سرشانه به پایین؛ هرچه بیشتر، پشت بازتر.',
                ],
                'neck_drop' => [
                    'label' => 'گودی یقه جلو', 'min' => 0, 'max' => 24, 'step' => 0.5,
                    'default' => 7, 'unit' => 'سانتی‌متر',
                ],
            ],
        ), length: 6);
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $ease = $this->knitEase($ease, 3.0);
        $g = $this->blockMetrics($measurements, $ease, $params);

        $strap = (float) $this->param($params, 'strap_width', 3.5);
        $racer = (float) $this->param($params, 'racer_width', 5);
        $depth = (float) $this->param($params, 'racer_depth', 9);

        $shared = [
            'shape' => 'straight',
            'length' => $this->bodyLength($params, $g, 6),
            'bottom_tag' => 'hem',
            'waist_dart' => false,
            'shoulder_extra' => ($g['neck_width'] + $strap) - $g['shoulder_half'],
            'armhole_drop' => 2.5,
            'across_extra' => -min(4.0, $strap * 0.5),
        ];

        $front = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'front',
            'code' => 'racer-front',
            'name' => 'راسربک جلو',
            'neck_depth_extra' => (float) $this->param($params, 'neck_drop', 7),
        ]));

        $back = $this->bodyPanel($g, array_merge($shared, [
            'side' => 'back',
            'code' => 'racer-back',
            'name' => 'راسربک پشت',
        ]));

        $back = $this->carveRacer($back, $racer, $depth);

        $notes = [
            $this->finishNote($params, ['یقه', 'حلقهٔ پشت']),
            ['type' => 'info', 'text' => 'حلقهٔ پشت گودتر از جلوست و بند به مرکز پشت نزدیک می‌شود؛ همین است که بند را روی شانه نگه می‌دارد.'],
            ['type' => 'warning', 'text' => 'کتف باز می‌ماند: اگر پارچه نازک است، لبهٔ حلقهٔ پشت را با نوار اریب دولا تمام کنید تا کش نیاید.'],
        ];

        return $this->finishBlock($this->noted([$front, $back], $notes), $g);
    }

    /**
     * گود کردن حلقهٔ پشت تا بند به مرکز نزدیک شود.
     *
     * به‌جای بریدن، خودِ نقطه‌های حلقه جابه‌جا می‌شوند: نوک سرشانه به اندازهٔ
     * پهنای بندِ مرکز تو می‌آید و منحنی حلقه از همان‌جا پایین می‌افتد. این کار
     * از برش امن‌تر است چون مسیر بسته دست‌نخورده می‌ماند.
     */
    protected function carveRacer(array $piece, float $racerWidth, float $depth): array
    {
        $outline = array_values($piece['outline']);
        $edges = array_values($piece['meta']['edges'] ?? []);
        $shoulder = array_search('shoulder', $edges, true);

        if ($shoulder === false || ! isset($outline[$shoulder + 1])) {
            return $piece;
        }

        // نقطهٔ پایان سرشانه = نوک بند؛ به مرکز پشت نزدیکش می‌کنیم و پایین می‌بریم
        $tip = $shoulder + 1;
        $centerX = (float) ($piece['meta']['center_x'] ?? 0);
        $target = $centerX + max(2.0, $racerWidth);

        $outline[$tip]['x'] = round(min((float) $outline[$tip]['x'], $target), 2);
        $outline[$tip]['y'] = round((float) $outline[$tip]['y'] + max(0.0, $depth), 2);

        // نقطهٔ کنترل منحنی بعدی هم با نوک جابه‌جا می‌شود وگرنه حلقه گره می‌خورد
        if (isset($outline[$tip + 1]['cx'])) {
            $outline[$tip + 1]['cx'] = round(min(
                (float) $outline[$tip + 1]['cx'],
                $target + 1.0,
            ), 2);
            $outline[$tip + 1]['cy'] = round((float) $outline[$tip + 1]['cy'] + ($depth * 0.35), 2);
        }

        $piece['outline'] = $outline;
        $piece['meta']['racerback'] = true;

        return Geometry::normalizePiece($this->dropStrayMarks($piece));
    }
}
