<?php

namespace App\Services\Pattern\Generators;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Style\Sleeve\RaglanSleeveStyle;

/**
 * پایه مشترک لباس‌های رگلان.
 *
 * رگلان «یک جور آستین» نیست؛ یک جور بریدنِ تنه است. اگر آستین رگلان به حلقه‌ی
 * نبریده دوخته شود، سرآستینِ کوتاهِ رگلان (که عمداً افتاده و کم‌ارتفاع است) با
 * حلقه‌ی کاملِ بالاتنه هیچ نسبتی ندارد — همان اشتباهی که یک بار در این پروژه
 * سرآستین ۲۶٫۶ را روبه‌روی حلقه‌ی ۵۴٫۸ گذاشت. پس این خانواده کار را کامل انجام
 * می‌دهد:
 *
 *   ۱. تنه‌ی جلو و پشت با همان درفت آزموده‌ی پیراهن ساخته می‌شود.
 *   ۲. روی هر دو، خطی از خط یقه تا حلقه‌ی آستین بریده می‌شود. تکه‌ی بالای این
 *      خط — سرگردن، سرشانه و بالای حلقه — از تنه جدا می‌شود و به آستین می‌رود؛
 *      پس تنه دیگر نه درز سرشانه دارد و نه حلقه‌ی کامل.
 *   ۳. آستین از روی همان خطِ بریده‌شده درفت می‌شود: طول درز رگلان، طول حلقه‌ی
 *      پایینِ مانده، طول سرشانه‌ی منتقل‌شده و زاویه‌ی خط یقه در سرگردن، همگی از
 *      خودِ تنه خوانده و روی آستین با پرگار پیاده می‌شوند.
 *   ۴. بعد از برش، طول حلقه و طول یقه‌ی هر قطعه دوباره اندازه گرفته می‌شود
 *      (refreshAfterCut)، وگرنه هر اندازه‌گیرِ بعدی — از نوار یقه تا برگه‌ی فنی —
 *      عددِ قطعه‌ای را می‌خواند که دیگر وجود ندارد.
 *
 * بریدن و درفت آستین کار سبک آزموده‌ی sleeve_raglan است و این‌جا دوباره نوشته
 * نشده؛ همان موتور با پارامترهای این مدل صدا زده می‌شود.
 */
abstract class RaglanBaseGenerator extends ShirtBaseGenerator
{
    use PieceRoles;

    /**
     * پارامترهای مشترک لباس‌های رگلان.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function raglanSchema(array $defaults = [], array $extra = []): array
    {
        $schema = $this->shirtSchema($defaults);

        // سرشانه و حلقه‌ی رگلان جای «افتادن سرشانه» را می‌گیرند: در رگلان سرشانه
        // اصلاً روی تنه نیست که بیفتد.
        unset($schema['drop_shoulder'], $schema['cap_ease']);

        return array_merge($schema, [
            'raglan_neck' => [
                'label' => 'شروع خط رگلان روی خط یقه', 'min' => 2, 'max' => 12, 'step' => 0.5,
                'default' => 4.5, 'unit' => 'سانتی‌متر',
                'hint' => 'از سرگردن به سمت مرکز اندازه گرفته می‌شود؛ بیشتر یعنی بند رگلان پهن‌تر و یقه بازتر.',
            ],
            'raglan_armhole' => [
                'label' => 'باقی‌ماندن حلقه روی تنه', 'min' => 4, 'max' => 18, 'step' => 0.5,
                'default' => 8, 'unit' => 'سانتی‌متر',
                'hint' => 'از زیر بغل به بالا؛ کمتر یعنی خط رگلان پایین‌تر به حلقه می‌رسد.',
            ],
            'raglan_curve' => [
                'label' => 'خمیدگی خط رگلان', 'min' => 0, 'max' => 3, 'step' => 0.25,
                'default' => 0.75, 'unit' => 'سانتی‌متر',
            ],
            'underarm_drop' => [
                'label' => 'پایین‌آوردن زیر بغل', 'min' => 0, 'max' => 6, 'step' => 0.5,
                'default' => 1.5, 'unit' => 'سانتی‌متر',
            ],
            'cap_softness' => [
                'label' => 'بلندی سر آستین', 'min' => 0.35, 'max' => 1, 'step' => 0.05, 'default' => 0.7,
                'hint' => 'کمتر یعنی آستین افتاده‌تر و راحت‌تر؛ همان چیزی که رگلان را رگلان می‌کند.',
            ],
            'sleeve_head' => [
                'label' => 'سر آستین', 'type' => 'select', 'default' => 'one_piece',
                'options' => [
                    'one_piece' => 'یک‌تکه ساده',
                    'dart' => 'یک‌تکه با ساسون سرشانه',
                    'two_piece' => 'دوتکه با درز سرشانه',
                ],
            ],
        ], $extra);
    }

    /* ---------------------------------------------------------------------
     |  برش رگلان
     * ------------------------------------------------------------------- */

