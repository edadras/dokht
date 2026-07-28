<?php

namespace Tests\Unit;

use App\Models\GarmentType;
use App\Models\PatternTemplate;
use App\Models\Project;
use App\Services\Fit\FitAnalysisService;
use App\Services\Pattern\PatternBuilder;
use App\Services\Simulation\ClothPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe(): void
    {
        $this->actingAsWorkshopUser();
        $g = GarmentType::factory()->create(['parts' => ['front_bodice', 'back_bodice', 'sleeve']]);
        $p = Project::factory()->create(['workshop_id' => $this->workshop()->id, 'garment_type_id' => $g->id, 'size' => '40', 'measurement_set_id' => null]);
        $t = PatternTemplate::create(['garment_type_id' => $g->id, 'code' => 'x', 'name_fa' => 'x', 'generator' => getenv('GEN') ?: 'dress', 'is_public' => true]);
        $pattern = app(PatternBuilder::class)->createForProject($p, $t);
        $p->update(['pattern_id' => $pattern->id]);
        fwrite(STDERR, "PIECES: ".$pattern->pieces->pluck('code')->implode(', ')."\n");
        foreach ($pattern->pieces as $pc) {
            fwrite(STDERR, sprintf("%-14s qty=%d fold=%d mirror=%d w=%.1f h=%.1f markers=%s\n", $pc->code, $pc->cut_quantity, $pc->on_fold, $pc->mirror, $pc->width(), $pc->height(), implode(',', array_map(fn ($m) => $m['key'] ?? '?', $pc->markers ?? []))));
        }
        $a = app(FitAnalysisService::class)->analyze($pattern, null, $p->resolvedMeasurements());
        foreach ($a['zones'] as $z) {
            fwrite(STDERR, sprintf("%-10s garment=%6.1f body=%6.1f ease=%6.1f %-6s p=%.2f\n", $z['key'], $z['garment_cm'], $z['body_cm'], $z['ease_cm'], $z['level'], $z['pressure']));
        }
        fwrite(STDERR, "SCORE: ".$a['fit_score']."\n");
        $pay = app(ClothPreviewService::class)->payload($pattern, null, $p->resolvedMeasurements(), $a);
        fwrite(STDERR, "GARMENT: ".json_encode(['s' => $pay['garment']['silhouette'], 'l' => $pay['garment']['lengths'], 'pieces' => count($pay['garment']['pieces'])], JSON_UNESCAPED_UNICODE)."\n");
        $this->assertTrue(true);
    }
}
