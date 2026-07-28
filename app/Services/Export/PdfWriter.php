<?php

namespace App\Services\Export;

use RuntimeException;

/**
 * نویسنده PDF نسخه ۱٫۴ — از صفر، بدون هیچ بسته بیرونی و بدون شبکه.
 *
 * ساختار فایلی که ساخته می‌شود:
 *
 *   %PDF-1.4
 *   ۱  Catalog        ⇒ به درخت صفحه‌ها اشاره می‌کند
 *   ۲  Pages          ⇒ فهرست Kids و شمار صفحه‌ها
 *   ۳..n  Page        ⇒ هر صفحه با MediaBox و Resources و Contents
 *   ...   Contents    ⇒ جریان محتوا (اختیاری فشرده با FlateDecode)
 *   ...   Font/Type0  ⇒ اگر قلم جاسازی شده باشد
 *   xref               ⇒ جدول ارجاع متقاطع با یک ورودی برای هر شیء
 *   trailer            ⇒ /Size /Root /ID
 *   startxref … %%EOF
 *
 * دستگاه مختصات همان دستگاه خود PDF است: مبدأ گوشه پایین-چپ، محور y به بالا،
 * و «واحد کاربر» برابر یک هفتاد و دوم اینچ. برای چاپ یک‌به‌یک، هر سانتی‌متر
 * دقیقاً ۷۲÷۲٫۵۴ = ۲۸٫۳۴۶ واحد است؛ متد cm() همین تبدیل را انجام می‌دهد.
 * هیچ ماتریس مقیاسی روی جریان محتوا اعمال نمی‌شود تا ضخامت خط‌ها هم دقیقاً
 * به «پوینت» بماند و اندازه‌ها در فایل خروجی مستقیم قابل بازبینی باشند.
 *
 * دستورهای مسیر پشتیبانی‌شده: m l c re h S s f B W n و q/Q و w و d برای
 * ضخامت و خط‌چین و RG/rg برای رنگ.
 */
class PdfWriter
{
    /** واحد کاربر PDF یک هفتاد و دوم اینچ است. */
    public const UNITS_PER_CM = 72 / 2.54;

    /** اندازه A4 به پوینت (۲۱٫۰ × ۲۹٫۷ سانتی‌متر). */
    public const A4_WIDTH = 595.28;

    public const A4_HEIGHT = 841.89;

    /** @var array<int, array{width: float, height: float, content: string}> */
    protected array $pages = [];

    protected ?string $current = null;

    protected float $pageWidth;

    protected float $pageHeight;

    protected ?TrueTypeFont $font = null;

    /** @var array<int, bool> گلیف‌هایی که واقعاً به کار رفته‌اند (برای آرایه /W) */
    protected array $usedGlyphs = [];

    /** @var array<int, int> glyph id ⇒ codepoint (برای ToUnicode) */
    protected array $glyphToUnicode = [];

    protected ArabicShaper $shaper;

    /** @var array<string, string> */
    protected array $info = [];

    public function __construct(
        float $pageWidth = self::A4_WIDTH,
        float $pageHeight = self::A4_HEIGHT,
        public bool $compress = true,
    ) {
        $this->pageWidth = $pageWidth;
        $this->pageHeight = $pageHeight;
        $this->shaper = new ArabicShaper;
    }

    /** سانتی‌متر ⇒ واحد کاربر PDF (پوینت). */
    public static function cm(float $value): float
    {
        return $value * self::UNITS_PER_CM;
    }

    /** قلم جاسازی‌شونده؛ اگر داده نشود متن با Helvetica استاندارد نوشته می‌شود. */
    public function useFont(?TrueTypeFont $font): static
    {
        $this->font = $font;

        return $this;
    }

    public function hasEmbeddedFont(): bool
    {
        return $this->font !== null;
    }

    public function shaper(): ArabicShaper
    {
        return $this->shaper;
    }

    /** اطلاعات سند (عنوان، سازنده …). */
    public function setInfo(array $info): static
    {
        $this->info = array_merge($this->info, $info);

        return $this;
    }

