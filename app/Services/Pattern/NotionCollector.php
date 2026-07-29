<?php

namespace App\Services\Pattern;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Support\Format;
use App\Support\Jalali;

/**
 * جمع‌کردن «یراق» یک الگو: دکمه، زیپ، قزن، دکمه فشاری، کش، بند و مغزی.
 *
 * جای هر یراق روی خود قطعه ثبت می‌شود — دکمه به شکل drill و جادکمه، زیپ به شکل
 * خط نشانه و دو نشانه سر و ته — و در کنارش یک ردیف در `meta.notions` می‌نشیند که
 * می‌گوید چه چیزی، چند تا و چند سانتی‌متر. این کلاس همان ردیف‌ها را از همه
 * قطعه‌ها برمی‌دارد، هم‌نوع‌ها را با هم جمع می‌کند و تعداد برش هر قطعه را هم
 * حساب می‌کند (قطعه‌ای که دو بار بریده می‌شود، یراقش هم دو برابر است).
 *
 * خروجی یک فهرست مرتب است:
 *   ['type' => 'button', 'label' => 'دکمه جلو', 'count' => 6, 'length' => null, 'size' => 1.8]
 *
 * صورت مواد و کارت فنی از همین فهرست استفاده می‌کنند، پس عددی که به کاربر نشان
 * داده می‌شود همان چیزی است که روی الگو علامت خورده، نه یک حدس از روی نوع لباس.
 */
class NotionCollector
{
    /** برچسب فارسی هر نوع یراق. */
    public const LABELS = [
        'button' => 'دکمه',
        'zip' => 'زیپ',
        'hook' => 'قزن',
        'snap' => 'دکمه فشاری',
        'elastic' => 'کش',
        'cord' => 'بند',
        'eyelet' => 'مغزی (اویلت)',
        // لباس زیر یراق خودش را دارد: فنر زیر کاپ با سایز سفارش داده می‌شود و
        // حلقه و سگکِ بند با تعداد. زیر «یراق» بردنشان یعنی خریدار نمی‌داند چه بخرد.
        'underwire' => 'فنر کاپ',
        'ring' => 'حلقه و سگک بند',
        'buckle' => 'سگک',
        'other' => 'یراق',
    ];

    /** یراق‌هایی که با طول سفارش داده می‌شوند، نه با تعداد. */
    public const BY_LENGTH = ['zip', 'elastic', 'cord'];

    /**
     * فهرست یراق یک الگو.
     *
     * @return array<int, array{type: string, label: string, count: int, length: float|null, size: float|null}>
     */
    public function forPattern(?Pattern $pattern): array
    {
        if (! $pattern) {
            return [];
        }

        return $this->forPieces(
            $pattern->pieces->map(fn (PatternPiece $piece) => [
                'cut_quantity' => (int) $piece->cut_quantity,
                'meta' => $piece->meta ?? [],
                'drills' => $piece->drills ?? [],
            ])->all(),
        );
    }

    /**
     * فهرست یراق از روی آرایه قطعه‌ها (پیش از ذخیره در دیتابیس هم کار می‌کند).
     *
     * @param  array<int, array<string, mixed>>  $pieces
     * @return array<int, array{type: string, label: string, count: int, length: float|null, size: float|null}>
     */
    public function forPieces(array $pieces): array
    {
        $rows = [];

        foreach ($pieces as $piece) {
            // قطعه‌ای که دو بار بریده می‌شود دو برابر یراق می‌خواهد، ولی زیپِ
            // مرکز جلو یک عدد است هرچند تنه جلو دو بار بریده شود؛ پس ضریب فقط
            // روی یراقی می‌نشیند که خودِ ردیف آن را خواسته باشد.
            $cut = max(1, (int) ($piece['cut_quantity'] ?? 1));

            foreach ($piece['meta']['notions'] ?? [] as $notion) {
                $type = (string) ($notion['type'] ?? 'other');
                $perCut = (bool) ($notion['per_cut'] ?? false);
                $count = max(0, (int) ($notion['count'] ?? 1)) * ($perCut ? $cut : 1);

                if ($count === 0) {
                    continue;
                }

                $length = isset($notion['length']) ? round((float) $notion['length'], 1) : null;
                $size = isset($notion['size']) ? round((float) $notion['size'], 1) : null;
                $label = (string) ($notion['label'] ?? static::LABELS[$type] ?? static::LABELS['other']);

                $key = $type.'|'.$label.'|'.($length ?? '-').'|'.($size ?? '-');

                if (isset($rows[$key])) {
                    $rows[$key]['count'] += $count;

                    continue;
                }

                $rows[$key] = [
                    'type' => $type,
                    'label' => $label,
                    'count' => $count,
                    'length' => $length,
                    'size' => $size,
                ];
            }
        }

        $rows = array_values($rows);

        usort($rows, function (array $a, array $b) {
            $order = array_flip(array_keys(static::LABELS));

            return [$order[$a['type']] ?? 99, $a['label']] <=> [$order[$b['type']] ?? 99, $b['label']];
        });

        return $rows;
    }

    /** آیا این الگو چنین یراقی دارد؟ */
    public function has(?Pattern $pattern, string $type): bool
    {
        foreach ($this->forPattern($pattern) as $row) {
            if ($row['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * خلاصه یک‌خطی برای نمایش («۶ دکمه ۱٫۸ سانتی‌متری، زیپ ۵۵ سانتی‌متری»).
     */
    public function summary(?Pattern $pattern): string
    {
        $parts = [];

        foreach ($this->forPattern($pattern) as $row) {
            $parts[] = $this->describe($row);
        }

        return implode('، ', $parts);
    }

    /** توضیح یک ردیف یراق به فارسی. */
    public function describe(array $row): string
    {
        $text = $row['label'];

        if ($row['length'] !== null) {
            $text .= ' به طول '.Format::cm($row['length']);
        }

        if ($row['size'] !== null) {
            $text .= ' اندازه '.Format::cm($row['size']);
        }

        if ($row['count'] > 1) {
            $text = Jalali::digits((string) $row['count']).' عدد '.$text;
        }

        return $text;
    }
}
