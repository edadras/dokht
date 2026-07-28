<?php

namespace App\Services\Pattern\Style\Collar;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\StyleModifier;
use App\Support\Format;

/**
 * پایه همه سبک‌های «یقه».
 *
 * تفاوت «خط یقه» و «یقه» این است: خط یقه شکل بریدگی گردن روی تنه است، ولی یقه
 * قطعه‌ای است که روی همان بریدگی دوخته می‌شود. پس هر یقه باید اول خط یقه‌ای که
 * به آن داده شده را اندازه بگیرد و بعد خودش را به همان اندازه درفت کند — نه به
 * اندازه‌ای که در کتاب نوشته شده.
 *
 * کار مشترک همه یقه‌ها همین‌جا انجام می‌شود:
 *
 *   ۱. لبه‌های «neck» همه قطعه‌های تنه اندازه گرفته می‌شود و شمار برش هر قطعه
 *      (تای پارچه یا دو تکه) در حساب می‌آید، تا دور کامل خط یقه به دست بیاید.
 *   ۲. سبک فقط draft() را می‌نویسد: با طول خط یقه، قطعه‌های یقه را می‌سازد.
 *   ۳. هر قطعه یقه با fitToNeckline() چند بار درفت می‌شود تا لبه یقه‌اش دقیقاً
 *      روی خط یقه بنشیند، بعد با PieceOps روی خط یقه «پیاده» و راست می‌شود.
 *   ۴. نشانه مرکز پشت، سرشانه و مرکز جلو روی لبه یقه می‌نشیند.
 *   ۵. یادداشت فارسی با طول نهایی خط یقه و آزادی به‌کاررفته برگردانده می‌شود.
 *
 * یقه‌هایی که تنه را هم عوض می‌کنند (یقه انگلیسی، نوک‌تیز و شال، که برگردانشان
 * تکه‌ای از خود تنه است) این کار را در prepare() می‌کنند؛ اندازه‌گیری خط یقه پس
 * از آن انجام می‌شود، چون خط یقه دیگر همانی نیست که بود.
 */
abstract class BaseCollar implements StyleModifier
{
    use CollarGeometry;

    public static function group(): string
    {
        return 'collar';
    }

    /**
     * درفت قطعه‌های یقه.
     *
     * @param  array{front: float, back: float, half: float, full: float, pieces: int}  $neck
     * @param  array<string, mixed>  $p  پارامترهای پاک‌شده
     * @param  array<int, array<string, mixed>>  $pieces  قطعه‌های لباس (پس از prepare)
     * @return array{pieces: array<int, array<string, mixed>>, notes?: array<int, string>, meta?: array<string, mixed>}
     */
    abstract protected function draft(array $neck, array $p, array $pieces, array $context): array;

