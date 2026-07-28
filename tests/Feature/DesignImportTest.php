<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GarmentType;
use App\Models\MeasurementSet;
use App\Models\Pattern;
use App\Models\PatternTemplate;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\PatternTemplateSeeder;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignImportTest extends TestCase
{
    use RefreshDatabase;

    protected function library(): void
    {
        $this->seed(GarmentTypeSeeder::class);
        $this->seed(PatternTemplateSeeder::class);
    }

    /** عکس آزمایشی: شکل تیره روی زمینه روشن، مثل عکس روی میز. */
    protected function image(callable $draw, int $width = 300, int $height = 420): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 248));
        $draw($image, imagecolorallocate($image, 25, 25, 30));

        $path = tempnam(sys_get_temp_dir(), 'dokht-upload').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'garment.png', 'image/png', null, true);
    }

    protected function skirtPhoto(): UploadedFile
    {
        return $this->image(fn (GdImage $image, int $ink) => imagefilledpolygon(
            $image,
            [110, 90, 190, 90, 219, 330, 81, 330],
            $ink,
        ));
    }

    /** @return array<int, array<int, array{x: int, y: int}>> */
    protected function skirtStrokes(): array
    {
        return [array_map(fn ($point) => ['x' => $point[0], 'y' => $point[1]], [
            [110, 90], [190, 90], [219, 330], [81, 330],
        ])];
    }

    public function test_page_shows_both_tabs_and_an_honest_explanation(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $this->get(route('design-import.create'))
            ->assertOk()
            ->assertSee('از روی عکس')
            ->assertSee('طرح دستی')
            ->assertSee('نقطه شروع')
            ->assertSee('هیچ سرویس هوش مصنوعی بیرونی')
            ->assertSee('هنوز طرحی خوانده نشده');
    }

    public function test_guests_cannot_reach_the_page(): void
    {
        $this->get(route('design-import.create'))->assertRedirect(route('login'));
    }

    public function test_photo_upload_is_analysed_and_the_result_is_shown(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser();
        $this->library();

        $response = $this->post(route('design-import.photo'), ['photo' => $this->skirtPhoto()]);

        $response->assertRedirect(route('design-import.create'));
        $proposal = $response->baseResponse->getSession()->get('designProposal');

        $this->assertSame('photo', $proposal['source']);
        $this->assertSame('bottom', $proposal['garment']['family']);
        $this->assertStringContainsString('<svg', $proposal['overlay_svg']);
        $this->assertNotNull($proposal['template']['id']);
        $this->assertCount(3, $proposal['alternatives']);

        // عکس روی دیسک عمومی ذخیره می‌شود تا کاربر رونما را روی خودش ببیند
        $this->assertNotNull($proposal['image_path']);
        Storage::disk('public')->assertExists($proposal['image_path']);

        $this->followingRedirects()
            ->post(route('design-import.photo'), ['photo' => $this->skirtPhoto()])
            ->assertOk()
            ->assertSee('چه چیزی اندازه گرفته شد')
            ->assertSee('چرا این تشخیص؟')
            ->assertSee('بساز الگو');
    }

    public function test_photo_upload_rejects_a_non_image(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser();
        $this->library();

        $this->post(route('design-import.photo'), [
            'photo' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_photo_upload_rejects_a_file_over_six_megabytes(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser();
        $this->library();

        $this->post(route('design-import.photo'), [
            'photo' => UploadedFile::fake()->image('big.jpg')->size(7000),
        ])->assertSessionHasErrors('photo');
    }

    public function test_photo_upload_requires_a_file(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('design-import.photo'), [])->assertSessionHasErrors('photo');
    }

    public function test_sensitivity_outside_the_allowed_range_is_refused(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser();
        $this->library();

        $this->post(route('design-import.photo'), [
            'photo' => $this->skirtPhoto(),
            'sensitivity' => 9,
        ])->assertSessionHasErrors('sensitivity');
    }

    public function test_sketch_points_produce_the_same_kind_of_proposal(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $response = $this->post(route('design-import.sketch'), [
            'strokes' => json_encode($this->skirtStrokes()),
        ]);

        $response->assertRedirect(route('design-import.create'));
        $proposal = $response->baseResponse->getSession()->get('designProposal');

        $this->assertSame('sketch', $proposal['source']);
        $this->assertSame('bottom', $proposal['garment']['family']);
        $this->assertNull($proposal['image_url']);
        $this->assertStringContainsString('<svg', $proposal['overlay_svg']);
        $this->assertNotEmpty($proposal['evidence']);

        // خط‌های کشیده‌شده برمی‌گردند تا بوم بعد از تحلیل خالی نشود
        $this->assertNotEmpty($response->baseResponse->getSession()->get('designStrokes'));
    }

    public function test_sketch_refuses_a_stroke_with_too_few_points(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('design-import.sketch'), [
            'strokes' => json_encode([[['x' => 1, 'y' => 2]]]),
        ])->assertSessionHasErrors(['strokes.0'], null, 'sketch');
    }

    public function test_sketch_refuses_a_broken_payload(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('design-import.sketch'), ['strokes' => 'یک چیز نامعتبر'])
            ->assertSessionHasErrors(['strokes'], null, 'sketch');

        $this->post(route('design-import.sketch'), [])->assertSessionHasErrors('strokes');
    }

    public function test_apply_creates_a_real_pattern_with_pieces(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $template = PatternTemplate::where('generator', 'skirt_a_line')->firstOrFail();
        $garment = GarmentType::where('code', 'skirt_gored')->firstOrFail();

        $response = $this->post(route('design-import.apply'), [
            'pattern_template_id' => $template->id,
            'garment_type_id' => $garment->id,
            'source' => 'sketch',
            'measurement_source' => 'size',
            'base_size' => '40',
            'name' => 'دامن از روی طرح',
            'detected' => 'دامن ترک',
            'confidence' => 0.72,
            'params' => ['length' => 62, 'flare' => 14],
        ]);

        $pattern = Pattern::latest('id')->firstOrFail();

        $response->assertRedirect(route('patterns.show', $pattern));

        $this->assertSame('دامن از روی طرح', $pattern->name);
        $this->assertSame($garment->id, $pattern->garment_type_id);
        $this->assertSame($template->id, $pattern->pattern_template_id);
        $this->assertGreaterThan(0, $pattern->pieces()->count());
        $this->assertSame(62.0, (float) $pattern->params['length']);
        $this->assertSame(14.0, (float) $pattern->params['flare']);
        $this->assertStringContainsString('طرح دستی', $pattern->notes);
        $this->assertStringContainsString('نقطه شروع', $pattern->notes);
        $this->assertNotEmpty($pattern->pieces()->first()->outline);
    }

    public function test_apply_uses_the_measurements_of_the_chosen_customer(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $customer = Customer::factory()->create(['workshop_id' => $this->workshop()->id]);
        $set = MeasurementSet::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'customer_id' => $customer->id,
            'is_default' => true,
            'values' => ['bust' => 104, 'waist' => 86, 'hip' => 110, 'shoulder_width' => 41],
        ]);

        $this->post(route('design-import.apply'), [
            'pattern_template_id' => PatternTemplate::where('generator', 'skirt_pencil')->firstOrFail()->id,
            'measurement_source' => 'customer',
            'customer_id' => $customer->id,
            'source' => 'photo',
        ]);

        $pattern = Pattern::latest('id')->firstOrFail();

        $this->assertSame($set->id, $pattern->measurement_set_id);
        $this->assertEqualsWithDelta(110, (float) $pattern->measurements['hip'], 0.5);
        $this->assertStringContainsString('عکس لباس', $pattern->notes);
    }

    public function test_apply_needs_a_known_template(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $this->post(route('design-import.apply'), ['pattern_template_id' => 999999])->assertNotFound();
        $this->post(route('design-import.apply'), [])->assertSessionHasErrors('pattern_template_id');
    }

    public function test_apply_ignores_parameters_the_generator_does_not_know(): void
    {
        $this->actingAsWorkshopUser();
        $this->library();

        $this->post(route('design-import.apply'), [
            'pattern_template_id' => PatternTemplate::where('generator', 'skirt_a_line')->firstOrFail()->id,
            'params' => ['length' => 5000, 'not_a_real_param' => 3],
        ]);

        $pattern = Pattern::latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('not_a_real_param', $pattern->params);
        $this->assertSame(120.0, (float) $pattern->params['length'], 'مقدار بیرون از بازه باید بریده شود.');
    }

    public function test_low_confidence_input_says_so_on_the_page(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser();
        $this->library();

        $plain = $this->image(fn (GdImage $image, int $ink) => imagefilledrectangle($image, 90, 90, 210, 330, $ink));

        $this->followingRedirects()
            ->post(route('design-import.photo'), ['photo' => $plain])
            ->assertOk()
            ->assertSee('اطمینان این تشخیص پایین است')
            ->assertSee('مستطیل ساده');
    }
}
