<?php

namespace App\Services\Simulation;

use App\Support\Measurements;
use Symfony\Component\Process\Process;

/**
 * آواتارِ GLB به اندازهٔ تنِ مشتری.
 *
 * آواتار یک تن است با اندازه‌های خودش؛ لباس باید روی تنِ *مشتری* دوخته شود.
 * پس برای هر مجموعه اندازه، یک بار آواتار با tests/js/avatar-body.mjs به همان
 * اندازه‌ها درمی‌آید (قد، دورها، سرشانه، بازو، پا) و به شکلِ GLBِ پخته‌شده و
 * جدولِ حلقه‌های بدن (body.json) کنارِ آواتارِ اصلی ذخیره می‌شود:
 *
 *   public/avatars/{name}/model.glb            آواتارِ اصلی (T-pose)
 *   public/avatars/{name}/fit/{hash}/model.glb آواتار به اندازهٔ همین تن، بازو آویزان
 *   public/avatars/{name}/fit/{hash}/body.json حلقه‌های همان تن برای حل‌کننده
 *
 * hash از خودِ اندازه‌هاست، پس دو مشتری با یک تن یک بار پخته می‌شوند و همان
 * مشتری با اندازه‌های تازه، آواتارِ تازه می‌گیرد.
 */
class AvatarFitService
{
    /** کلیدهایی که ابزارِ آواتار می‌فهمد؛ بقیهٔ اندازه‌ها در hash نمی‌آیند */
    protected const KEYS = [
        'height', 'bust', 'under_bust', 'waist', 'high_hip', 'hip', 'neck', 'shoulder_width',
        'arm_length', 'bicep', 'elbow', 'wrist', 'thigh', 'knee', 'ankle',
    ];

    /**
     * @param  array<string, mixed>  $measurements
     * @return array{url: string, body: array<string, mixed>}|null null یعنی آواتار نیست یا پخته نشد
     */
    public function fit(string $name, array $measurements): ?array
    {
        if (! preg_match('/^[a-z0-9_-]+$/i', $name)) {
            return null;
        }

        $source = public_path("avatars/{$name}/model.glb");

        if (! is_file($source)) {
            return null;
        }

        $wanted = $this->wanted($measurements);
        $hash = substr(sha1(json_encode($wanted)), 0, 12);
        $dir = public_path("avatars/{$name}/fit/{$hash}");
        $body = $dir.'/body.json';
        $model = $dir.'/model.glb';

        if (! is_file($body) || ! is_file($model)) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return null;
            }

            $file = $dir.'/measurements.json';

            file_put_contents($file, json_encode($wanted));

            $process = new Process(
                ['node', base_path('tests/js/avatar-body.mjs'), $source, $file, '--bake', $model],
                base_path(),
            );
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
                return null;
            }

            file_put_contents($body, $process->getOutput());
        }

        $rings = json_decode((string) file_get_contents($body), true);

        return is_array($rings) && isset($rings['torso'])
            ? ['url' => "/avatars/{$name}/fit/{$hash}/model.glb", 'body' => $rings]
            : null;
    }

    /**
     * اندازه‌های کامل‌شده، فقط کلیدهایی که ابزار می‌خواند، گرد به یک دهم.
     *
     * @param  array<string, mixed>  $measurements
     * @return array<string, float>
     */
    protected function wanted(array $measurements): array
    {
        $full = Measurements::complete($measurements);
        $out = [];

        foreach (static::KEYS as $key) {
            if (isset($full[$key]) && is_numeric($full[$key])) {
                $out[$key] = round((float) $full[$key], 1);
            }
        }

        ksort($out);

        return $out;
    }
}
