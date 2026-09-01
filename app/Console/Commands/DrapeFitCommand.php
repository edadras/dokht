<?php

namespace App\Console\Commands;

use App\Models\PatternTemplate;
use App\Services\Simulation\DrapePayloadService;
use App\Support\Measurements;
use Symfony\Component\Process\Process;

/**
 * حلقهٔ اصلاحِ فیت: بدوز، اندازه بگیر، آزادی را درست کن، دوباره بدوز.
 *
 * همان کاری که خیاط در پرو می‌کند، این‌جا خودکار است: لباس در شبیه‌سازی دوخته
 * می‌شود، در چهار ناحیهٔ آزادی (سینه، کمر، باسن، بازو) کشش و فاصله تا تن
 * اندازه گرفته می‌شود، و هر جا پارچه زیرِ فشار است — یعنی الگو برای این تن
 * تنگ است — به آزادیِ همان ناحیه به اندازهٔ *کمبودِ اندازه‌گیری‌شده* اضافه
 * می‌شود، نه با حدس: کششِ ۱٫۰۸ در سینه یعنی دورِ سینهٔ الگو ۸٪ کم است.
 *
 * چشمِ حلقه tests/js/fit-report.mjs است که با همان خطِ لولهٔ نماگر می‌دوزد.
 *
 *   php artisan drape:fit --model=shirt_classic
 *   php artisan drape:fit --model=shirt_classic --tight   # از آزادیِ صفر شروع کن
 */
class DrapeFitCommand extends DrapeBenchCommand
{
    protected $signature = 'drape:fit
        {--model=shirt_classic : کلید مدل}
        {--size=40 : سایز بدن}
        {--rounds=3 : بیشینهٔ دورهای اصلاح}
        {--tight : شروع از آزادیِ صفر، برای دیدنِ خودِ حلقه}';

    protected $description = 'اصلاح خودکار آزادی الگو از روی فیت اندازه‌گیری‌شده در سه‌بعدی';

    /**
     * کمتر از این کسری نسبت به آزادیِ طراحی، اصلاح نمی‌خواهد (سانتی‌متر).
     *
     * دورِ پوشیده به‌خاطرِ چین همیشه کمی بیش از دورِ الگوست، پس کسریِ کوچک
     * یعنی «همان که خواسته بودیم».
     */
    protected const SLACK = 1.5;

    /** هر دور بیش از این به یک ناحیه اضافه نمی‌شود (سانتی‌متر) — اصلاحِ پله‌پله */
    protected const STEP_CAP = 8.0;

    public function handle(DrapePayloadService $service): int
    {
        $key = (string) $this->option('model');
        $body = Measurements::complete(Measurements::fromSize((string) $this->option('size')));
        $template = PatternTemplate::where('generator', $key)->first();
        $ease = $this->option('tight')
            ? ['bust' => 0, 'waist' => 0, 'hip' => 0, 'bicep' => 0]
            : ($template?->garmentType?->ease() ?? ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4]);
        $girths = [
            'bust' => (float) ($body['bust'] ?? 90),
            'waist' => (float) ($body['waist'] ?? 70),
            'hip' => (float) ($body['hip'] ?? 95),
            'bicep' => (float) ($body['bicep'] ?? 28),
        ];

        $rounds = max(1, (int) $this->option('rounds'));
        $before = [];
        $frozen = [];

