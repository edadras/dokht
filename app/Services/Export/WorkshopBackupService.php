<?php

namespace App\Services\Export;

use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Fitting;
use App\Models\Material;
use App\Models\Order;
use App\Models\Pattern;
use App\Models\Project;
use App\Models\Workshop;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * پشتیبان کامل یک کارگاه در یک فایل زیپ.
 *
 * دلیلش ساده است: دادهٔ این سامانه سرمایهٔ یک کسب‌وکار کوچک است — دفترچه اندازه
 * مشتری‌ها، الگوهایی که ماه‌ها رویشان کار شده و تاریخچه سفارش‌ها. تا امروز فقط
 * می‌شد «یک الگو» یا «یک پروژه» را بیرون برد؛ راهی برای برداشتن همه‌چیز نبود.
 *
 * خروجی دو بخش دارد:
 *   - JSON خام هر جدول، برای بازگرداندن یا بردن به جای دیگر
 *   - CSV خواندنی مشتری‌ها و سفارش‌ها، برای باز کردن در اکسل
 *
 * فایل‌های بارگذاری‌شده (نشان کارگاه، عکس پارچه) هم داخل زیپ کپی می‌شوند تا
 * پشتیبان واقعاً کامل باشد و به سرور وابسته نماند.
 */
class WorkshopBackupService
{
    /** جدول‌هایی که در پشتیبان می‌آیند و رابطه‌هایی که همراهشان بارگذاری می‌شود. */
    protected const TABLES = [
        'customers' => [Customer::class, ['measurementSets']],
        'fabrics' => [Fabric::class, []],
        'materials' => [Material::class, []],
        'patterns' => [Pattern::class, ['pieces']],
        'projects' => [Project::class, ['simulations', 'cuttingLayouts', 'techPacks']],
        'fittings' => [Fitting::class, []],
        'orders' => [Order::class, ['items', 'payments']],
    ];

    public function download(Workshop $workshop): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'dokht-backup-').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ساخت فایل پشتیبان ممکن نشد.');
        }

        $zip->addFromString('راهنما.txt', $this->readme($workshop));
        $zip->addFromString('کارگاه.json', $this->json($workshop->only([
            'name', 'slug', 'phone', 'city', 'address', 'settings', 'created_at',
        ])));

        $counts = [];

        foreach (static::TABLES as $name => [$class, $with]) {
            $rows = $this->rowsOf($class, $with);
            $counts[$name] = count($rows);
            $zip->addFromString('داده/'.$name.'.json', $this->json($rows));
        }

        $zip->addFromString('خواندنی/مشتری‌ها.csv', $this->customersCsv());
        $zip->addFromString('خواندنی/سفارش‌ها.csv', $this->ordersCsv());
        $zip->addFromString('فهرست.json', $this->json([
            'workshop' => $workshop->name,
            'taken_at' => now()->toIso8601String(),
            'taken_at_jalali' => Jalali::dateTime(now()),
            'counts' => $counts,
        ]));

        $this->addUploads($zip, $workshop);
        $zip->close();

        $name = 'پشتیبان-'.($workshop->slug ?: 'کارگاه').'-'.now()->format('Y-m-d').'.zip';

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $name, ['Content-Type' => 'application/zip']);
    }

    /**
     * ردیف‌های یک جدول کارگاه، با رابطه‌هایش.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rowsOf(string $class, array $with): array
    {
        /** @var class-string<Model> $class */
        return $class::query()
            ->when($with !== [], fn ($query) => $query->with($with))
            ->get()
            ->map(fn (Model $row) => $row->toArray())
            ->all();
    }

    protected function customersCsv(): string
    {
        $lines = ["\u{FEFF}نام,تلفن,یادداشت,آخرین اندازه‌گیری"];

        foreach (Customer::query()->with('measurementSets')->get() as $customer) {
            $last = $customer->measurementSets->sortByDesc('measured_on')->first();

            $lines[] = implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                [
                    $customer->name,
                    $customer->phone,
                    $customer->notes,
                    $last ? Jalali::date($last->measured_on) : '',
                ],
            ));
        }

        return implode("\n", $lines);
    }

    protected function ordersCsv(): string
    {
        $lines = ["\u{FEFF}کد,مشتری,وضعیت,مبلغ,تاریخ ثبت"];

        foreach (Order::query()->with('customer')->get() as $order) {
            $lines[] = implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                [
                    $order->code,
                    $order->customer?->name,
                    $order->statusLabel(),
                    $order->price,
                    Jalali::date($order->created_at),
                ],
            ));
        }

        return implode("\n", $lines);
    }

    /** نشان کارگاه و عکس پارچه‌ها. */
    protected function addUploads(ZipArchive $zip, Workshop $workshop): void
    {
        $disk = Storage::disk('public');
        $paths = array_filter(array_merge(
            [$workshop->logo_path ?? null],
            Fabric::query()->pluck('texture_path')->all(),
            Fabric::query()->pluck('normal_map_path')->all(),
            Fabric::query()->pluck('roughness_map_path')->all(),
        ));

        foreach (array_unique($paths) as $path) {
            if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
                continue;
            }

            $zip->addFromString('فایل‌ها/'.basename($path), $disk->get($path));
        }
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function readme(Workshop $workshop): string
    {
        return implode("\n", [
            'پشتیبان کارگاه «'.$workshop->name.'»',
            'گرفته‌شده در '.Jalali::dateTime(now()),
            '',
            'داده/            هر جدول به صورت JSON خام — برای بازگرداندن یا بردن به جای دیگر',
            'خواندنی/         مشتری‌ها و سفارش‌ها به CSV — با اکسل باز می‌شود',
            'فایل‌ها/          نشان کارگاه و عکس پارچه‌ها',
            'فهرست.json       تعداد ردیف هر جدول و زمان گرفتن پشتیبان',
            '',
            'این فایل همه دادهٔ کارگاه شماست؛ جایی امن نگهش دارید.',
        ]);
    }
}
