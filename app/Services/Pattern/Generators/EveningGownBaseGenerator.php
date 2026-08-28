<?php

namespace App\Services\Pattern\Generators;

/**
 * لایه ترکیبی لباس مجلسی و عروس.
 *
 * هر لباسِ این خانواده از سه انتخاب ساخته می‌شود و بس: بالاتنه (خطِ بالا و جای
 * خطِ کمر)، دامن (کدام دامنِ کاتالوگ، با چه بلندی و گشادی)، و بست. EveningBase
 * هر سه را دارد؛ این لایه فقط ترتیبِ آن‌ها را یک‌جا می‌نویسد تا مدلِ تازه یک
 * جدولِ چندسطری باشد، نه یک generate تازه.
 *
 * دو چیز در همهٔ این لباس‌ها ثابت است و هر دو این‌جا اجرا می‌شوند:
 *
 *   ۱. بالاتنه و دامن با *یک* آزادی درفت می‌شوند (gownEase). اگر هرکدام آزادیِ
 *      خودش را بگیرد، دو کمر با چند سانتی‌متر اختلاف درمی‌آیند و کسی تا لحظهٔ
 *      دوخت نمی‌فهمد.
 *   ۲. اختلافِ کمرِ دامن و کمرِ بالاتنه اندازه گرفته و اعلام می‌شود (joinWaist)،
 *      نه پنهان.
 */
abstract class EveningGownBaseGenerator extends EveningBaseGenerator
{
    /**
     * شخصیتِ این مدل.
     *
     * کلیدها: prefix، title، skirt (کلیدِ دامنِ کاتالوگ)، length، skirt_params
     * (پارامترهای همان دامن، با نامِ خودش)، skirt_schema (پارامترهای نمایانِ
     * کاربر و نگاشتشان)، neckline، bodice_length، closure، lining، boning،
     * fit، notes، extra.
     *
     * @return array<string, mixed>
     */
    abstract protected function gown(): array;

    public function label(): string
    {
        return (string) ($this->gown()['title'] ?? 'لباس مجلسی');
    }

    public function paramsSchema(): array
    {
        $w = $this->gown();

        return array_merge(
            $this->fitParam((string) ($w['fit'] ?? 'regular')),
            $this->eveningSchema(
                array_merge(
                    $this->gownLengthParam((float) ($w['length'] ?? 110)),
                    (array) ($w['extra'] ?? []),
                ),
                array_filter([
                    'neckline' => $w['neckline'] ?? null,
                    'bodice_length' => $w['bodice_length'] ?? null,
                    'closure' => $w['closure'] ?? null,
                    'lining' => $w['lining'] ?? null,
                    'boning' => $w['boning'] ?? null,
                ], fn ($v) => $v !== null),
            ),
        );
    }

    public function generate(array $measurements, array $ease, array $params): array
    {
        $w = $this->gown();
        $prefix = (string) ($w['prefix'] ?? static::key());

        // یک آزادی برای بالاتنه و دامن، وگرنه دو کمر به هم نمی‌رسند
        $ease = $this->gownEase($ease, $params);

        [$bodice, $waist] = $this->eveningBodice($measurements, $ease, $params, ['prefix' => $prefix]);

        $overrides = ['length' => (float) $this->param($params, 'skirt_length', $w['length'] ?? 110)];

        foreach ((array) ($w['skirt_params'] ?? []) as $name => $value) {
            // مقدارِ رشته‌ای یعنی «این را از پارامترِ کاربر بخوان»
            $overrides[$name] = is_string($value) && ! is_numeric($value)
                ? $this->param($params, $value, 0)
                : $value;
        }

        $skirt = $this->gownSkirt(
            (string) ($w['skirt'] ?? 'skirt_a_line'),
            $measurements,
            $ease,
            $overrides,
            $prefix,
        );

        [$skirt, $waistNotes] = $this->joinWaist($skirt, $waist);

        $pieces = array_merge($bodice, $skirt);

        [$pieces, $closureNotes] = $this->gownClosure($pieces, $params, $measurements);
        $pieces = $this->gownLining($pieces, $params);

        $notes = array_merge(
            $waistNotes,
            $closureNotes,
            $this->gownNotes($params),
            (array) ($w['notes'] ?? []),
        );

        return $this->finish($this->noted($pieces, array_map(
            fn (string $text) => ['type' => 'info', 'text' => $text],
            $notes,
        )));
    }
}
