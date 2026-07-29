<?php

namespace App\Services\Pattern\Generators;

/**
 * اعلام «نقش دور» هر قطعه.
 *
 * هر اندازه‌گیرِ عمومی این سامانه باید بتواند بدون دانستن جزئیات مدل بفهمد کدام
 * قطعه در دور بدن حساب می‌شود و کدام نه:
 *
 *   shell    پوستهٔ لباس (پاچه، بالاتنه) — در جمع دور می‌آید
 *   lining   آستر
 *   sleeve   آستین
 *   trim     کمربند، نوار، جیب، حلقه، بند — در دور بدن حساب نمی‌شوند
 *
 * درفت‌های پایه‌ی قدیمی‌تر (مثلاً کمربند و نوار کشِ PantsBlock) این کلید را
 * نمی‌گذارند؛ این کمک‌کار آن‌ها را پس از ساخت پر می‌کند، بی‌آنکه لازم باشد به
 * درفت پایه دست بخورد.
 */
trait PieceRoles
{
    /**
     * پرکردن meta.girth_role هر قطعه‌ای که خودش اعلام نکرده است.
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, string>  $map  نقشِ هر «part»
     * @return array<int, array<string, mixed>>
     */
    protected function withGirthRoles(array $pieces, array $map = [], string $default = 'trim'): array
    {
        $map = array_merge([
            'front_leg' => 'shell',
            'back_leg' => 'shell',
            'front_bodice' => 'shell',
            'back_bodice' => 'shell',
            'sleeve' => 'sleeve',
            'lining' => 'lining',
        ], $map);

        foreach ($pieces as $index => $piece) {
            if (isset($piece['meta']['girth_role'])) {
                continue;
            }

            $part = (string) ($piece['meta']['part'] ?? '');
            $pieces[$index]['meta']['girth_role'] = $map[$part]
                ?? (($piece['layer'] ?? 'outer') === 'lining' ? 'lining' : $default);
        }

        return $pieces;
    }
}
