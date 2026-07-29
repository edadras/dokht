<?php

namespace App\Services\Export;

use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Services\Cutting\NestingService;
use App\Services\Pattern\NotionCollector;
use App\Support\Format;
use Throwable;

/**
 * صورت مواد لازم (BOM): پارچه، آستر، لایی و متعلقات با برآورد هزینه.
 *
 * اگر پروژه به سفارشی وصل باشد و آن سفارش ردیف مواد داشته باشد، همان ردیف‌های واقعی
 * مبنا قرار می‌گیرد؛ در غیر این صورت از روی چیدمان برش و اجزای لباس برآورد می‌شود.
 */
class BillOfMaterialsBuilder
{
    public function __construct(
        protected NestingService $nesting = new NestingService,
        protected NotionCollector $notions = new NotionCollector,
    ) {}

    /** یراقی که هر نوع در انبار با آن ثبت می‌شود. */
    protected const NOTION_KIND = [
        'button' => 'button',
        'zip' => 'zipper',
        'hook' => 'hook',
        'snap' => 'snap',
        'elastic' => 'elastic',
        'cord' => 'other',
        'eyelet' => 'other',
    ];

    public function build(Project $project): array
    {
        $order = $this->order($project);

        $rows = $order && $order->items->isNotEmpty()
            ? $this->rowsFromOrder($order)
            : $this->estimatedRows($project);

        $total = round(collect($rows)->sum('total'), 0);

        return [
            'from_order' => $order !== null && $order->items->isNotEmpty(),
            'order_id' => $order?->id,
            'order_code' => $order?->code,
            'currency' => 'تومان',
            'rows' => $rows,
            'total' => $total,
            'total_label' => Format::money($total),
            'notes' => $this->notes($project, $order),
        ];
    }

