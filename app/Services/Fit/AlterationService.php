<?php

namespace App\Services\Fit;

use App\Models\Fitting;
use App\Models\Pattern;
use App\Services\Pattern\PatternBuilder;
use App\Support\Alterations;
use App\Support\Format;
use RuntimeException;

/**
 * بستن حلقه پرو: از آنچه خیاط روی تن مشتری دید تا الگوی اصلاح‌شده.
 *
 * هیچ‌جا مستقیم به هندسه دست نمی‌زنیم. اصلاح‌ها به اندازه بدن، آزادی و پارامتر
 * درفت ترجمه می‌شوند و الگو با همان ورودی تازه بازتولید می‌شود؛ یعنی همان مسیری
 * که الگو از اول از آن آمده. سود این کار دو چیز است: اصلاح روی همه قطعه‌ها
 * یک‌دست می‌نشیند، و چون بازتولید خودش نسخه ثبت می‌کند، هر پرو برگشت‌پذیر است.
 */
class AlterationService
{
    public function __construct(protected PatternBuilder $builder = new PatternBuilder) {}

    /**
     * پیش‌نمایش: بدون دست‌زدن به الگو، بگو چه چیزی عوض می‌شود.
     *
     * @return array{measurements: array, ease: array, params: array, applied: array<int, array<string, mixed>>}
     */
    public function preview(Pattern $pattern, array $adjustments): array
    {
        return Alterations::apply(
            $adjustments,
            $pattern->measurements ?? [],
            $pattern->ease ?? [],
            $pattern->params ?? [],
        );
    }

    /**
     * اعمال یک پرو روی الگوی پروژه.
     *
     * @throws RuntimeException اگر الگو قابل بازتولید نباشد یا اصلاحی در کار نباشد
     */
    public function apply(Fitting $fitting): Pattern
    {
        $pattern = $fitting->pattern ?? $fitting->project->pattern;

        if ($pattern === null) {
            throw new RuntimeException('این پروژه هنوز الگویی ندارد که اصلاح روی آن بنشیند.');
        }

        if ($fitting->isApplied()) {
            throw new RuntimeException('این پرو یک بار اعمال شده است؛ برای اصلاح دوباره یک پروی تازه ثبت کنید.');
        }

        $result = $this->preview($pattern, $fitting->adjustments ?? []);

        if ($result['applied'] === []) {
            throw new RuntimeException('هیچ اصلاح معتبری در این پرو ثبت نشده است.');
        }

        $pattern = $this->builder->regenerate($pattern, [
            'measurements' => $result['measurements'],
            'ease' => $result['ease'],
            'params' => $result['params'],
            'note' => 'پیش از اصلاح پروی '.$fitting->round.'ام',
        ]);

        $fitting->forceFill([
            'pattern_id' => $pattern->id,
            'applied' => $result['applied'],
            'applied_at' => now(),
        ])->save();

        return $pattern;
    }

    /**
     * شرح فارسی آنچه اعمال شد؛ برای نمایش و کارت فنی.
     *
     * @param  array<int, array<string, mixed>>  $applied
     * @return array<int, string>
     */
    public function describe(array $applied): array
    {
        $target = ['measurement' => 'اندازه بدن', 'ease' => 'آزادی', 'param' => 'پارامتر درفت'];

        return array_map(fn (array $row) => sprintf(
            '%s: %s از %s به %s (%s)',
            $row['label'],
            $row['value'] > 0 ? 'افزایش' : 'کاهش',
            Format::cm((float) $row['from']),
            Format::cm((float) $row['to']),
            $target[$row['target']] ?? $row['target'],
        ), $applied);
    }
}
