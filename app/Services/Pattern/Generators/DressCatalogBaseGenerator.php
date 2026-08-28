<?php

namespace App\Services\Pattern\Generators;

/**
 * لایه ترکیبی پیراهن زنانه.
 *
 * پیراهن‌های کاتالوگ دو خانواده‌اند و تفاوتشان یک چیز است: خطِ کمر دارند یا نه.
 *
 *   onepiece  از سرشانه تا دم یک قطعه؛ خطِ کمر ندارد، پس نمی‌تواند از آن‌جا
 *             بشکند، ولی همهٔ وزنش روی سرشانه است.
 *   waisted   بالاتنه و دامن جدا، به هم دوخته؛ دورِ کمرِ دامن از روی *همان*
 *             کمری خوانده می‌شود که بالاتنه درآورده، نه از عددی که موقع درفت
 *             در ذهن بوده.
 *
 * دامنِ مدلِ کمردار از کاتالوگِ دامن می‌آید — کلوش، ترک‌دار، پیلی‌دار و بقیه
 * همه آزموده‌اند و دوباره درفت نمی‌شوند.
 */
abstract class DressCatalogBaseGenerator extends DressBaseGenerator
{
    /**
     * شخصیتِ این پیراهن.
     *
     * کلیدها: prefix، title، form (onepiece|waisted)، shape، length،
     * hem_flare، bust_dart، waist_dart، sleeve، sleeve_length، neck_depth،
     * closure، lining، fit، ease، skirt (کلیدِ دامنِ کاتالوگ)، skirt_length،
     * skirt_params، block، extra، notes.
     *
     * @return array<string, mixed>
     */
    abstract protected function dress(): array;

    public function label(): string
    {
        return (string) ($this->dress()['title'] ?? 'پیراهن');
    }

