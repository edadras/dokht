<?php

namespace Tests\Feature;

use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_open_the_settings_page(): void
    {
        $this->actingAsWorkshopUser('owner');
        $this->get(route('workshop.edit'))->assertOk()->assertSee('تنظیمات کارگاه');
    }

    public function test_a_tailor_cannot_open_or_update_the_settings(): void
    {
        $this->actingAsWorkshopUser('tailor');

        $this->get(route('workshop.edit'))->assertForbidden();
        $this->patch(route('workshop.update'), $this->payload())->assertForbidden();
    }

    public function test_the_owner_can_update_the_workshop_identity(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->patch(route('workshop.update'), $this->payload([
            'name' => 'خیاطی گل‌نار',
            'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
            'city' => 'یزد',
            'address' => 'خیابان کاشانی، پلاک ۱۲',
            'currency' => 'تومان',
        ]))->assertRedirect()->assertSessionHas('status');

        $workshop = $this->workshop()->fresh();

        $this->assertSame('خیاطی گل‌نار', $workshop->name);
        $this->assertSame('09123456789', $workshop->phone);
        $this->assertSame('یزد', $workshop->city);
        $this->assertSame('تومان', $workshop->currency);
    }

    public function test_per_edge_seam_allowances_round_trip_through_the_workshop_defaults(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->patch(route('workshop.update'), $this->payload([
            'default_seam_allowance' => 1.2,
            'default_hem_allowance' => 4,
            'seam_allowances' => [
                'neck' => 0.5,
                'shoulder' => 1.3,
                'side' => 2,
                'armhole' => 0.9,
                'waist' => 1.1,
            ],
            'fabric_width' => 150,
            'order_prefix' => '۱۴۰۴-',
        ]))->assertRedirect();

        $allowances = $this->workshop()->fresh()->defaultSeamAllowances();

        $this->assertSame(1.2, (float) $allowances['default']);
        $this->assertSame(4.0, (float) $allowances['hem']);
        $this->assertSame(0.5, (float) $allowances['neck']);
        $this->assertSame(1.3, (float) $allowances['shoulder']);
        $this->assertSame(2.0, (float) $allowances['side']);
        $this->assertSame(0.9, (float) $allowances['armhole']);
        $this->assertSame(1.1, (float) $allowances['waist']);

        $this->assertSame(150.0, $this->workshop()->fresh()->defaultFabricWidth());
        $this->assertSame('۱۴۰۴-', $this->workshop()->fresh()->orderPrefix());
    }

    public function test_empty_edge_allowances_fall_back_to_the_default(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->patch(route('workshop.update'), $this->payload([
            'default_seam_allowance' => 1.5,
            'seam_allowances' => ['neck' => '', 'shoulder' => '', 'side' => '', 'armhole' => '', 'waist' => ''],
        ]))->assertRedirect();

        $allowances = $this->workshop()->fresh()->defaultSeamAllowances();

        $this->assertSame([], $this->workshop()->fresh()->setting('seam_allowances'));
        $this->assertSame(0.7, (float) $allowances['neck']);
    }

    public function test_a_logo_upload_is_stored_and_the_old_file_is_removed(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser('owner');

        $this->patch(route('workshop.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))->assertRedirect();

        $first = $this->workshop()->fresh()->logo_path;

        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith('workshops/', $first);

        $this->patch(route('workshop.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo-2.png', 200, 200),
        ]))->assertRedirect();

        $second = $this->workshop()->fresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_too_large_logo_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsWorkshopUser('owner');

        $this->patch(route('workshop.update'), $this->payload([
            'logo' => UploadedFile::fake()->create('logo.png', 3000, 'image/png'),
        ]))->assertSessionHasErrors('logo');

        $this->assertNull($this->workshop()->fresh()->logo_path);
    }

    public function test_the_settings_page_shows_the_workshop_summary(): void
    {
        $this->actingAsWorkshopUser('owner');

        $this->get(route('workshop.edit'))
            ->assertOk()
            ->assertSee('کارگاه من')
            ->assertSee('حذف کارگاه')
            ->assertSee('پشتیبانی');
    }

    public function test_a_workshop_from_another_account_is_untouched(): void
    {
        $other = Workshop::factory()->create(['name' => 'کارگاه دیگر']);

        $this->actingAsWorkshopUser('owner');
        $this->patch(route('workshop.update'), $this->payload(['name' => 'نام تازه']))->assertRedirect();

        $this->assertSame('کارگاه دیگر', $other->fresh()->name);
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'کارگاه آزمون',
            'default_seam_allowance' => 1,
            'default_hem_allowance' => 3,
        ], $overrides);
    }
}