    public function pageCount(): int
    {
        return count($this->pages) + ($this->current === null ? 0 : 1);
    }

    public function width(): float
    {
        return $this->pageWidth;
    }

    public function height(): float
    {
        return $this->pageHeight;
    }

    /** آغاز یک صفحه تازه؛ صفحه پیشین بسته می‌شود. */
    public function addPage(): static
    {
        $this->flushPage();
        $this->current = '';

        return $this;
    }

    /** نوشتن یک دستور خام در جریان محتوا. */
    public function raw(string $operators): static
    {
        if ($this->current === null) {
            $this->addPage();
        }

        $this->current .= $operators."\n";

        return $this;
    }

    public function save(): static
    {
        return $this->raw('q');
    }

    public function restore(): static
    {
        return $this->raw('Q');
    }

    public function setLineWidth(float $width): static
    {
        return $this->raw($this->n($width).' w');
    }

    /** الگوی خط‌چین به پوینت؛ آرایه خالی یعنی خط پیوسته. */
    public function setDash(array $pattern = [], float $phase = 0.0): static
    {
        $values = implode(' ', array_map(fn ($value) => $this->n((float) $value), $pattern));

        return $this->raw('['.$values.'] '.$this->n($phase).' d');
    }

    public function setStrokeColor(float $r, float $g, float $b): static
    {
        return $this->raw($this->n($r).' '.$this->n($g).' '.$this->n($b).' RG');
    }

    public function setFillColor(float $r, float $g, float $b): static
    {
        return $this->raw($this->n($r).' '.$this->n($g).' '.$this->n($b).' rg');
    }

    public function setLineCap(int $cap): static
    {
        return $this->raw($cap.' J');
    }

    public function setLineJoin(int $join): static
    {
        return $this->raw($join.' j');
    }

    public function moveTo(float $x, float $y): static
    {
        return $this->raw($this->n($x).' '.$this->n($y).' m');
    }

    public function lineTo(float $x, float $y): static
    {
        return $this->raw($this->n($x).' '.$this->n($y).' l');
    }

    /** منحنی بزیه درجه‌سه. */
    public function curveTo(float $x1, float $y1, float $x2, float $y2, float $x, float $y): static
    {
        return $this->raw(
            $this->n($x1).' '.$this->n($y1).' '.$this->n($x2).' '.$this->n($y2).' '
            .$this->n($x).' '.$this->n($y).' c'
        );
    }

    /**
     * منحنی درجه‌دو (کوادراتیک) — PDF فقط درجه‌سه دارد، پس تبدیل می‌شود:
     * نقطه‌های کنترل درجه‌سه دو سومِ راه از دو سر به نقطه کنترل درجه‌دو هستند.
     */
    public function quadraticTo(float $fromX, float $fromY, float $cx, float $cy, float $x, float $y): static
    {
        return $this->curveTo(
            $fromX + ((2 / 3) * ($cx - $fromX)),
            $fromY + ((2 / 3) * ($cy - $fromY)),
            $x + ((2 / 3) * ($cx - $x)),
            $y + ((2 / 3) * ($cy - $y)),
            $x,
            $y,
        );
    }

    public function closePath(): static
    {
        return $this->raw('h');
    }

    public function rect(float $x, float $y, float $width, float $height): static
    {
        return $this->raw($this->n($x).' '.$this->n($y).' '.$this->n($width).' '.$this->n($height).' re');
    }

    public function stroke(): static
    {
        return $this->raw('S');
    }

    public function closeAndStroke(): static
    {
        return $this->raw('s');
    }

    public function fill(): static
    {
        return $this->raw('f');
    }

    public function fillAndStroke(): static
    {
        return $this->raw('B');
    }

    public function endPath(): static
    {
        return $this->raw('n');
    }

    /** بریدن به مسیر جاری (قاعده غیرصفر) و بستن مسیر. */
    public function clip(): static
    {
        return $this->raw('W n');
    }

    /** یک خط ساده. */
    public function line(float $x1, float $y1, float $x2, float $y2): static
    {
        return $this->moveTo($x1, $y1)->lineTo($x2, $y2)->stroke();
    }

