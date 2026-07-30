<?php

namespace App\Console\Commands;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Services\Pattern\GeneratorRegistry;
use App\Services\Simulation\DrapePayloadService;
use App\Support\Measurements;
use Illuminate\Console\Command;

/**
 * بستهٔ دوختِ چند مدل را روی دیسک می‌نویسد تا سنجهٔ مرورگری بتواند بسنجدشان.
 *
 * چرا این هست: کیفیتِ دوختِ سه‌بعدی را نمی‌شود با چشم قضاوت کرد. هر تغییری در
 * چیدن و دوخت روی یک لباس بهتر و روی لباس دیگر بدتر جواب می‌دهد؛ سه بار همین
 * اتفاق افتاد و هر بار با نگاه کردن به یک عکس، «درست شد» به‌نظر می‌رسید.
 * راهش این است: چند لباسِ عمداً متفاوت، و چهار عددِ اندازه‌گرفته‌شده.
 *
 *   php artisan drape:bench            # هشت مدل پیش‌فرض
 *   php artisan drape:bench --model=blazer --model=skirt_gathered
 *   npm run bench:drape                # سنجش خروجی در مرورگر (node)
 */
class DrapeBenchCommand extends Command
{
    protected $signature = 'drape:bench
        {--model=* : کلید مدل‌ها؛ خالی یعنی فهرست پیش‌فرض}
        {--size=40 : سایز بدن}
        {--out=storage/app/drape-bench : پوشهٔ خروجی}';

    protected $description = 'نوشتن بستهٔ دوخت سه‌بعدی چند مدل برای سنجش';

    /**
     * لباس‌های پیش‌فرض: عمداً از هر خانواده یکی، با سختیِ متفاوت.
     *
     * پیراهن یقه و آستین دارد، کت آستین دوتکه، ترنچ‌کت قطعهٔ زیاد، لباس عروس
     * ساسون، دامن کلوش کمربندِ چنددرزه، چیپائو بستِ اریب، پیراهن راپ هم‌پوشانی.
     */
    protected const DEFAULT_MODELS = [
        'shirt_classic', 'blazer', 'suit_jacket', 'coat_trench',
        'bridal_sheath', 'dress_wrap', 'trad_qipao', 'skirt_circle_full',
    ];

    public function handle(DrapePayloadService $service): int
    {
        $models = $this->option('model') ?: static::DEFAULT_MODELS;
        $body = Measurements::complete(Measurements::fromSize((string) $this->option('size')));
        $out = base_path((string) $this->option('out'));

        if (! is_dir($out) && ! mkdir($out, 0755, true) && ! is_dir($out)) {
            $this->error("پوشهٔ «{$out}» ساخته نشد.");

            return self::FAILURE;
        }

        $written = 0;

        foreach ($models as $key) {
            if (! GeneratorRegistry::has($key)) {
                $this->warn("«{$key}» در کاتالوگ نیست.");

                continue;
            }

            $pattern = $this->pattern($key, $body);

            file_put_contents($out.'/p-'.$key.'.json', json_encode([
                'drape' => $service->payload($pattern),
                'avatar' => $body,
                'fabric' => [],
            ], JSON_UNESCAPED_UNICODE));

            $written++;
            $this->line("  {$key}");
        }

        $this->info($written.' بسته در '.$out.' نوشته شد. سنجش: npm run bench:drape');

        return self::SUCCESS;
    }

    /** الگوی در حافظه از یک مدل کاتالوگ؛ این فرمان به پایگاه داده کاری ندارد. */
    protected function pattern(string $key, array $body): Pattern
    {
        $generator = GeneratorRegistry::make($key);
        $pieces = $generator->generate($body, [], $generator->defaultParams());

        $models = collect($pieces)->map(function (array $piece, int $index) {
            $model = new PatternPiece;
            $model->code = (string) ($piece['code'] ?? 'piece-'.$index);
            $model->name = (string) ($piece['name'] ?? '');
            $model->outline = $piece['outline'];
            $model->meta = $piece['meta'] ?? [];
            $model->darts = $piece['darts'] ?? [];
            $model->layer = (string) ($piece['layer'] ?? 'outer');
            $model->cut_quantity = (int) ($piece['cut_quantity'] ?? 1);
            $model->on_fold = (bool) ($piece['on_fold'] ?? false);
            $model->mirror = (bool) ($piece['mirror'] ?? false);

            return $model;
        });

        $pattern = new Pattern(['name' => $key, 'measurements' => $body]);
        $pattern->setRelation('pieces', $models);

        return $pattern;
    }
}
