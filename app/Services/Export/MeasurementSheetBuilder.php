<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Support\Jalali;
use App\Support\Measurements;

/**
 * برگه اندازه: همان چیزی که خیاط کنار چرخ می‌گذارد.
 *
 * اندازه‌های ثبت‌شده مشتری مبنا است؛ اگر اندازه‌ای وارد نشده باشد از روی اندازه‌های
 * اصلی تخمین زده می‌شود و در برگه با نشانه «تخمینی» مشخص می‌شود.
 */
class MeasurementSheetBuilder
{
    public function build(Project $project): array
    {
        [$values, $source] = $this->source($project);

        $completed = Measurements::complete($values);
        $rows = [];

        foreach (Measurements::FIELDS as $key => $field) {
            $rows[] = [
                'key' => $key,
                'label' => $field['label'],
                'value' => isset($completed[$key]) ? round((float) $completed[$key], 1) : null,
                'unit' => 'سانتی‌متر',
                'essential' => (bool) ($field['essential'] ?? false),
                'derived' => ! array_key_exists($key, $values),
            ];
        }

        $ease = $project->effectiveEase();

        return [
            'title' => 'برگه اندازه — '.($project->customer?->name ?? $project->name),
            'customer' => $project->customer?->name,
            'phone' => $project->customer?->phone,
            'project' => $project->name,
            'size' => $project->size ?: ($project->measurementSet?->base_size ?? Measurements::guessSize($values)),
            'unit' => 'سانتی‌متر',
            'source' => $source,
            'source_label' => match ($source) {
                'measurement_set' => 'اندازه‌های ثبت‌شده مشتری',
                'pattern' => 'اندازه‌های ذخیره‌شده در الگو',
                default => 'جدول سایز استاندارد',
            },
            'date_jalali' => Jalali::date(now()),
            'rows' => $rows,
            'essential_rows' => array_values(array_filter($rows, fn ($row) => $row['essential'])),
            'ease' => collect($ease)
                ->map(fn ($value, $key) => [
                    'key' => $key,
                    'label' => Measurements::label($key),
                    'value' => round((float) $value, 1),
                ])
                ->values()
                ->all(),
            'notes' => $project->measurementSet?->notes,
        ];
    }

    /** ردیف‌های آماده برای فایل CSV. */
    public function csv(Project $project): string
    {
        $sheet = $this->build($project);

        $lines = [
            ['اندازه', 'مقدار (سانتی‌متر)', 'وضعیت'],
        ];

        foreach ($sheet['rows'] as $row) {
            $lines[] = [
                $row['label'],
                $row['value'] === null ? '' : (string) $row['value'],
                $row['derived'] ? 'تخمینی' : 'اندازه‌گیری‌شده',
            ];
        }

        $lines[] = ['', '', ''];
        $lines[] = ['مشتری', (string) ($sheet['customer'] ?? '—'), ''];
        $lines[] = ['سایز', (string) ($sheet['size'] ?? '—'), ''];
        $lines[] = ['تاریخ', $sheet['date_jalali'], ''];

        $handle = fopen('php://temp', 'r+');

        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        // نویسه BOM تا اکسل فارسی را درست باز کند
        return "\xEF\xBB\xBF".$csv;
    }

    /** @return array{0: array<string, float>, 1: string} */
    protected function source(Project $project): array
    {
        $set = $project->measurementSet;

        if ($set && ! empty($set->values)) {
            return [Measurements::clean($set->values), 'measurement_set'];
        }

        $pattern = $project->pattern;

        if ($pattern && ! empty($pattern->measurements)) {
            return [Measurements::clean($pattern->measurements), 'pattern'];
        }

        $size = $project->size ?: '40';

        return [Measurements::clean(Measurements::SIZE_TABLE[$size] ?? Measurements::SIZE_TABLE['40']), 'size_table'];
    }
}