    /**
     * چندضلعی از فهرست نقطه‌ها.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function polygon(array $points, bool $close = true, string $paint = 'S'): static
    {
        $points = array_values($points);

        if (count($points) < 2) {
            return $this;
        }

        $this->moveTo($points[0][0], $points[0][1]);

        for ($i = 1; $i < count($points); $i++) {
            $this->lineTo($points[$i][0], $points[$i][1]);
        }

        if ($close) {
            $this->closePath();
        }

        return $this->raw($paint);
    }

    /** دایره با چهار کمان بزیه. */
    public function circle(float $cx, float $cy, float $radius, string $paint = 'S'): static
    {
        $k = $radius * 0.5522847498;

        $this->moveTo($cx + $radius, $cy);
        $this->curveTo($cx + $radius, $cy + $k, $cx + $k, $cy + $radius, $cx, $cy + $radius);
        $this->curveTo($cx - $k, $cy + $radius, $cx - $radius, $cy + $k, $cx - $radius, $cy);
        $this->curveTo($cx - $radius, $cy - $k, $cx - $k, $cy - $radius, $cx, $cy - $radius);
        $this->curveTo($cx + $k, $cy - $radius, $cx + $radius, $cy - $k, $cx + $radius, $cy);

        return $this->raw($paint);
    }

    /**
     * نوشتن متن.
     *
     * متن فارسی پیش از نوشتن شکل‌دهی و راست‌به‌چپ می‌شود. اگر قلمی جاسازی نشده
     * باشد فقط بخش لاتین متن با Helvetica نوشته می‌شود (نگاه کنید به asciiOnly).
     *
     * @param  string  $align  چیدمان افقی نسبت به x: left | center | right
     */
    public function text(string $value, float $x, float $y, float $size, string $align = 'left'): static
    {
        if (trim($value) === '') {
            return $this;
        }

        if ($this->font === null) {
            return $this->simpleText($this->asciiOnly($value), $x, $y, $size, $align);
        }

        $codepoints = $this->shaper->visualCodepoints($value);
        $hex = '';
        $width = 0;

        foreach ($codepoints as $codepoint) {
            $glyph = $this->font->glyph($codepoint) ?? $this->font->glyph(0x003F) ?? 0;

            if ($glyph === 0) {
                continue;
            }

            $this->usedGlyphs[$glyph] = true;
            $this->glyphToUnicode[$glyph] = $codepoint;
            $hex .= sprintf('%04X', $glyph);
            $width += $this->font->advance($glyph);
        }

        if ($hex === '') {
            return $this;
        }

        $textWidth = ($width / 1000) * $size;
        $x = $this->alignedX($x, $textWidth, $align);

        return $this->raw('BT /F1 '.$this->n($size).' Tf '.$this->n($x).' '.$this->n($y).' Td <'.$hex.'> Tj ET');
    }

    /** پهنای متن به پوینت (برای چیدمان). */
    public function textWidth(string $value, float $size): float
    {
        if ($this->font === null) {
            return strlen($this->asciiOnly($value)) * $size * 0.5;
        }

        return ($this->font->stringWidth($this->shaper->visualCodepoints($value)) / 1000) * $size;
    }

    protected function simpleText(string $value, float $x, float $y, float $size, string $align): static
    {
        if ($value === '') {
            return $this;
        }

        $x = $this->alignedX($x, strlen($value) * $size * 0.5, $align);

        return $this->raw(
            'BT /F0 '.$this->n($size).' Tf '.$this->n($x).' '.$this->n($y).' Td ('.$this->escapeString($value).') Tj ET'
        );
    }

    protected function alignedX(float $x, float $width, string $align): float
    {
        return match ($align) {
            'center' => $x - ($width / 2),
            'right' => $x - $width,
            default => $x,
        };
    }

    /** متن جایگزین وقتی قلمی جاسازی نشده است: فقط نویسه‌های اَسکی. */
    protected function asciiOnly(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', '', $value)));
    }

