<?php

namespace App\Services\Pattern\Generators\Concerns;

use App\Services\Pattern\Geometry;
use App\Services\Pattern\Transform\FullnessRecorder;

/**
 * ثبت چین روی لبه‌ای که با چین به قطعه دیگری دوخته می‌شود.
 *
 * چرا لازم است: پُری یک لبه اگر فقط در `meta.fullness` بنشیند، هیچ اندازه‌گیر
 * عمومی‌ای آن را نمی‌خواند و طول دوخته‌شده لبه به اندازه همان پُری غلط
 * درمی‌آید. قرارداد کاتالوگ `meta.gathers` است (FullnessRecorder) و این کمک‌کار
 * همان را می‌گذارد.
 *
 * یک نکته باریک هم دارد: پنل‌های پایین کاتالوگ چین را به شکل یک ردیف در
 * `pleats` با `edge = null` هم ثبت می‌کنند. اگر هر دو ثبت با هم بمانند، طول
 * دوخته‌شده دو بار کم می‌شود. پس شماره لبه واقعی روی همان ردیف نوشته می‌شود تا
 * فقط یک بار شمرده شود.
 */
trait RecordsGathers
{
    /**
     * ثبت چین روی لبه‌ای با برچسب خواسته‌شده.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    protected function recordGathers(array $piece, float $amount, string $label, string $tag = 'waist'): array
    {
        if ($amount <= 0.01) {
            return $piece;
        }

        $edges = Geometry::edgesWithTag($piece, $tag);

        if ($edges === []) {
            return $piece;
        }

        // چین روی بلندترین لبه از این برچسب می‌نشیند؛ لبه کوتاهِ اضافه جای دکمه
        // چین نمی‌خورد.
        $edge = $edges[0];

        foreach ($edges as $candidate) {
            if (Geometry::edgeLength($piece['outline'], $candidate) > Geometry::edgeLength($piece['outline'], $edge)) {
                $edge = $candidate;
            }
        }

        foreach ($piece['pleats'] ?? [] as $index => $pleat) {
            if (($pleat['edge'] ?? null) === null) {
                $piece['pleats'][$index]['edge'] = $edge;
            }
        }

        return FullnessRecorder::gathers($piece, $edge, $amount, ['label' => $label]);
    }
}
