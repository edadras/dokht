<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Fabric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * پشتیبان کارگاه و ردّ کار.
 *
 * داده این سامانه سرمایه یک کسب‌وکار کوچک است: باید بشود همه‌اش را برداشت، و
 * باید معلوم باشد چه کسی چه کرد.
 */
class WorkshopBackupAndActivityTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------------
     |  پشتیبان
     * ------------------------------------------------------------------- */

    public function test_the_owner_downloads_a_zip_with_every_table_in_it(): void
    {
        $user = $this->actingAsWorkshopUser();
        Customer::factory()->count(3)->create(['workshop_id' => $user->workshop_id]);
        Fabric::factory()->count(2)->create(['workshop_id' => $user->workshop_id]);

        $response = $this->actingAs($user)->get(route('workshop.backup'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $path = tempnam(sys_get_temp_dir(), 'test-backup-').'.zip';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'فایل پشتیبان باید یک زیپ سالم باشد.');

        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        foreach (['راهنما.txt', 'کارگاه.json', 'فهرست.json', 'داده/customers.json', 'خواندنی/مشتری‌ها.csv'] as $expected) {
            $this->assertContains($expected, $names, "«{$expected}» باید در پشتیبان باشد.");
        }

        $manifest = json_decode($zip->getFromName('فهرست.json'), true);
        $this->assertSame(3, $manifest['counts']['customers']);
        $this->assertSame(2, $manifest['counts']['fabrics']);

        $zip->close();
        @unlink($path);
    }

    public function test_the_backup_never_reaches_into_another_workshop(): void
    {
        $other = $this->actingAsWorkshopUser();
        Customer::factory()->count(4)->create(['workshop_id' => $other->workshop_id]);
        auth()->logout();

        $mine = $this->actingAsWorkshopUser();
        Customer::factory()->count(1)->create(['workshop_id' => $mine->workshop_id]);

        $response = $this->actingAs($mine)->get(route('workshop.backup'));
        $path = tempnam(sys_get_temp_dir(), 'test-backup-').'.zip';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('فهرست.json'), true);
        $zip->close();
        @unlink($path);

        $this->assertSame(1, $manifest['counts']['customers'], 'پشتیبان فقط دادهٔ کارگاه خودش را دارد.');
        $this->assertNotSame($other->workshop_id, $mine->workshop_id);
    }

    public function test_a_tailor_may_not_take_the_backup(): void
    {
        $user = $this->actingAsWorkshopUser('tailor');

        $this->actingAs($user)->get(route('workshop.backup'))->assertForbidden();
    }

    /* ---------------------------------------------------------------------
     |  ردّ کار
     * ------------------------------------------------------------------- */

    public function test_creating_updating_and_deleting_leave_a_trail(): void
    {
        $user = $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['name' => 'زهرا کریمی', 'workshop_id' => $user->workshop_id]);
        $customer->update(['phone' => '09121234567']);
        $customer->delete();

        $trail = Activity::query()->where('subject_type', 'Customer')->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $trail->pluck('action')->all());
        $this->assertSame($user->id, $trail->first()->user_id);
        $this->assertStringContainsString('زهرا کریمی', $trail->first()->subject_label);
    }

    public function test_an_update_records_only_what_really_changed(): void
    {
        $user = $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['phone' => '09120000000', 'workshop_id' => $user->workshop_id]);
        $customer->update(['phone' => '09129999999']);

        $updated = Activity::query()->where('action', 'updated')->latest('id')->firstOrFail();

        $this->assertSame(['phone'], array_keys($updated->changes));
        $this->assertSame('09120000000', $updated->changes['phone']['from']);
        $this->assertSame('09129999999', $updated->changes['phone']['to']);
    }

    public function test_saving_without_a_real_change_writes_nothing(): void
    {
        $user = $this->actingAsWorkshopUser();

        $customer = Customer::factory()->create(['workshop_id' => $user->workshop_id]);
        $before = Activity::query()->count();

        $customer->update(['phone' => $customer->phone]);

        $this->assertSame($before, Activity::query()->count(), 'ذخیره بدون تغییر نباید ردیفی بسازد.');
    }

    public function test_the_trail_of_one_workshop_is_invisible_to_another(): void
    {
        $first = $this->actingAsWorkshopUser();
        Customer::factory()->create(['name' => 'مشتری کارگاه یک', 'workshop_id' => $first->workshop_id]);
        auth()->logout();

        $second = $this->actingAsWorkshopUser();
        Customer::factory()->create(['name' => 'مشتری کارگاه دو', 'workshop_id' => $second->workshop_id]);

        $labels = Activity::query()->pluck('subject_label')->all();

        $this->assertContains('مشتری کارگاه دو', $labels);
        $this->assertNotContains('مشتری کارگاه یک', $labels);

        $this->actingAs($second)->get(route('workshop.activity'))
            ->assertOk()
            ->assertSee('مشتری کارگاه دو')
            ->assertDontSee('مشتری کارگاه یک');
    }

    public function test_the_activity_page_can_be_filtered(): void
    {
        $user = $this->actingAsWorkshopUser();
        Customer::factory()->create(['name' => 'مشتری فیلتر', 'workshop_id' => $user->workshop_id]);
        Fabric::factory()->create(['name' => 'پارچه فیلتر', 'workshop_id' => $user->workshop_id]);

        $this->actingAs($user)->get(route('workshop.activity', ['subject' => 'Customer']))
            ->assertOk()
            ->assertSee('مشتری فیلتر')
            ->assertDontSee('پارچه فیلتر');
    }

    public function test_the_sentence_reads_like_persian(): void
    {
        $user = $this->actingAsWorkshopUser();
        $customer = Customer::factory()->create(['name' => 'نگار', 'workshop_id' => $user->workshop_id]);
        $customer->update(['phone' => '09120000001']);

        $rows = Activity::query()->orderBy('id')->get();

        $this->assertStringEndsWith('را ساخت', $rows[0]->sentence());
        $this->assertStringEndsWith('را ویرایش کرد', $rows[1]->sentence());
    }
}
