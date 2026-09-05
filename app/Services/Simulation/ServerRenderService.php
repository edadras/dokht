<?php

namespace App\Services\Simulation;

use App\Models\Pattern;
use App\Models\Simulation;
use Illuminate\Support\Facades\File;

/** صف و خروجی موتور رندر مستقل سرور. */
class ServerRenderService
{
    public function queue(Simulation $simulation, array $payload): void
    {
        $this->queueFor('simulation-'.$simulation->id, $payload);
    }

    /**
     * الگوهایی که هنوز داخل پروژه نیستند هم باید خروجی واقعی موتور سرور داشته
     * باشند. امضای payload مانع می‌شود هر بار باز شدن صفحه، رندر سنگین دوباره
     * وارد صف شود؛ با هر تغییر واقعی هندسه یا پارچه امضا عوض می‌شود.
     */
    public function ensurePattern(Pattern $pattern, array $payload): array
    {
        $key = 'pattern-'.$pattern->id;
        $signature = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $signatureDirectory = storage_path('app/render-signatures');
        $signaturePath = $signatureDirectory.'/'.$key.'.sha256';
        $recorded = File::exists($signaturePath) ? trim(File::get($signaturePath)) : null;
        $manifest = storage_path('app/public/renders/'.$key.'/manifest.json');
        $inFlight = File::exists(storage_path('app/render-queue/'.$key.'.json'))
            || File::exists(storage_path('app/render-processing/'.$key.'.json'));
        $failed = File::exists(storage_path('app/render-failed/'.$key.'.json'));

        if ($recorded !== $signature || (! File::exists($manifest) && ! $inFlight && ! $failed)) {
            File::ensureDirectoryExists($signatureDirectory);
            $this->queueFor($key, $payload);
            File::put($signaturePath, $signature);
        }

        return $this->resultFor($key);
    }

    public function patternResult(Pattern $pattern): array
    {
        return $this->resultFor('pattern-'.$pattern->id);
    }

    protected function queueFor(string $key, array $payload): void
    {
        $directory = storage_path('app/render-queue');
        File::ensureDirectoryExists($directory);

        // خطای اجرای قبلی نباید جلوی تلاش تازه را بگیرد.
        File::delete(storage_path('app/render-failed/'.$key.'.json'));
        // وجود manifest قدیمی نباید باعث شود رابط کاربری، رندر تازه را آماده
        // اعلام کند. تصاویر قبلی تا زمان جایگزینی به‌عنوان پیش‌نمایش می‌مانند.
        File::delete(storage_path('app/public/renders/'.$key.'/manifest.json'));

        $target = $directory.'/'.$key.'.json';
        $temporary = $target.'.tmp';
        File::put($temporary, json_encode([
            'id' => $key,
            'payload' => $payload,
            'requested_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        File::move($temporary, $target);
    }

    public function result(Simulation $simulation): array
    {
        return $this->resultFor('simulation-'.$simulation->id);
    }

    protected function resultFor(string $key): array
    {
        $relative = 'renders/'.$key;
        $directory = storage_path('app/public/'.$relative);
        $manifest = storage_path('app/public/'.$relative.'/manifest.json');
        $inFlight = File::exists(storage_path('app/render-queue/'.$key.'.json'))
            || File::exists(storage_path('app/render-processing/'.$key.'.json'));

        if (File::exists($manifest) && ! $inFlight) {
            $data = json_decode(File::get($manifest), true) ?: [];
            $version = File::lastModified($manifest);
            $data['status'] = 'ready';
            $data['images'] = collect($data['images'] ?? [])->mapWithKeys(
                fn ($file, $view) => [$view => asset('storage/'.$relative.'/'.$file).'?v='.$version]
            )->all();
            $data['model'] = isset($data['model'])
                ? asset('storage/'.$relative.'/'.$data['model']).'?v='.$version
                : null;
            // برگهٔ کنارِ همِ پنج نما با عنوان (اختیاری؛ رندرهای قدیمی ندارند)
            $data['sheet'] = ! empty($data['sheet'])
                ? asset('storage/'.$relative.'/'.$data['sheet']).'?v='.$version
                : null;

            return $data;
        }

        // موتور هر نما را جداگانه روی دیسک می‌نویسد. تا پیش از کامل‌شدن کل
        // بسته و manifest، همان خروجی‌های آماده را نشان می‌دهیم تا کاربر چند
        // دقیقه فقط پیام «در حال ساخت» نبیند.
        $files = [
            'front' => 'front.png',
            'side' => 'side.png',
            'back' => 'back.png',
            'water' => 'water.png',
            'airflow' => 'airflow.png',
        ];
        $images = collect($files)->filter(
            fn ($file) => File::exists($directory.'/'.$file)
        )->mapWithKeys(function ($file, $view) use ($directory, $relative) {
            return [$view => asset('storage/'.$relative.'/'.$file).'?v='.File::lastModified($directory.'/'.$file)];
        })->all();
        $modelPath = $directory.'/garment.glb';
        $sheetPath = $directory.'/sheet.png';
        $partial = [
            'status' => 'pending',
            'images' => $images,
            'model' => File::exists($modelPath)
                ? asset('storage/'.$relative.'/garment.glb').'?v='.File::lastModified($modelPath)
                : null,
            'sheet' => File::exists($sheetPath)
                ? asset('storage/'.$relative.'/sheet.png').'?v='.File::lastModified($sheetPath)
                : null,
        ];

        if ($inFlight || $images !== [] || $partial['model'] || $partial['sheet']) {
            return $partial;
        }

        if (File::exists(storage_path('app/render-failed/'.$key.'.json'))) {
            return ['status' => 'failed'];
        }

        return ['status' => 'pending'];
    }
}