    protected function flushPage(): void
    {
        if ($this->current !== null) {
            $this->pages[] = [
                'width' => $this->pageWidth,
                'height' => $this->pageHeight,
                'content' => $this->current,
            ];
            $this->current = null;
        }
    }

    /** ساخت فایل کامل PDF. */
    public function render(): string
    {
        $this->flushPage();

        if ($this->pages === []) {
            $this->pages[] = ['width' => $this->pageWidth, 'height' => $this->pageHeight, 'content' => ''];
        }

        $objects = [];   // شماره ⇒ بدنه شیء (بدون «n 0 obj» و «endobj»)
        $pageCount = count($this->pages);

        // ۱ Catalog، ۲ Pages، سپس برای هر صفحه دو شیء (Page و Contents)
        $firstPageObject = 3;
        $kids = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObject + ($i * 2)).' 0 R';
        }

        $fontObject = $firstPageObject + ($pageCount * 2);
        $fontResource = $this->font !== null
            ? '/F1 '.$fontObject.' 0 R'
            : '/F0 '.$fontObject.' 0 R';

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$pageCount} >>";

        foreach ($this->pages as $index => $page) {
            $pageObject = $firstPageObject + ($index * 2);
            $contentObject = $pageObject + 1;

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R '
                .'/MediaBox [0 0 '.$this->n($page['width']).' '.$this->n($page['height']).'] '
                .'/Resources << /ProcSet [/PDF /Text] /Font << '.$fontResource.' >> >> '
                .'/Contents '.$contentObject.' 0 R >>';

            $objects[$contentObject] = $this->streamObject($page['content']);
        }

        $next = $fontObject;

        if ($this->font === null) {
            $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
            $next = $fontObject + 1;
        } else {
            $descendant = $fontObject + 1;
            $descriptor = $fontObject + 2;
            $fontFile = $fontObject + 3;
            $toUnicode = $fontObject + 4;
            $next = $fontObject + 5;

            $objects[$fontObject] = '<< /Type /Font /Subtype /Type0 /BaseFont /'.$this->font->name.' '
                .'/Encoding /Identity-H /DescendantFonts ['.$descendant.' 0 R] /ToUnicode '.$toUnicode.' 0 R >>';

            $objects[$descendant] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /'.$this->font->name.' '
                .'/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> '
                .'/FontDescriptor '.$descriptor.' 0 R /DW 1000 /W '.$this->widthsArray().' '
                .'/CIDToGIDMap /Identity >>';

            [$x0, $y0, $x1, $y1] = $this->font->scaledBbox();

            $objects[$descriptor] = '<< /Type /FontDescriptor /FontName /'.$this->font->name.' '
                .'/Flags 4 /FontBBox ['.$x0.' '.$y0.' '.$x1.' '.$y1.'] '
                .'/ItalicAngle '.$this->n($this->font->italicAngle).' '
                .'/Ascent '.$this->font->scaledAscent().' /Descent '.$this->font->scaledDescent().' '
                .'/CapHeight '.$this->font->scaledCapHeight().' /StemV '.$this->font->stemV.' '
                .'/FontFile2 '.$fontFile.' 0 R >>';

            $objects[$fontFile] = $this->streamObject(
                $this->font->data(),
                '/Length1 '.strlen($this->font->data()),
            );

            $objects[$toUnicode] = $this->streamObject($this->toUnicodeCMap());
        }

        $objects[$next] = $this->infoObject();
        $infoObject = $next;

        return $this->assemble($objects, $infoObject);
    }

    /** چیدن شیءها، ساخت جدول xref و trailer. */
    protected function assemble(array $objects, int $infoObject): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $max = max(array_keys($objects));
        $xrefOffset = strlen($pdf);

        $xref = "xref\n0 ".($max + 1)."\n";
        $xref .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $max; $number++) {
            $xref .= isset($offsets[$number])
                ? sprintf("%010d 00000 n \n", $offsets[$number])
                : "0000000000 65535 f \n";
        }

        $id = strtoupper(substr(md5($pdf), 0, 32));

        $pdf .= $xref;
        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R /Info {$infoObject} 0 R "
            ."/ID [<{$id}> <{$id}>] >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    protected function infoObject(): string
    {
        $info = array_merge([
            'Producer' => 'Dokht pattern studio',
            'Creator' => 'Dokht PdfWriter',
        ], $this->info);

        $body = '<<';

        foreach ($info as $key => $value) {
            $body .= ' /'.$key.' '.$this->utf16String((string) $value);
        }

        return $body.' /CreationDate (D:'.date('YmdHis').'Z) >>';
    }

    /** رشته متنی PDF به شکل UTF-16BE با علامت ترتیب بایت (برای فارسی). */
    protected function utf16String(string $value): string
    {
        $codepoints = $this->shaper->codepoints($value);
        $hex = 'FEFF';

        foreach ($codepoints as $codepoint) {
            if ($codepoint > 0xFFFF) {
                $codepoint -= 0x10000;
                $hex .= sprintf('%04X%04X', 0xD800 + ($codepoint >> 10), 0xDC00 + ($codepoint & 0x3FF));

                continue;
            }

            $hex .= sprintf('%04X', $codepoint);
        }

        return '<'.$hex.'>';
    }

    /** آرایه /W برای پهنای گلیف‌های به‌کاررفته. */
    protected function widthsArray(): string
    {
        if ($this->font === null || $this->usedGlyphs === []) {
            return '[]';
        }

        $glyphs = array_keys($this->usedGlyphs);
        sort($glyphs);

        $parts = [];
        $start = null;
        $previous = null;
        $widths = [];

        foreach ($glyphs as $glyph) {
            if ($previous !== null && $glyph !== $previous + 1) {
                $parts[] = $start.' ['.implode(' ', $widths).']';
                $widths = [];
                $start = null;
            }

            $start ??= $glyph;
            $widths[] = $this->font->advance($glyph);
            $previous = $glyph;
        }

        if ($widths !== []) {
            $parts[] = $start.' ['.implode(' ', $widths).']';
        }

        return '['.implode(' ', $parts).']';
    }

    /** نگاشت گلیف ⇒ یونیکد تا متن PDF قابل جست‌وجو و کپی باشد. */
    protected function toUnicodeCMap(): string
    {
        $entries = $this->glyphToUnicode;
        ksort($entries);

        $lines = [];

        foreach (array_chunk($entries, 100, true) as $chunk) {
            $body = '';

            foreach ($chunk as $glyph => $codepoint) {
                $body .= sprintf("<%04X> <%04X>\n", $glyph, $codepoint);
            }

            $lines[] = count($chunk)." beginbfchar\n".$body.'endbfchar';
        }

        return "/CIDInit /ProcSet findresource begin\n"
            ."12 dict begin\nbegincmap\n"
            ."/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            ."/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            ."1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            .implode("\n", $lines)."\n"
            ."endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    }

    /** یک شیء جریان، در صورت امکان فشرده با FlateDecode. */
    protected function streamObject(string $data, string $extra = ''): string
    {
        $filter = '';

        if ($this->compress && function_exists('gzcompress')) {
            $compressed = gzcompress($data, 6);

            if ($compressed !== false && strlen($compressed) < strlen($data)) {
                $data = $compressed;
                $filter = '/Filter /FlateDecode ';
            }
        }

        return '<< '.$filter.($extra === '' ? '' : $extra.' ').'/Length '.strlen($data)." >>\nstream\n".$data."\nendstream";
    }

    /** گریز نویسه‌های ویژه در رشته‌های متنی PDF. */
    protected function escapeString(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '\\r', '\\n'], $value);
    }

    /**
     * عدد در جریان محتوا: حداکثر دو رقم اعشار، بدون نماد نمایی.
     *
     * دو رقم اعشار در واحد پوینت یعنی دقت بهتر از یک صدم میلی‌متر، که برای
     * چاپ ۱:۱ الگو بیش از کافی است و فایل را هم کوچک نگه می‌دارد.
     */
    protected function n(float|int $value): string
    {
        if (! is_finite((float) $value)) {
            throw new RuntimeException('مختصات نامعتبر در جریان محتوای PDF.');
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' || $formatted === '-0' ? '0' : $formatted;
    }
}
