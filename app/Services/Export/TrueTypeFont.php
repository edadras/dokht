<?php

namespace App\Services\Export;

use RuntimeException;

/**
 * خواننده کوچک فایل TrueType.
 *
 * فقط آنچه برای جاسازی قلم در PDF لازم است خوانده می‌شود:
 *   - head: unitsPerEm و کادر قلم
 *   - hhea/maxp/hmtx: پهنای پیشروی هر گلیف
 *   - cmap (قالب ۴ و ۱۲): نگاشت کدنقطه یونیکد ⇒ شماره گلیف
 *   - OS/2 و post: مقادیر لازم برای FontDescriptor
 *
 * واحد خروجی پهناها هزارم «em» است، چون PDF اندازه‌ها را در همین مقیاس می‌خواهد.
 * هیچ کتابخانه بیرونی و هیچ درخواست شبکه‌ای در کار نیست.
 */
class TrueTypeFont
{
    /** @var array<string, static> */
    protected static array $cache = [];

    protected string $data;

    /** @var array<string, array{0: int, 1: int}> tag ⇒ [offset, length] */
    protected array $tables = [];

    /** @var array<int, int> codepoint ⇒ glyph id */
    protected array $cmap = [];

    /** @var array<int, int> glyph id ⇒ advance width (font units) */
    protected array $advances = [];

    public int $unitsPerEm = 1000;

    public int $numGlyphs = 0;

    /** @var array{0: int, 1: int, 2: int, 3: int} */
    public array $bbox = [0, 0, 1000, 1000];

    public int $ascent = 800;

    public int $descent = -200;

    public int $capHeight = 700;

    public float $italicAngle = 0.0;

    public int $stemV = 80;

    public string $name = 'EmbeddedFont';

    public function __construct(string $data, string $name = 'EmbeddedFont')
    {
        $this->data = $data;
        $this->name = $name;

        $this->readDirectory();
        $this->readHead();
        $this->readMetrics();
        $this->readCmap();
        $this->readDescriptorValues();
    }

    /** خواندن یک فایل قلم با نگه‌داری در حافظه (هر فایل یک بار تجزیه می‌شود). */
    public static function fromFile(string $path): static
    {
        $key = $path.'@'.(string) @filemtime($path);

        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        $data = @file_get_contents($path);

        if ($data === false || strlen($data) < 12) {
            throw new RuntimeException('فایل قلم خوانده نشد: '.$path);
        }

        $name = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($path, PATHINFO_FILENAME)) ?: 'EmbeddedFont';