    public function paramsSchema(): array
    {
        $d = $this->dress();
        $waisted = (string) ($d['form'] ?? 'onepiece') === 'waisted';

        $extra = array_merge(
            $waisted
                ? $this->skirtLengthParam((float) ($d['skirt_length'] ?? 62))
                : [
                    'length' => [
                        'label' => 'بلندی از خط کمر', 'min' => 20, 'max' => 120, 'step' => 1,
                        'default' => (float) ($d['length'] ?? 45), 'unit' => 'سانتی‌متر',
                    ],
                ],
            [
                'hem_flare' => [
                    'label' => 'باز شدن دم در هر پهلو', 'min' => 0, 'max' => 20, 'step' => 0.5,
                    'default' => (float) ($d['hem_flare'] ?? 2), 'unit' => 'سانتی‌متر',
                ],
                'bust_dart' => [
                    'label' => 'ساسون سینه', 'type' => 'toggle',
                    'default' => (bool) ($d['bust_dart'] ?? true),
                ],
            ],
            $this->sleeveParam(
                (string) ($d['sleeve'] ?? 'none'),
                (float) ($d['sleeve_length'] ?? 20),
                ['none' => 'بدون آستین', 'set_in' => 'آستین حلقه‌ای'],
            ),
        );

        if ($waisted) {
            unset($extra['hem_flare']);
        }

        return $this->dressSchema(
            array_merge($extra, (array) ($d['extra'] ?? [])),
            array_filter([
                'fit' => $d['fit'] ?? null,
                'back_closure' => $d['closure'] ?? null,
                'lining' => $d['lining'] ?? null,
            ], fn ($v) => $v !== null),
            (array) ($d['block'] ?? []),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $d = $this->dress();

        return (string) ($d['form'] ?? 'onepiece') === 'waisted'
            ? $this->waistedDress($measurements, $ease, $params, $d)
            : $this->onePieceDress($measurements, $ease, $params, $d);
    }

    /**
     * پیراهنِ بی‌خطِ کمر: از سرشانه تا دم، یک قطعه.
     *
     * @param  array<string, mixed>  $d
     * @return array<int, array<string, mixed>>
     */
    protected function onePieceDress(array $measurements, array $ease, array $params, array $d): array
    {
        $prefix = (string) ($d['prefix'] ?? static::key());
        $ease = $this->dressEase($ease, $params, (array) ($d['ease'] ?? ['bust' => 6.0, 'waist' => 6.0, 'hip' => 5.0]));
        $g = $this->blockMetrics($measurements, $ease, $params);
        $seam = (string) $this->param($params, 'back_closure', $d['closure'] ?? 'zip') !== 'none';

        [$bodice] = $this->dressBodice($g, $params, [
            'prefix' => $prefix,
            'shape' => (string) ($d['shape'] ?? 'straight'),
            'bottom_tag' => 'hem',
            'length' => (float) $this->param($params, 'length', $d['length'] ?? 45),
            'hem_flare' => (float) $this->param($params, 'hem_flare', $d['hem_flare'] ?? 2),
            'waist_dart' => (bool) ($d['waist_dart'] ?? false),
            'bust_dart' => $this->flag($params, 'bust_dart', (bool) ($d['bust_dart'] ?? true)),
            'back_seam' => $seam,
            'front_name' => 'جلوی پیراهن',
            'back_name' => $seam ? 'پشت پیراهن (درز مرکزی)' : 'پشت پیراهن',
        ]);

        [$pieces, $closureNotes] = $this->dressClosure($bodice, $g, $params, ['below' => 0.0]);

        return $this->finishDress($measurements, $ease, $params, $d, $g, $bodice, $pieces, $closureNotes);
    }

    /**
     * پیراهنِ کمردار: بالاتنه از بلوک، دامن از کاتالوگ.
     *
     * @param  array<string, mixed>  $d
     * @return array<int, array<string, mixed>>
     */
    protected function waistedDress(array $measurements, array $ease, array $params, array $d): array
    {
        $prefix = (string) ($d['prefix'] ?? static::key());
        $ease = $this->dressEase($ease, $params, (array) ($d['ease'] ?? ['bust' => 5.0, 'waist' => 3.0, 'hip' => 5.0]));
        $g = $this->blockMetrics($measurements, $ease, $params);
        $seam = (string) $this->param($params, 'back_closure', $d['closure'] ?? 'zip') !== 'none';

        [$bodice, $waist] = $this->dressBodice($g, $params, [
            'prefix' => $prefix,
            'shape' => 'fitted',
            'bottom_tag' => 'waist',
            'length' => 0.0,
            'waist_dart' => (bool) ($d['waist_dart'] ?? true),
            'bust_dart' => $this->flag($params, 'bust_dart', (bool) ($d['bust_dart'] ?? true)),
            'back_seam' => $seam,
            'front_name' => 'بالاتنه جلو',
            'back_name' => $seam ? 'بالاتنه پشت (درز مرکزی)' : 'بالاتنه پشت',
        ]);

        $overrides = ['length' => (float) $this->param($params, 'skirt_length', $d['skirt_length'] ?? 62)];

        foreach ((array) ($d['skirt_params'] ?? []) as $name => $value) {
            $overrides[$name] = is_string($value) && ! is_numeric($value)
                ? $this->param($params, $value, 0)
                : $value;
        }

        $skirt = $this->catalogSkirt(
            (string) ($d['skirt'] ?? 'skirt_a_line'),
            $measurements,
            $ease,
            $overrides,
            $prefix,
        );

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        [$pieces, $closureNotes] = $this->dressClosure(array_merge($bodice, $skirt), $g, $params, ['below' => 18.0]);

        return $this->finishDress(
            $measurements,
            $ease,
            $params,
            $d,
            $g,
            $bodice,
            $pieces,
            array_merge($waistNotes, $closureNotes),
        );
    }

    /**
     * آستین، سجاف، آستر و یادداشت‌ها — همان پایانی که هر دو فرم دارند.
     *
     * @param  array<string, mixed>  $d
     * @param  array<string, float>  $g
     * @param  array<int, array<string, mixed>>  $bodice
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    protected function finishDress(
        array $measurements,
        array $ease,
        array $params,
        array $d,
        array $g,
        array $bodice,
        array $pieces,
        array $notes,
    ): array {
        $prefix = (string) ($d['prefix'] ?? static::key()).'-';

        $pieces = array_merge(
            $pieces,
            $this->dressSleeves($measurements, $ease, $params, $bodice, $g, ['prefix' => $prefix]),
            [$this->backNeckFacingPiece($g, ['prefix' => $prefix, 'width' => 6])],
        );

        if ((string) $this->param($params, 'sleeve_style', $d['sleeve'] ?? 'none') === 'none') {
            $pieces[] = $this->armholeBindingPiece($this->armholeOf($bodice), ['prefix' => $prefix]);
        }

        $pieces = $this->dressLining($pieces, $params);

        return $this->finish($this->noted($pieces, array_merge($notes, (array) ($d['notes'] ?? []))));
    }
}