        for ($round = 1; $round <= $rounds; $round++) {
            $report = $this->measure($service, $key, $body, $ease);

            if ($report === null) {
                $this->error('گزارشِ فیت خوانده نشد.');

                return self::FAILURE;
            }

            $targets = $template?->garmentType?->ease()
                ?? ['bust' => 6, 'waist' => 4, 'hip' => 6, 'bicep' => 4];

            $this->line('');
            $this->info("دور {$round} — آزادیِ فعلی: ".$this->row($ease));
            $this->table(
                ['ناحیه', 'دورِ تن', 'دورِ پوشیده', 'آزادیِ پوشیده', 'آزادیِ طراحی', 'داوری'],
                collect($report['zones'])->map(fn ($zone, $name) => [
                    $name,
                    $girths[$name] ?? '—',
                    $zone === null ? '—' : $zone['girth'],
                    $zone === null ? '—' : $zone['worn'],
                    $targets[$name] ?? '—',
                    $zone === null
                        ? 'پارچه‌ای در این تراز نیست'
                        : (($zone['worn'] ?? 99) < ($targets[$name] ?? 0) - static::SLACK ? 'تنگ' : 'خوب'),
                ])->all(),
            );

            $changed = false;

            foreach ($report['zones'] as $name => $zone) {
                if ($zone === null || $zone['worn'] === null || ! isset($targets[$name])) {
                    continue;
                }

                /*
                 * ناحیه‌ای که به اصلاح جواب نمی‌دهد، رها می‌شود.
                 *
                 * حلقه خودش این را پیدا کرد: مولّدِ پیراهنِ کلاسیک آزادیِ باسن
                 * را اصلاً به کار نمی‌برد، پس هرچه به آن اضافه شود دورِ پوشیده
                 * میلی‌متری هم تکان نمی‌خورد. ادامه دادن یعنی عددِ پیشنهادی را
                 * بی‌دلیل باد کردن؛ گفتنش صادقانه‌تر است.
                 */
                if (isset($before[$name]) && abs($zone['worn'] - $before[$name]) < 0.5) {
                    if (! isset($frozen[$name])) {
                        $frozen[$name] = true;
                        $ease[$name] = round($ease[$name] - $before[$name.'-added'], 1);
                        $this->warn("  {$name}: الگو به این آزادی پاسخ نداد (دورِ پوشیده تکان نخورد)؛"
                            .' اصلاحش پس گرفته شد و این ناحیه رها می‌شود.');
                    }

                    continue;
                }

                if (isset($frozen[$name])) {
                    continue;
                }

                /*
                 * کمبود، مستقیم از اندازه‌گیری: آزادیِ پوشیده باید دستِ کم به
                 * آزادیِ طراحیِ همین نوعِ لباس برسد. هرچه کم است، همان‌قدر به
                 * الگو اضافه می‌شود — نه یک ضریبِ سلیقه‌ای.
                 */
                $need = min(static::STEP_CAP, round($targets[$name] - $zone['worn'], 1));

                if ($need >= static::SLACK) {
                    $ease[$name] = round(($ease[$name] ?? 0) + $need, 1);
                    $before[$name] = $zone['worn'];
                    $before[$name.'-added'] = $need;
                    $this->line("  {$name}: آزادیِ پوشیده {$zone['worn']} از {$targets[$name]} کم است"
                        ." ⇒ آزادیِ الگو +{$need} (شد {$ease[$name]})");
                    $changed = true;
                } else {
                    unset($before[$name]);
                }
            }

            if (! $changed) {
                $this->info('هیچ ناحیه‌ای تنگ نیست؛ حلقه همین‌جا تمام است.');
                break;
            }
        }

        $this->line('');
        $this->info('آزادیِ پیشنهادی: '.$this->row($ease));

        return self::SUCCESS;
    }

    /** یک دور دوخت و اندازه‌گیری. @return array{zones: array<string, ?array>}|null */
    protected function measure(DrapePayloadService $service, string $key, array $body, array $ease): ?array
    {
        $pattern = $this->pattern($key, $body, $ease);
        $file = tempnam(sys_get_temp_dir(), 'fit-').'.json';

        file_put_contents($file, json_encode([
            'drape' => $service->payload($pattern),
            'avatar' => $body,
            'fabric' => [],
        ], JSON_UNESCAPED_UNICODE));

        $process = new Process(['node', base_path('tests/js/fit-report.mjs'), $file], base_path());
        $process->setTimeout(600);
        $process->run();
        @unlink($file);

        if (! $process->isSuccessful()) {
            $this->line($process->getErrorOutput());

            return null;
        }

        $out = json_decode(trim($process->getOutput()), true);

        return is_array($out) && isset($out['zones']) ? $out : null;
    }

    /** @param array<string, float> $ease */
    protected function row(array $ease): string
    {
        return collect($ease)->map(fn ($value, $name) => "{$name}={$value}")->implode('  ');
    }
}
