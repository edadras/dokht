<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_an_empty_state(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('materials.index'))->assertOk()->assertSee('هنوز متعلقاتی ثبت نکرده‌اید');
    }

    public function test_materials_are_grouped_by_kind(): void
    {
        $this->actingAsWorkshopUser();

        Material::factory()->ofKind('thread')->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'نخ مشکی',
        ]);

        Material::factory()->ofKind('zipper')->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'زیپ مخفی',
        ]);

        $this->get(route('materials.index'))
            ->assertOk()
            ->assertSee('نخ مشکی')
            ->assertSee('زیپ مخفی')
            ->assertSeeInOrder(['نخ', 'زیپ']);
    }

    public function test_a_material_can_be_created(): void
    {
        $this->actingAsWorkshopUser();

        $this->get(route('materials.create'))->assertOk()->assertSee('مشخصات');

        $this->post(route('materials.store'), [
            'name' => 'دکمه صدفی',
            'kind' => 'button',
            'spec' => 'شماره ۲۰',
            'unit' => 'عدد',
            'price' => 2500,
            'stock' => 120,
            'color' => 'سفید',
        ])->assertRedirect(route('materials.index'));

        $material = Material::firstOrFail();

        $this->assertSame('دکمه صدفی', $material->name);
        $this->assertSame('button', $material->kind);
        $this->assertSame(120.0, $material->stock);
        $this->assertSame($this->workshop()->id, $material->workshop_id);
    }

    public function test_name_and_kind_are_required(): void
    {
        $this->actingAsWorkshopUser();

        $this->post(route('materials.store'), [])->assertSessionHasErrors(['name', 'kind']);

        $this->assertSame(0, Material::count());
    }

    public function test_a_material_can_be_updated_and_deleted(): void
    {
        $this->actingAsWorkshopUser();

        $material = Material::factory()->create([
            'workshop_id' => $this->workshop()->id,
            'name' => 'نام قدیمی',
        ]);

        $this->get(route('materials.edit', $material))->assertOk()->assertSee('نام قدیمی');

        $this->put(route('materials.update', $material), [
            'name' => 'نام تازه',
            'kind' => 'elastic',
            'stock' => 10,
        ])->assertRedirect(route('materials.index'));

        $material->refresh();

        $this->assertSame('نام تازه', $material->name);
        $this->assertSame('elastic', $material->kind);

        $this->delete(route('materials.destroy', $material))->assertRedirect(route('materials.index'));
        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_materials_can_be_searched(): void
    {
        $this->actingAsWorkshopUser();

        Material::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'زیپ فلزی']);
        Material::factory()->create(['workshop_id' => $this->workshop()->id, 'name' => 'لایی چسب']);

        $this->get(route('materials.index', ['q' => 'زیپ']))
            ->assertOk()
            ->assertSee('زیپ فلزی')
            ->assertDontSee('لایی چسب');
    }

    public function test_materials_of_another_workshop_are_invisible(): void
    {
        $this->actingAsWorkshopUser();

        $other = Workshop::factory()->create();
        $foreign = Material::factory()->create(['workshop_id' => $other->id, 'name' => 'دکمه کارگاه دیگر']);

        $this->get(route('materials.index'))->assertOk()->assertDontSee('دکمه کارگاه دیگر');
        $this->get(route('materials.edit', $foreign))->assertNotFound();
        $this->put(route('materials.update', $foreign), ['name' => 'x', 'kind' => 'other'])->assertNotFound();
        $this->delete(route('materials.destroy', $foreign))->assertNotFound();
    }
}
