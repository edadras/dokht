<?php

namespace App\Services\Simulation;

use App\Models\Simulation;
use Illuminate\Support\Facades\File;

/** صف و خروجی موتور رندر مستقل سرور. */
class ServerRenderService
{
    public function queue(Simulation $simulation, array $payload): void
    {
        $directory = storage_path('app/render-queue');
        File::ensureDirectoryExists($directory);

        $target = $directory.'/simulation-'.$simulation->id.'.json';
        $temporary = $target.'.tmp';
        File::put($temporary, json_encode([
            'id' => 'simulation-'.$simulation->id,
            'payload' => $payload,
            'requested_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        File::move($temporary, $target);
    }

    public function result(Simulation $simulation): array
    {
        $key = 'simulation-'.$simulation->id;
        $relative = 'renders/'.$key;
        $manifest = storage_path('app/public/'.$relative.'/manifest.json');

        if (File::exists($manifest)) {
            $data = json_decode(File::get($manifest), true) ?: [];
            $data['status'] = 'ready';
            $data['images'] = collect($data['images'] ?? [])->mapWithKeys(
                fn ($file, $view) => [$view => asset('storage/'.$relative.'/'.$file)]
            )->all();
            $data['model'] = isset($data['model']) ? asset('storage/'.$relative.'/'.$data['model']) : null;

            return $data;
        }

        if (File::exists(storage_path('app/render-failed/'.$key.'.json'))) {
            return ['status' => 'failed'];
        }

        return ['status' => 'pending'];
    }
}

