<?php

namespace Tests\Unit;

use App\Services\Vision\GarmentClassifier;
use App\Services\Vision\GarmentImageAnalyzer;
use App\Services\Vision\SilhouetteFeatures;
use App\Services\Vision\SilhouetteOverlay;
use App\Services\Vision\SketchAnalyzer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * آزمون تحلیل طرح دستی.
 *
 * انتظار اصلی این است که همان شکل، چه از عکس بیاید و چه از قلم کاربر، به یک
 * نتیجه برسد؛ چون هر دو از یک استخراج ویژگی و یک دسته‌بند رد می‌شوند.
 */
class SketchAnalyzerTest extends TestCase
{
    protected function analyzer(): SketchAnalyzer
    {
        return new SketchAnalyzer;
    }

    /** @param  array<int, array{0: int, 1: int}>  $points */
    protected function polygon(array $points): array
    {
        return array_map(fn ($point) => ['x' => $point[0], 'y' => $point[1]], $points);
    }

    protected function aLineSkirt(): array
    {
        return $this->polygon([[110, 90], [190, 90], [219, 330], [81, 330]]);
    }

    protected function trousers(): array
    {
        return $this->polygon([
            [110, 60], [190, 60], [189, 390], [159, 390], [150, 150], [141, 390], [111, 390],
        ]);
    }

    protected function fittedBodice(): array
    {
        return $this->polygon([
            [95, 70], [120, 62], [135, 78], [165, 78], [180, 62], [205, 70],
            [212, 120], [195, 130], [188, 200], [178, 230], [196, 300], [210, 380],
            [90, 380], [104, 300], [122, 230], [112, 200], [105, 130], [88, 120],
        ]);
    }

    public function test_polygon_is_filled_into_a_mask_with_the_right_proportions(): void
    {
        [$mask, $notes] = $this->analyzer()->silhouette($this->aLineSkirt());
        $bounds = $mask->bounds();

        $this->assertNotNull($bounds);
        $this->assertEqualsWithDelta(240 / 138, $bounds['height'] / $bounds['width'], 0.1);
        $this->assertStringContainsString('نقطه‌های قلم', implode(' ', $notes));

        // شکل نباید به لبه قاب چسبیده باشد؛ حاشیه برای عملیات ریخت‌شناسی لازم است
        $this->assertSame([], $mask->touchedEdges());
    }

    public function test_a_line_sketch_is_read_as_a_flaring_skirt(): void
    {
        $features = $this->analyzer()->features($this->aLineSkirt());
        $result = (new GarmentClassifier)->classify($features);

        $this->assertSame('bottom', $result['garment']['family']);
        $this->assertStringStartsWith('skirt_', $result['garment']['code']);
        $this->assertGreaterThan(1.3, $features->hemRatio);
        $this->assertContains($result['silhouette']['value'], ['a_line', 'flared']);
    }

    public function test_sketched_legs_are_detected_as_trousers(): void
    {
        $features = $this->analyzer()->features($this->trousers());
        $result = (new GarmentClassifier)->classify($features);

        $this->assertGreaterThan(0.4, $features->splitRatio);
        $this->assertContains($result['garment']['code'], ['pants', 'shorts', 'jumpsuit']);
        $this->assertNull($result['neckline']['value'], 'پایین‌تنه یقه ندارد.');
    }

    public function test_sketch_and_photo_of_the_same_shape_agree(): void
    {
        $sketch = $this->analyzer()->features($this->fittedBodice());

        $image = imagecreatetruecolor(300, 420);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 248));
        $flat = [];

        foreach ($this->fittedBodice() as $point) {
            $flat[] = (int) $point['x'];
            $flat[] = (int) $point['y'];
        }

        imagefilledpolygon($image, $flat, imagecolorallocate($image, 20, 20, 25));
        $path = tempnam(sys_get_temp_dir(), 'dokht-sketch').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        $photo = (new GarmentImageAnalyzer)->features($path);
        @unlink($path);

        $this->assertEqualsWithDelta($photo->hemRatio, $sketch->hemRatio, 0.15);
        $this->assertEqualsWithDelta($photo->lengthRatio, $sketch->lengthRatio, 0.3);
        $this->assertEqualsWithDelta($photo->waistPinch, $sketch->waistPinch, 0.1);

        $this->assertSame(
            (new GarmentClassifier)->classify($photo)['garment']['family'],
            (new GarmentClassifier)->classify($sketch)['garment']['family'],
        );
    }

    public function test_multiple_strokes_are_accepted_and_the_largest_shape_wins(): void
    {
        $strokes = [
            $this->aLineSkirt(),
            $this->polygon([[10, 10], [20, 10], [20, 20], [10, 20]]),
        ];

        [$mask, $notes] = $this->analyzer()->silhouette($strokes);

        $this->assertStringContainsString('بزرگ‌ترین شکل', implode(' ', $notes));
        $this->assertGreaterThan(0, $mask->area());
    }

    public function test_an_empty_sketch_is_refused_in_persian(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('طرح خالی است');

        $this->analyzer()->silhouette([['x' => 1, 'y' => 1]]);
    }

    public function test_overlay_svg_draws_the_outline_and_the_measured_lines(): void
    {
        [$mask, $notes] = $this->analyzer()->silhouette($this->aLineSkirt());
        $features = SilhouetteFeatures::extract($mask, $notes);

        $svg = (new SilhouetteOverlay)->render($mask, $features, upperIsShoulder: false);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<path d="M', $svg);
        $this->assertStringContainsString('کمر', $svg);
        $this->assertStringContainsString('لبه', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'رونما باید SVG معتبر باشد.');
    }
}