    /**
     * دست‌کاری تنه پیش از اندازه‌گیری خط یقه (برگردان یقه انگلیسی و شال).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{pieces: array<int, array<string, mixed>>, notes: array<int, string>}
     */
    protected function prepare(array $pieces, array $p, array $context): array
    {
        return ['pieces' => $pieces, 'notes' => []];
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری خط یقه
     * ------------------------------------------------------------------- */

    /**
     * خط یقه واقعی این لباس.
     *
     * هر قطعه تنه به اندازه شمار برشش در دور یقه سهم دارد: قطعه‌ای که روی تای
     * پارچه بریده می‌شود دو برابر، و قطعه‌ای که دو تکه (چپ و راست) بریده می‌شود
     * هم دو برابر. «front» و «back» سهم یک طرف بدن‌اند، «half» نیم دور یقه از
     * مرکز پشت تا مرکز جلو، و «full» دور کامل.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{front: float, back: float, half: float, full: float, pieces: int}
     */
    protected function measureNeckline(array $pieces): array
    {
        $sides = ['front' => 0.0, 'back' => 0.0];
        $count = 0;

        foreach ($pieces as $piece) {
            if (! $this->isBodyPiece($piece)) {
                continue;
            }

            $length = $this->neckEdgeLength($piece);

            if ($length <= 0.01) {
                continue;
            }

            $copies = ! empty($piece['on_fold']) ? 2 : max(1, (int) ($piece['cut_quantity'] ?? 1));
            $side = $this->sideOf($piece) === 'back' ? 'back' : 'front';
            $sides[$side] += ($length * $copies) / 2;
            $count++;
        }

        $half = $sides['front'] + $sides['back'];

        return [
            'front' => round($sides['front'], 3),
            'back' => round($sides['back'], 3),
            'half' => round($half, 3),
            'full' => round($half * 2, 3),
            'pieces' => $count,
        ];
    }

    /* ---------------------------------------------------------------------
     |  پذیرش
     * ------------------------------------------------------------------- */

    public function supports(array $pieces, array $context): true|string
    {
        $neck = $this->measureNeckline($pieces);

        if ($neck['pieces'] === 0 || $neck['full'] < 1.0) {
            return $this->noNeckMessage();
        }

        if ($neck['full'] < 20.0) {
            return 'دور خط یقه این لباس '.Format::cm($neck['full'])
                .' است و کمتر از آن است که یقه‌ای رویش دوخته شود؛ اول خط یقه را بزرگ‌تر کنید.';
        }

        return $this->supportsCollar($pieces, $context);
    }

    /** پیام نپذیرفتن وقتی لبه یقه‌ای در کار نیست. */
    protected function noNeckMessage(): string
    {
        return 'در قطعه‌های این لباس لبه‌ای با برچسب خط یقه نیست؛ '.$this->label()
            .' فقط روی لباسی دوخته می‌شود که خط یقه داشته باشد (روی دامن یا پایین‌تنه جای دوختن ندارد).';
    }

    /** بررسی ویژه هر یقه؛ پیش‌فرض پذیرش. */
    protected function supportsCollar(array $pieces, array $context): true|string
    {
        return true;
    }

    /**
     * آیا این لباس جلوباز است؟
     *
     * برگردان و شال و کاپشن بدون چاک جلو معنا ندارند. نشانه‌های جلوباز بودن:
     * اضافه جای دکمه، سبک بست، پاتلت، یا تنه جلویی که دیگر روی تای پارچه بریده
     * نمی‌شود و دو تکه است.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function frontOpening(array $pieces, array $context): bool
    {
        $flag = $context['front_opening'] ?? ($context['params']['front_opening'] ?? null);

        if ($flag !== null) {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        foreach ($pieces as $piece) {
            $part = (string) ($piece['meta']['part'] ?? '');

            if ($part === 'placket') {
                return true;
            }

            if (! $this->isBodyPiece($piece) || $this->sideOf($piece) !== 'front') {
                continue;
            }

            $meta = $piece['meta'] ?? [];

            if ((float) ($meta['button_stand'] ?? 0) > 0.01 || (float) ($meta['center_x'] ?? 0) > 0.01) {
                return true;
            }

            if (! empty($meta['closure']) || ! empty($meta['opening']) || ! empty($meta['zip'])) {
                return true;
            }

            if (empty($piece['on_fold']) && (int) ($piece['cut_quantity'] ?? 1) >= 2) {
                return true;
            }
        }

        $closure = $context['closure'] ?? ($context['styles']['closure'] ?? null);

        return is_string($closure) && $closure !== '' && $closure !== 'none';
    }

    /**
     * اضافه جلوی لباس (جای دکمه)، اگر داشته باشد.
     *
     * یقه‌ای که سر جلویش از مرکز جلو جلوتر می‌رود باید دقیقاً به اندازه همین
     * اضافه جلوتر برود، وگرنه دکمه یقه روی خط دکمه‌های لباس نمی‌افتد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function buttonStandOf(array $pieces): float
    {
        $stand = 0.0;

        foreach ($pieces as $piece) {
            if (! $this->isBodyPiece($piece) || $this->sideOf($piece) !== 'front') {
                continue;
            }

            $meta = $piece['meta'] ?? [];
            $stand = max($stand, (float) ($meta['button_stand'] ?? 0), (float) ($meta['center_x'] ?? 0));
        }

        return round($stand, 2);
    }

    /**
     * اضافه سر جلوی یقه: اگر لباس اضافه جلو دارد همان، وگرنه مقدار خواسته‌شده.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array{0: float, 1: string|null} اندازه و یادداشت (اگر پارامتر کنار گذاشته شد)
     */
    protected function frontExtension(array $pieces, array $context, float $wanted): array
    {
        if (! $this->frontOpening($pieces, $context)) {
            return [0.0, null];
        }

        $stand = $this->buttonStandOf($pieces);

        if ($stand < 0.05 || abs($stand - $wanted) <= 0.2) {
            return [$stand > 0.05 ? $stand : $wanted, null];
        }

        return [$stand, 'اضافه سر جلوی یقه '.Format::cm($stand).' گرفته شد، نه '.Format::cm($wanted)
            .' که خواسته شده بود؛ یقه باید هم‌اندازه اضافه جلوی خود لباس جلوتر برود تا دکمه یقه روی خط دکمه‌ها بیفتد.'];
    }

    /** تنه‌های جلو (برای برگردان). @return array<int, int> */
    protected function frontIndexes(array $pieces): array
    {
        $found = [];

        foreach ($pieces as $index => $piece) {
            if ($this->isBodyPiece($piece) && $this->sideOf($piece) === 'front') {
                $found[] = $index;
            }
        }

        return $found;
    }

    /* ---------------------------------------------------------------------
     |  اجرا
     * ------------------------------------------------------------------- */

    public function apply(array $pieces, array $context): array
    {
        $p = $this->params($context);
        $pieces = $this->withoutOwnPieces(array_values($pieces));
        $prepared = $this->prepare($pieces, $p, $context);
        $pieces = array_values($prepared['pieces']);
        $notes = array_values($prepared['notes'] ?? []);

        $neck = $this->measureNeckline($pieces);
        $draft = $this->draft($neck, $p, $pieces, $context);

        $made = [];
        $sort = 80;

        foreach ($draft['pieces'] ?? [] as $piece) {
            $piece['sort'] = $piece['sort'] ?? $sort++;
            $piece['meta']['collar_style'] = static::key();
            $piece['meta']['neckline_measured'] = $neck['full'];
            $made[] = Geometry::normalizePiece($piece);
        }

        foreach ($draft['notes'] ?? [] as $note) {
            $notes[] = $note;
        }

        $meta = $draft['meta'] ?? [];
        $notes[] = $this->label().': دور کامل خط یقه '.Format::cm($neck['full'])
            .' اندازه گرفته شد — نیم‌یقه '.Format::cm($neck['half'])
            .' (پشت '.Format::cm($neck['back']).' و جلو '.Format::cm($neck['front']).').';
        $notes[] = $this->easeNote($meta);

        return [
            'pieces' => array_merge($pieces, $made),
            'notes' => array_values(array_filter($notes)),
            'meta' => [
                'collar' => array_merge([
                    'style' => static::key(),
                    'neckline' => $neck,
                    'pieces' => count($made),
                ], $meta),
            ],
        ];
    }

    /**
     * برداشتن قطعه‌هایی که همین سبک پیش‌تر ساخته بود.
     *
     * اگر کاربر یقه را عوض کند یا دوباره همین یقه را بزند، نباید دو یقه روی هم
     * بماند؛ درفت تازه جای درفت پیشین را می‌گیرد.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array<string, mixed>>
     */
    protected function withoutOwnPieces(array $pieces): array
    {
        return array_values(array_filter(
            $pieces,
            fn (array $piece) => ($piece['meta']['collar_style'] ?? null) !== static::key(),
        ));
    }

    /**
     * یادداشت طول نهایی و آزادی.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function easeNote(array $meta): string
    {
        $target = (float) ($meta['target'] ?? 0);
        $measured = (float) ($meta['measured'] ?? 0);

        if ($target <= 0) {
            return '';
        }

        $difference = round($measured - $target, 2);
        $ease = round((float) ($meta['ease'] ?? 0), 2);

        $text = 'لبه یقه پس از پیاده‌کردن روی خط یقه '.Format::cm($measured)
            .' درآمد و باید '.Format::cm($target).' می‌شد؛ اختلاف '.Format::cm(abs($difference)).'.';

        $text .= abs($difference) <= static::NECK_TOLERANCE
            ? ' یقه راست است و بدون کش‌آمدن روی خط یقه می‌نشیند.'
            : ' این اختلاف بیش از یک‌دهم سانتی‌متر است؛ پیش از برش، یقه را دوباره روی خط یقه پیاده کنید.';

        if (abs($ease) > 0.01) {
            $text .= $ease > 0
                ? ' آزادی به‌کاررفته '.Format::cm($ease).' است؛ همین اندازه پارچه در دوخت روی یقه جا داده می‌شود.'
                : ' آزادی به‌کاررفته منفی است: یقه '.Format::cm(abs($ease))
                    .' کوتاه‌تر از خط یقه بریده شد تا موقع دوخت کشیده شود و لبه یقه باز نایستد.';
        } else {
            $text .= ' آزادی به‌کاررفته صفر است؛ یقه سربه‌سر خط یقه بریده شد.';
        }

        return $text;
    }

    /* ---------------------------------------------------------------------
     |  پارامترها
     * ------------------------------------------------------------------- */

    /**
     * پارامترهای پاک‌شده: مقدار کاربر یا پیش‌فرض، در بازه مجاز.
     *
     * @return array<string, mixed>
     */
    protected function params(array $context): array
    {
        $given = is_array($context['params'] ?? null) ? $context['params'] : [];
        $out = [];

        foreach ($this->paramsSchema() as $key => $field) {
            $value = $given[$key] ?? $field['default'];
            $type = $field['type'] ?? 'number';

            if ($type === 'toggle') {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $field['default'];

                continue;
            }

            if ($type === 'select') {
                $out[$key] = array_key_exists((string) $value, $field['options'] ?? []) ? (string) $value : $field['default'];

                continue;
            }

            $number = is_numeric($value) ? (float) $value : (float) $field['default'];

            if (isset($field['min'])) {
                $number = max((float) $field['min'], $number);
            }

            if (isset($field['max'])) {
                $number = min((float) $field['max'], $number);
            }

            $out[$key] = $number;
        }

        return $out;
    }

    /** آزادی خط یقه؛ روی همه یقه‌های دوختنی هست. */
    protected function easeField(float $default = 0.0): array
    {
        return [
            'label' => 'آزادی لبه یقه', 'min' => -1.5, 'max' => 2, 'step' => 0.1, 'default' => $default,
            'unit' => 'سانتی‌متر', 'hint' => 'یقه به همین اندازه بلندتر (یا با عدد منفی، کوتاه‌تر) از خط یقه بریده می‌شود.',
        ];
    }

    /** لایه چسب؛ تقریباً همه یقه‌ها لایه می‌خواهند. */
    protected function interfacingField(bool $default = true): array
    {
        return [
            'label' => 'لایه چسب', 'type' => 'toggle', 'default' => $default,
            'hint' => 'لایه کمی کوچک‌تر از یقه بریده می‌شود تا در درز جمع نکند.',
        ];
    }
}
