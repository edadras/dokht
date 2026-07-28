<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternTemplate;
use App\Models\Workshop;
use App\Services\Pattern\PatternBuilder;
use App\Support\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternExportTest extends TestCase
{
    use RefreshDatabase;

    protected function pattern(string $generator = 'bodice_block'): Pattern
    {
        $template = PatternTemplate::factory()->generator($generator)->create();

        return app(PatternBuilder::class)->createPattern($template, [
            'name' => 'بالاتنه خروجی',
            'base_size' => '40',
        ], [
            'measurements' => Measurements::fromSize('40'),
            'ease' => ['bust' => 6, 'waist' => 4, 'hip' => 6],
        ]);
    }

    public function test_svg_export_returns_an_svg_download(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.export', [$pattern, 'svg']));

        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.svg', $response->headers->get('content-disposition'));

        $body = $response->getContent();

        $this->assertStringStartsWith('<svg', $body);
        // یک مسیر برای هر قطعه (خط دوخت) به‌همراه خط برش
        $this->assertGreaterThanOrEqual($pattern->pieces->count(), substr_count($body, '<path'));
        $this->assertStringContainsString('viewBox', $body);
    }

    public function test_dxf_export_returns_a_dxf_download(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.export', [$pattern, 'dxf']));

        $response->assertOk();
        $this->assertStringContainsString('application/dxf', $response->headers->get('content-type'));
        $this->assertStringContainsString('.dxf', $response->headers->get('content-disposition'));

        $body = $response->getContent();

        $this->assertStringContainsString('SECTION', $body);
        $this->assertStringContainsString('ENTITIES', $body);
        $this->assertStringContainsString('POLYLINE', $body);
        $this->assertStringEndsWith("EOF\n", $body);
    }

    public function test_json_export_carries_pattern_and_pieces(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.export', [$pattern, 'json']));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame('dokht-pattern', $payload['format']);
        $this->assertSame('بالاتنه خروجی', $payload['pattern']['name']);
        $this->assertSame('bodice_block', $payload['pattern']['generator']);
        $this->assertCount($pattern->pieces->count(), $payload['pieces']);
        $this->assertArrayHasKey('outline', $payload['pieces'][0]);
        $this->assertArrayHasKey('edge_tags', $payload['pieces'][0]);
        $this->assertArrayHasKey('area', $payload['pieces'][0]['measures']);
        $this->assertSame('cm', $payload['geometry']['unit']);
    }

    public function test_unknown_format_is_not_routed(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->get(route('patterns.export', [$pattern, 'json']).'x')->assertNotFound();
        $this->get('/patterns/'.$pattern->id.'/export/pdf')->assertNotFound();
    }

    public function test_print_page_tiles_pieces_at_real_size(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $response = $this->get(route('patterns.print', $pattern));

        $response->assertOk()
            ->assertSee('چند صفحه')
            ->assertSee('۱۰ سانتی‌متر', false)
            ->assertSee('mm" height="', false)
            ->assertSee('برگ الگو')
            ->assertSee('بالاتنه جلو');

        // اندازه بیرونی هر برگ در میلی‌متر است تا چاپ یک‌به‌یک بماند
        $this->assertStringContainsString('width="170mm"', $response->getContent());
        $this->assertStringContainsString('height="250mm"', $response->getContent());
    }

    public function test_print_can_hide_the_seam_allowance_line(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern();

        $this->get(route('patterns.print', [$pattern, 'no_seam' => 1]))
            ->assertOk()
            ->assertSee('فقط خط دوخت چاپ شده است.');
    }

    public function test_export_of_another_workshop_pattern_is_not_found(): void
    {
        $other = Workshop::factory()->create();
        $foreign = Pattern::factory()->withSimplePieces()->create(['workshop_id' => $other->id]);

        $this->actingAsWorkshopUser();

        $this->get(route('patterns.export', [$foreign, 'svg']))->assertNotFound();
        $this->get(route('patterns.export', [$foreign, 'dxf']))->assertNotFound();
        $this->get(route('patterns.print', $foreign))->assertNotFound();
    }

    public function test_shirt_export_contains_every_piece(): void
    {
        $this->actingAsWorkshopUser();
        $pattern = $this->pattern('shirt_classic');

        $payload = json_decode($this->get(route('patterns.export', [$pattern, 'json']))->getContent(), true);
        $codes = array_column($payload['pieces'], 'code');

        foreach (['shirt-front', 'shirt-back', 'yoke', 'sleeve', 'collar', 'cuff', 'placket'] as $code) {
            $this->assertContains($code, $codes);
        }
    }
}
