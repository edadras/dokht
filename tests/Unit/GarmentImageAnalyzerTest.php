<?php

namespace Tests\Unit;

use App\Services\Vision\GarmentClassifier;
use App\Services\Vision\GarmentImageAnalyzer;
use App\Services\Vision\SilhouetteFeatures;
use GdImage;
use PHPUnit\Framework\TestCase;

/**
 * آزمون تحلیل عکس.
 *
 * عکس‌ها همین‌جا با GD ساخته می‌شوند تا آزمون به هیچ فایل بیرونی وابسته نباشد و
 * دقیقاً معلوم باشد شکل ورودی چیست: ذوزنقه (دامن A)، مستطیل با دو پاچه (شلوار)،
 * ساعت‌شنی با سرشانه و یقه (بالاتنه قالب‌دار) و یک مربع بی‌نشانه.
 */
class GarmentImageAnalyzerTest extends TestCase
{
    /** @var array<int, string> */
    protected array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        $this->files = [];

        parent::tearDown();
    }

    protected function analyzer(): GarmentImageAnalyzer
    {
        return new GarmentImageAnalyzer;
    }

    protected function features(GdImage $image): SilhouetteFeatures
    {
        $path = tempnam(sys_get_temp_dir(), 'dokht-vision').'.png';
        $this->files[] = $path;

        imagepng($image, $path);
        imagedestroy($image);

        return $this->analyzer()->features($path);
    }

    /** بوم سفید با شکل تیره — همان چیزی که کاربر روی میز عکس می‌گیرد. */
    protected function canvas(int $width = 300, int $height = 420): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 248));

        return $image;
    }

    protected function ink(GdImage $image): int
    {
        return imagecolorallocate($image, 25, 25, 30);
    }

    /** دامن ذوزنقه‌ای: کمر باریک، لبه پایین بازتر. */
    protected function aLineSkirt(): GdImage
    {
        $image = $this->canvas();
        imagefilledpolygon($image, [110, 90, 190, 90, 219, 330, 81, 330], $this->ink($image));

        return $image;
    }

    /** شلوار: بالاتنه یک‌پارچه و پایین دو پاچه جدا. */
    protected function trousers(): GdImage
    {
        $image = $this->canvas();
        $ink = $this->ink($image);

        imagefilledpolygon($image, [110, 60, 190, 60, 193, 150, 107, 150], $ink);
        imagefilledpolygon($image, [108, 146, 147, 146, 141, 390, 111, 390], $ink);
        imagefilledpolygon($image, [153, 146, 192, 146, 189, 390, 159, 390], $ink);

        return $image;
    }

    /** بالاتنه قالب‌دار: سرشانه، گودی یقه، آستین کوتاه و کمر گرفته. */
    protected function fittedBodice(): GdImage
    {
        $image = $this->canvas();

        imagefilledpolygon($image, [
            95, 70, 120, 62, 135, 78, 165, 78, 180, 62, 205, 70,
            212, 120, 195, 130,
            188, 200, 178, 230,
            196, 300, 210, 380,
            90, 380, 104, 300, 122, 230, 112, 200, 105, 130, 88, 120,
        ], $this->ink($image));

        return $image;
    }

    /** مربع ساده: هیچ نشانه‌ای ندارد. */
    protected function plainRectangle(): GdImage
    {
        $image = $this->canvas();
        imagefilledrectangle($image, 90, 90, 210, 330, $this->ink($image));

        return $image;
    }

    public function test_background_is_separated_and_bounding_box_matches_the_shape(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dokht-vision').'.png';
        $this->files[] = $path;

        $image = $this->plainRectangle();
        imagepng($image, $path);
        imagedestroy($image);

        [$mask] = $this->analyzer()->silhouette($path);
        $bounds = $mask->bounds();

        $this->assertNotNull($bounds);

        // عکس ۳۰۰×۴۲۰ به ۱۴۳×۲۰۰ کوچک می‌شود؛ کادر شکل باید همان نسبت‌ها را نگه دارد
        $this->assertEqualsWithDelta(120 / 300, $bounds['width'] / $mask->width, 0.05);
        $this->assertEqualsWithDelta(240 / 420, $bounds['height'] / $mask->height, 0.05);
        $this->assertEqualsWithDelta(1.0, $mask->coverage() * ($mask->width * $mask->height) / ($bounds['width'] * $bounds['height']), 0.05);
    }

    public function test_width_profile_grows_downwards_for_a_trapezoid(): void
    {
        $features = $this->features($this->aLineSkirt());

        $this->assertCount(SilhouetteFeatures::LEVELS, $features->profile);
        $this->assertLessThan($features->profile[20], $features->profile[0]);
        $this->assertGreaterThan(1.3, $features->hemRatio);
        $this->assertSame(0.0, $features->splitRatio);
    }

    public function test_a_line_trapezoid_is_read_as_a_skirt(): void
    {
        $features = $this->features($this->aLineSkirt());
        $result = (new GarmentClassifier)->classify($features);

        $this->assertSame('bottom', $result['garment']['family']);
        $this->assertStringStartsWith('skirt_', $result['garment']['code']);
        $this->assertContains($result['silhouette']['value'], ['a_line', 'flared']);
        $this->assertGreaterThan(0.6, $result['confidence']);
    }

    public function test_two_legs_are_detected_and_read_as_trousers(): void
    {
        $features = $this->features($this->trousers());

        $this->assertGreaterThan(0.4, $features->splitRatio);
        $this->assertNotNull($features->splitStart);
        $this->assertGreaterThan(0.15, $features->splitStart);

        $result = (new GarmentClassifier)->classify($features);

        $this->assertContains($result['garment']['code'], ['pants', 'shorts', 'jumpsuit']);
        $this->assertGreaterThan(0.6, $result['confidence']);
    }

    public function test_fitted_bodice_shows_waist_pinch_neckline_and_sleeves(): void
    {
        $features = $this->features($this->fittedBodice());

        $this->assertGreaterThan(0.08, $features->waistPinch, 'کمر باید باریک‌تر از سینه و باسن دیده شود.');
        $this->assertGreaterThan(0.02, $features->neckDepth, 'گودی یقه باید پیدا شود.');
        $this->assertGreaterThan(1.2, $features->sleeveBump, 'برجستگی آستین باید پیدا شود.');
        $this->assertSame(0.0, $features->splitRatio);

        $result = (new GarmentClassifier)->classify($features);

        $this->assertContains($result['garment']['family'], ['top', 'one_piece', 'formal', 'outer']);
        $this->assertSame('fitted', $result['silhouette']['value']);
        $this->assertNotNull($result['neckline']['value']);
    }

    public function test_confidence_drops_on_a_featureless_rectangle(): void
    {
        $plain = (new GarmentClassifier)->classify($this->features($this->plainRectangle()));
        $skirt = (new GarmentClassifier)->classify($this->features($this->aLineSkirt()));

        $this->assertLessThan(0.45, $plain['confidence']);
        $this->assertLessThan($skirt['confidence'], $plain['confidence']);
        $this->assertLessThan(0.35, $plain['distinctiveness']);

        $warnings = implode(' ', $plain['warnings']);
        $this->assertStringContainsString('مستطیل ساده', $warnings);
        $this->assertStringContainsString('خودتان', $warnings);
    }

    public function test_transparent_png_uses_the_alpha_channel(): void
    {
        $image = imagecreatetruecolor(240, 320);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagefilledpolygon($image, [90, 40, 150, 40, 200, 280, 40, 280], imagecolorallocate($image, 120, 90, 200));

        $path = tempnam(sys_get_temp_dir(), 'dokht-vision').'.png';
        $this->files[] = $path;
        imagepng($image, $path);
        imagedestroy($image);

        [$mask, $notes] = $this->analyzer()->silhouette($path);

        $this->assertStringContainsString('شفاف', implode(' ', $notes));
        $this->assertGreaterThan(0.1, $mask->coverage());
    }

    public function test_otsu_finds_the_valley_between_two_groups(): void
    {
        $values = array_merge(array_fill(0, 500, 30), array_fill(0, 500, 200));

        $threshold = $this->analyzer()->otsu($values);

        // آستانه به معنی «هرچه از این بیشتر بود، پیش‌زمینه است» جدا می‌کند
        $this->assertGreaterThanOrEqual(30, $threshold);
        $this->assertLessThan(200, $threshold);
    }

    public function test_a_broken_file_is_rejected_with_a_persian_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dokht-vision');
        $this->files[] = $path;
        file_put_contents($path, 'این یک عکس نیست');

        $this->expectExceptionMessage('این فایل یک عکس معتبر نیست.');

        $this->analyzer()->silhouette($path);
    }
}