        return static::$cache[$key] = new static($data, $name);
    }

    /** قلم پیش‌فرض فارسی که در repository قرار دارد. */
    public static function persian(): ?static
    {
        $path = resource_path('fonts/Vazirmatn-Regular.ttf');

        if (! is_file($path)) {
            return null;
        }

        try {
            return static::fromFile($path);
        } catch (RuntimeException) {
            return null;
        }
    }

    /** داده خام فایل قلم، برای جاسازی در FontFile2. */
    public function data(): string
    {
        return $this->data;
    }

    /** شماره گلیف یک کدنقطه؛ اگر قلم آن را نداشته باشد null. */
    public function glyph(int $codepoint): ?int
    {
        return $this->cmap[$codepoint] ?? null;
    }

    public function hasGlyph(int $codepoint): bool
    {
        return isset($this->cmap[$codepoint]);
    }

    /** پهنای پیشروی گلیف در هزارم em. */
    public function advance(int $glyph): int
    {
        $units = $this->advances[$glyph] ?? ($this->advances[count($this->advances) - 1] ?? $this->unitsPerEm);

        return (int) round(($units * 1000) / max(1, $this->unitsPerEm));
    }

    /** پهنای یک رشته کدنقطه در هزارم em. */
    public function stringWidth(array $codepoints): int
    {
        $total = 0;

        foreach ($codepoints as $codepoint) {
            $glyph = $this->glyph($codepoint);

            if ($glyph !== null) {
                $total += $this->advance($glyph);
            }
        }

        return $total;
    }

    /** کادر قلم در هزارم em (برای FontDescriptor). */
    public function scaledBbox(): array
    {
        $factor = 1000 / max(1, $this->unitsPerEm);

        return array_map(fn (int $value) => (int) round($value * $factor), $this->bbox);
    }

    public function scaledAscent(): int
    {
        return (int) round(($this->ascent * 1000) / max(1, $this->unitsPerEm));
    }

    public function scaledDescent(): int
    {
        return (int) round(($this->descent * 1000) / max(1, $this->unitsPerEm));
    }

    public function scaledCapHeight(): int
    {
        return (int) round(($this->capHeight * 1000) / max(1, $this->unitsPerEm));
    }

    protected function readDirectory(): void
    {
        $header = unpack('Nversion/nnumTables', substr($this->data, 0, 6));

        if ($header === false) {
            throw new RuntimeException('سرآیند قلم خوانده نشد.');
        }

        if ($header['version'] === 0x74746366) { // 'ttcf' — مجموعه قلم
            $offset = unpack('N', substr($this->data, 12, 4));
            $base = $offset === false ? 0 : $offset[1];
            $header = unpack('Nversion/nnumTables', substr($this->data, $base, 6));
            $directory = $base + 12;
        } else {
            $directory = 12;
        }

        if ($header === false) {
            throw new RuntimeException('فهرست جدول‌های قلم خوانده نشد.');
        }

        for ($i = 0; $i < $header['numTables']; $i++) {
            $entry = substr($this->data, $directory + (16 * $i), 16);

            if (strlen($entry) < 16) {
                break;
            }

            $tag = substr($entry, 0, 4);
            $values = unpack('Noffset/Nlength', substr($entry, 8, 8));

            if ($values !== false) {
                $this->tables[$tag] = [$values['offset'], $values['length']];
            }
        }

        if (! isset($this->tables['head'])) {
            throw new RuntimeException('جدول head در قلم نیست.');
        }
    }

    protected function readHead(): void
    {
        $offset = $this->tables['head'][0];

        $this->unitsPerEm = max(16, $this->uint16($offset + 18));
        $this->bbox = [
            $this->int16($offset + 36),
            $this->int16($offset + 38),
            $this->int16($offset + 40),
            $this->int16($offset + 42),
        ];
    }

    protected function readMetrics(): void
    {
        $this->numGlyphs = isset($this->tables['maxp']) ? $this->uint16($this->tables['maxp'][0] + 4) : 0;

        if (! isset($this->tables['hhea'], $this->tables['hmtx'])) {
            return;
        }

        $hhea = $this->tables['hhea'][0];
        $this->ascent = $this->int16($hhea + 4);
        $this->descent = $this->int16($hhea + 6);
        $count = max(1, $this->uint16($hhea + 34));

        $hmtx = $this->tables['hmtx'][0];
        $last = $this->unitsPerEm;

        for ($glyph = 0; $glyph < $count; $glyph++) {
            $last = $this->uint16($hmtx + ($glyph * 4));
            $this->advances[$glyph] = $last;
        }

        for ($glyph = $count; $glyph < $this->numGlyphs; $glyph++) {
            $this->advances[$glyph] = $last;
        }
    }

    protected function readDescriptorValues(): void
    {
        if (isset($this->tables['OS/2'])) {
            $offset = $this->tables['OS/2'][0];
            $version = $this->uint16($offset);
            $this->capHeight = $version >= 2 && $this->tables['OS/2'][1] >= 90
                ? $this->int16($offset + 88)
                : (int) round($this->ascent * 0.72);

            if ($this->capHeight <= 0) {
                $this->capHeight = (int) round($this->ascent * 0.72);
            }
        } else {
            $this->capHeight = (int) round($this->ascent * 0.72);
        }

        if (isset($this->tables['post'])) {
            $offset = $this->tables['post'][0];
            $this->italicAngle = round($this->int32($offset + 4) / 65536, 2);
        }
    }

    protected function readCmap(): void
    {
        if (! isset($this->tables['cmap'])) {
            return;
        }

        $base = $this->tables['cmap'][0];
        $count = $this->uint16($base + 2);
        $best = null;
        $bestScore = -1;

        for ($i = 0; $i < $count; $i++) {
            $record = $base + 4 + ($i * 8);
            $platform = $this->uint16($record);
            $encoding = $this->uint16($record + 2);
            $offset = $this->int32($record + 4);

            // ترجیح: یونیکد کامل (۳/۱۰) بعد BMP ویندوز (۳/۱) بعد یونیکد عمومی (۰/*)
            $score = match (true) {
                $platform === 3 && $encoding === 10 => 4,
                $platform === 0 && $encoding >= 4 => 3,
                $platform === 3 && $encoding === 1 => 2,
                $platform === 0 => 1,
                default => 0,
            };

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $base + $offset;
            }
        }

        if ($best === null) {
            return;
        }

        match ($this->uint16($best)) {
            4 => $this->readCmapFormat4($best),
            12 => $this->readCmapFormat12($best),
            6 => $this->readCmapFormat6($best),
            default => null,
        };
    }

    protected function readCmapFormat4(int $offset): void
    {
        $segments = intdiv($this->uint16($offset + 6), 2);
        $endBase = $offset + 14;
        $startBase = $endBase + ($segments * 2) + 2;
        $deltaBase = $startBase + ($segments * 2);
        $rangeBase = $deltaBase + ($segments * 2);

        for ($segment = 0; $segment < $segments; $segment++) {
            $end = $this->uint16($endBase + ($segment * 2));
            $start = $this->uint16($startBase + ($segment * 2));
            $delta = $this->int16($deltaBase + ($segment * 2));
            $range = $this->uint16($rangeBase + ($segment * 2));

            if ($start > $end) {
                continue;
            }

            for ($code = $start; $code <= $end && $code !== 0xFFFF; $code++) {
                if ($range === 0) {
                    $glyph = ($code + $delta) & 0xFFFF;
                } else {
                    $index = $rangeBase + ($segment * 2) + $range + (($code - $start) * 2);

                    if ($index + 1 >= strlen($this->data)) {
                        continue;
                    }

                    $glyph = $this->uint16($index);

                    if ($glyph !== 0) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }

                if ($glyph !== 0) {
                    $this->cmap[$code] = $glyph;
                }
            }
        }
    }

    protected function readCmapFormat6(int $offset): void
    {
        $first = $this->uint16($offset + 6);
        $count = $this->uint16($offset + 8);

        for ($i = 0; $i < $count; $i++) {
            $glyph = $this->uint16($offset + 10 + ($i * 2));

            if ($glyph !== 0) {
                $this->cmap[$first + $i] = $glyph;
            }
        }
    }

    protected function readCmapFormat12(int $offset): void
    {
        $groups = $this->int32($offset + 12);

        for ($i = 0; $i < $groups; $i++) {
            $group = $offset + 16 + ($i * 12);
            $start = $this->int32($group);
            $end = $this->int32($group + 4);
            $glyph = $this->int32($group + 8);

            if ($end - $start > 0x10000) {
                $end = $start + 0x10000; // محافظت در برابر فایل خراب
            }

            for ($code = $start; $code <= $end; $code++) {
                $this->cmap[$code] = $glyph + ($code - $start);
            }
        }
    }

    protected function uint16(int $offset): int
    {
        $value = unpack('n', substr($this->data, $offset, 2));

        return $value === false ? 0 : $value[1];
    }

    protected function int16(int $offset): int
    {
        $value = $this->uint16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    protected function int32(int $offset): int
    {
        $value = unpack('N', substr($this->data, $offset, 4));

        if ($value === false) {
            return 0;
        }

        return $value[1] >= 0x80000000 ? $value[1] - 0x100000000 : $value[1];
    }
}