    protected function order(Project $project): ?Order
    {
        try {
            return $project->orders()->with(['items.fabric', 'items.material'])->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function rowsFromOrder(Order $order): array
    {
        return $order->items
            ->map(fn (OrderItem $item) => [
                'kind' => $item->kind,
                'kind_label' => $item->kindLabel(),
                'label' => $item->fabric?->displayName() ?? $item->material?->name ?? $item->description ?? 'ردیف سفارش',
                'description' => $item->description,
                'quantity' => round((float) $item->quantity, 2),
                'unit' => $item->unit ?: 'عدد',
                'unit_price' => (float) $item->unit_price,
                'total' => $item->total(),
                'source' => 'order',
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function estimatedRows(Project $project): array
    {
        $rows = [];
        $meters = $this->fabricMeters($project);
        $fabric = $project->fabric;

        $rows[] = [
            'kind' => 'fabric',
            'kind_label' => 'پارچه',
            'label' => $fabric?->displayName() ?? 'پارچه رو (انتخاب نشده)',
            'description' => $fabric
                ? sprintf('عرض %s سانتی‌متر • %s', (string) $fabric->effectiveWidth(), $fabric->surfacePatternLabel())
                : 'پارچه پروژه را انتخاب کنید تا برآورد دقیق شود.',
            'quantity' => $meters,
            'unit' => 'متر',
            'unit_price' => (float) ($fabric?->price_per_meter ?? 0),
            'total' => round($meters * (float) ($fabric?->price_per_meter ?? 0)),
            'source' => 'estimate',
        ];

        $lining = $project->liningFabric;
        $needsLining = $lining !== null
            || ($project->pattern?->pieces->contains(fn ($piece) => $piece->layer === 'lining') ?? false)
            || in_array('lining', $project->garmentType?->parts ?? [], true);

        if ($needsLining) {
            $liningMeters = round($meters * 0.9, 2);
            $price = (float) ($lining?->price_per_meter ?? $this->materialPrice('lining') ?? 0);

            $rows[] = [
                'kind' => 'lining',
                'kind_label' => 'آستر',
                'label' => $lining?->displayName() ?? 'آستر ساده هم‌رنگ',
                'description' => 'برآورد بر پایه مصرف پارچه رو',
                'quantity' => $liningMeters,
                'unit' => 'متر',
                'unit_price' => $price,
                'total' => round($liningMeters * $price),
                'source' => 'estimate',
            ];
        }

        $needsInterfacing = ($project->pattern?->pieces->contains(fn ($piece) => $piece->layer === 'interfacing') ?? false)
            || in_array('interfacing', $project->garmentType?->parts ?? [], true)
            || in_array('collar', $project->garmentType?->parts ?? [], true);

        if ($needsInterfacing) {
            $price = (float) ($this->materialPrice('interfacing') ?? 0);

            $rows[] = [
                'kind' => 'interfacing',
                'kind_label' => 'لایی',
                'label' => 'لایی چسب نازک',
                'description' => 'برای یقه، سجاف و جای دکمه',
                'quantity' => 0.5,
                'unit' => 'متر',
                'unit_price' => $price,
                'total' => round(0.5 * $price),
                'source' => 'estimate',
            ];
        }

        $threadPrice = (float) ($this->materialPrice('thread') ?? 0);

        $rows[] = [
            'kind' => 'thread',
            'kind_label' => 'نخ',
            'label' => 'نخ دوخت هم‌رنگ پارچه',
            'description' => 'یک قرقره برای یک لباس معمولاً کافی است',
            'quantity' => 1,
            'unit' => 'قرقره',
            'unit_price' => $threadPrice,
            'total' => round($threadPrice),
            'source' => 'estimate',
        ];

        $parts = $project->garmentType?->parts ?? [];

        // یراق واقعی الگو: دکمه، زیپ، قزن، دکمه فشاری و کش با همان تعداد و طولی
        // که روی خود قطعه‌ها علامت خورده است.
        $notions = $this->notions->forPattern($project->pattern);

        foreach ($notions as $notion) {
            $kind = static::NOTION_KIND[$notion['type']] ?? 'other';
            $price = (float) ($this->materialPrice($kind) ?? 0);

            // کش و بند را با متر می‌خرند، ولی زیپ را با عدد (در طول مشخص).
            $byMeter = in_array($notion['type'], ['elastic', 'cord'], true) && $notion['length'] !== null;
            $quantity = $byMeter
                ? round(($notion['length'] * $notion['count']) / 100, 2)
                : $notion['count'];

            $rows[] = [
                'kind' => $kind,
                'kind_label' => NotionCollector::LABELS[$notion['type']] ?? 'یراق',
                'label' => $notion['label'],
                'description' => $this->notionDescription($notion),
                'quantity' => $quantity,
                'unit' => $byMeter ? 'متر' : 'عدد',
                'unit_price' => $price,
                'total' => round($quantity * $price),
                'source' => 'pattern',
            ];
        }

        // اگر الگو هنوز ساخته نشده یا یراقی روی آن علامت نخورده، از روی اجزای
        // نوع لباس یک برآورد محافظه‌کارانه می‌دهیم تا فهرست خرید خالی نماند.
        $has = fn (string $type) => collect($notions)->contains(fn (array $row) => $row['type'] === $type);

        if (! $has('button') && ! $has('snap') && in_array('placket', $parts, true)) {
            $price = (float) ($this->materialPrice('button') ?? 0);

            $rows[] = [
                'kind' => 'button',
                'kind_label' => 'دکمه',
                'label' => 'دکمه هم‌رنگ',
                'description' => 'برآورد برای پاتلت جلو؛ با گذاشتن سبک بست روی الگو، تعداد دقیق می‌شود.',
                'quantity' => 6,
                'unit' => 'عدد',
                'unit_price' => $price,
                'total' => round(6 * $price),
                'source' => 'estimate',
            ];
        }

        // کمر کشی خودش بستِ لباس است و زیپ نمی‌خواهد
        if (! $has('zip') && ! $has('elastic') && array_intersect(['waistband', 'skirt_back', 'back_panel'], $parts) !== []) {
            $price = (float) ($this->materialPrice('zipper') ?? 0);

            $rows[] = [
                'kind' => 'zipper',
                'kind_label' => 'زیپ',
                'label' => 'زیپ مخفی ۲۰ سانتی‌متری',
                'description' => 'برآورد برای بسته شدن کمر یا پشت',
                'quantity' => 1,
                'unit' => 'عدد',
                'unit_price' => $price,
                'total' => round($price),
                'source' => 'estimate',
            ];
        }

        return $rows;
    }

    /** توضیح ردیف یراق: طول و اندازه‌ای که از روی الگو خوانده شده. */
    protected function notionDescription(array $notion): string
    {
        $parts = ['از روی نشانه‌های خود الگو'];

        if ($notion['length'] !== null) {
            $parts[] = 'طول '.Format::cm($notion['length']);
        }

        if ($notion['size'] !== null) {
            $parts[] = 'اندازه '.Format::cm($notion['size']);
        }

        return implode(' • ', $parts);
    }

    /** مصرف پارچه: از چیدمان ثبت‌شده، وگرنه با اجرای چیدمان روی الگو. */
    public function fabricMeters(Project $project): float
    {
        $layout = $project->latestCuttingLayout;

        if ($layout) {
            return round($layout->requiredMeters() * 1.05, 2);
        }

        if ($project->pattern) {
            try {
                $result = $this->nesting->nest($project->pattern, $project->fabric);

                // buy_meters آب‌رفتِ پارچه را در خود دارد؛ پنج درصد هم برای خطای
                // برش و کج‌شدن لبه پارچه روی آن می‌گذاریم.
                return round($result['buy_meters'] * 1.05, 2);
            } catch (Throwable) {
                // برآورد ممکن نشد
            }
        }

        return 0.0;
    }

    /** قیمت متعلقات از انبار کارگاه، اگر ثبت شده باشد. */
    protected function materialPrice(string $kind): ?float
    {
        try {
            $price = Material::query()->where('kind', $kind)->whereNotNull('price')->value('price');

            return $price === null ? null : (float) $price;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, string> */
    protected function notes(Project $project, ?Order $order): array
    {
        $notes = [];

        if ($order === null) {
            $notes[] = 'این صورت مواد برآوردی است؛ با ثبت سفارش و ردیف‌های آن، اعداد واقعی جای برآورد را می‌گیرد.';
        } elseif ($order->items->isEmpty()) {
            $notes[] = sprintf('سفارش %s ردیف موادی ندارد؛ اعداد زیر برآوردی است.', (string) $order->code);
        }

        if ($project->fabric === null) {
            $notes[] = 'پارچه پروژه انتخاب نشده است، پس قیمت پارچه صفر در نظر گرفته شده.';
        }

        if ($project->latestCuttingLayout === null && $project->pattern !== null) {
            $notes[] = 'برای دقت بیشتر، یک بار نقشه برش را بسازید تا مصرف پارچه واقعی حساب شود.';
        }

        $notes[] = 'در برآورد پارچه ۵٪ اضافه برای خطای برش در نظر گرفته شده است.';

        return $notes;
    }

    /** خروجی CSV صورت مواد. */
    public function csv(Project $project): string
    {
        $bom = $this->build($project);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['گروه', 'شرح', 'مقدار', 'واحد', 'قیمت واحد', 'جمع']);

        foreach ($bom['rows'] as $row) {
            fputcsv($handle, [
                $row['kind_label'],
                $row['label'],
                (string) $row['quantity'],
                $row['unit'],
                (string) $row['unit_price'],
                (string) $row['total'],
            ]);
        }

        fputcsv($handle, ['', 'جمع کل', '', '', '', (string) $bom['total']]);

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }
}