    /**
     * بریدن تنه و ساختن آستین رگلان.
     *
     * ورودی فقط تنه‌ی جلو و پشت است؛ نوار و جیب بعداً کنارش گذاشته می‌شوند تا
     * موتور برش با قطعه‌ای که حلقه ندارد کاری نداشته باشد.
     *
     * @param  array<int, array<string, mixed>>  $body  [تنه جلو، تنه پشت]
     * @return array{pieces: array<int, array<string, mixed>>, notes: array<int, string>}
     */
    protected function raglanCut(array $m, array $ease, array $params, array $body, array $o = []): array
    {
        $style = new RaglanSleeveStyle;

        $context = [
            'measurements' => $m,
            'ease' => $ease,
            'params' => array_merge([
                'neck_start' => (float) $this->param($params, 'raglan_neck', 4.5),
                'armhole_join' => (float) $this->param($params, 'raglan_armhole', 8),
                'raglan_curve' => (float) $this->param($params, 'raglan_curve', 0.75),
                'underarm_drop' => (float) $this->param($params, 'underarm_drop', 1.5),
                'cap_softness' => (float) $this->param($params, 'cap_softness', 0.7),
                'head' => (string) $this->param($params, 'sleeve_head', 'one_piece'),
                'length_extra' => (float) ($o['sleeve_length'] ?? $this->m($m, 'arm_length', 58))
                    - $this->m($m, 'arm_length', 58),
                'hem_ease' => (float) ($o['hem_ease'] ?? 6),
            ], $o['style'] ?? []),
        ];

        $supported = $style->supports($body, $context);

        if ($supported !== true) {
            // نباید پیش بیاید؛ ولی اگر پیش آمد، لباس بی‌آستین و با پیام روشن
            // برمی‌گردد، نه با آستینی که به هیچ حلقه‌ای نمی‌خورد.
            return ['pieces' => $body, 'notes' => [$supported]];
        }

        $result = $style->apply($body, $context);
        $pieces = [];

        foreach ($result['pieces'] as $piece) {
            // بعد از برش، طول حلقه و یقه‌ی هر قطعه دوباره از روی لبه‌های واقعی
            // خودش اندازه گرفته می‌شود؛ عدد پیش از برش دیگر معنا ندارد.
            $pieces[] = $this->refreshAfterCut($piece);
        }

        return ['pieces' => $pieces, 'notes' => $result['notes'] ?? []];
    }

    /* ---------------------------------------------------------------------
     |  اندازه‌گیری پس از برش
     * ------------------------------------------------------------------- */

    /**
     * دور کامل خط یقه‌ی یک لباس رگلان.
     *
     * خط یقه‌ی رگلان از چهار جا ساخته می‌شود و اگر یکی‌شان جا بماند، نوار یقه
     * کوتاه بریده می‌شود و لباس از سر رد نمی‌شود: یقه‌ی مانده روی جلو، یقه‌ی
     * مانده روی پشت، و لبه‌ی یقه‌ی خودِ آستین‌ها.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function raglanNeckline(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            $edges = Geometry::edgesWithTag($piece, 'neck');

            if ($edges === []) {
                continue;
            }

            $length = 0.0;

            foreach ($edges as $edge) {
                $length += Geometry::edgeLength($piece['outline'], $edge);
            }

            $repeats = ! empty($piece['on_fold'])
                ? 2 * max(1, (int) ($piece['cut_quantity'] ?? 1))
                : max(1, (int) ($piece['cut_quantity'] ?? 1));

            $total += $length * $repeats;
        }

        return round($total, 2);
    }

    /**
     * پهنای لبه‌ی پایین لباس (برای نوار کشباف دم).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function hemWidthOf(array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if (! in_array($piece['meta']['part'] ?? '', ['front_bodice', 'back_bodice'], true)) {
                continue;
            }

            foreach (Geometry::edgesWithTag($piece, 'hem') as $edge) {
                $total += Geometry::edgeLength($piece['outline'], $edge)
                    * (empty($piece['on_fold']) ? 1 : 2)
                    * max(1, (int) ($piece['cut_quantity'] ?? 1));
            }
        }

        return round($total, 2);
    }

    /**
     * دور دم آستین (برای مچ کشباف).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     */
    protected function sleeveHemOf(array $pieces): float
    {
        foreach ($pieces as $piece) {
            if (($piece['meta']['part'] ?? '') !== 'sleeve') {
                continue;
            }

            $length = 0.0;

            foreach (Geometry::edgesWithTag($piece, 'hem') as $edge) {
                $length += Geometry::edgeLength($piece['outline'], $edge);
            }

            if ($length > 1.0) {
                // آستین یک‌تکه دم کامل دارد؛ آستین دوتکه نیمی از آن را
                return ($piece['meta']['head'] ?? 'one_piece') === 'two_piece' ? $length * 2 : $length;
            }
        }

        return 0.0;
    }
}
