<?php

namespace App\Services\Marketplace;

use App\Models\Pattern;
use App\Models\PatternPiece;
use App\Models\PatternVersion;
use Illuminate\Support\Facades\DB;

/**
 * ساختن نسخه مستقل از یک الگو در کارگاه مقصد.
 *
 * همان معنای کپیِ کتابخانه است: الگو با همه قطعه‌هایش عیناً کپی می‌شود، یک نسخه
 * آغازین در تاریخچه ثبت می‌شود و از آن لحظه به بعد دو الگو هیچ ربطی به هم ندارند —
 * تغییر یکی روی دیگری اثر نمی‌گذارد. دفترچه اندازه مشتریِ کارگاه مبدأ و وضعیت
 * انتشار/آگهی منتقل نمی‌شود؛ نسخه تازه خصوصیِ کارگاه مقصد است.
 */
class PatternDuplicator
{
    /**
     * کپی الگو در کارگاه مقصد.
     *
     * @param  int  $workshopId  کارگاه مقصد؛ همیشه صریح داده می‌شود تا نسخه در کارگاه اشتباه ساخته نشود
     */
    public function duplicate(Pattern $source, int $workshopId, ?string $note = null, ?int $userId = null): Pattern
    {
        $source->loadMissing('pieces');

        return DB::transaction(function () use ($source, $workshopId, $note, $userId) {
            $copy = Pattern::create([
                'workshop_id' => $workshopId,
                'garment_type_id' => $source->garment_type_id,
                'pattern_template_id' => $source->pattern_template_id,
                'measurement_set_id' => null, // دفترچه اندازه کارگاه دیگر منتقل نمی‌شود
                'name' => $this->copyName($source, $workshopId),
                'base_size' => $source->base_size,
                'measurements' => $source->measurements,
                'ease' => $source->ease,
                'seam_allowances' => $source->seam_allowances,
                'params' => $source->params,
                'sewing_relations' => $source->sewing_relations,
                'version' => 1,
                'notes' => $source->notes,
                'is_published' => false, // انتشار و آگهی فروشنده منتقل نمی‌شود
            ]);

            foreach ($source->pieces as $piece) {
                $copy->pieces()->create($this->pieceAttributes($piece));
            }

            PatternVersion::create([
                'pattern_id' => $copy->id,
                'version' => 1,
                'snapshot' => $this->snapshot($copy->load('pieces')),
                'note' => $note ?? 'نسخه آغازین',
                'created_by' => $userId,
            ]);

            // شمارنده کپی روی الگوی مبدأ، بی‌توجه به محدودیت کارگاه
            Pattern::query()->acrossWorkshops()->whereKey($source->id)->increment('copies_count');

            return $copy;
        });
    }

    /**
     * نام نسخه کپی، یکتا در کارگاه مقصد.
     *
     * زنجیره «(کپی)(کپی)» ساخته نمی‌شود؛ به‌جایش شماره می‌گیرد.
     */
    public function copyName(Pattern $source, int $workshopId): string
    {
        $base = preg_replace('/\s*\(کپی(?:\s*\d+)?\)\s*$/u', '', $source->name) ?: $source->name;
        $name = $base.' (کپی)';
        $counter = 2;

        while ($this->nameTaken($name, $workshopId)) {
            $name = $base.' (کپی '.$counter++.')';
        }

        return $name;
    }

    protected function nameTaken(string $name, int $workshopId): bool
    {
        return Pattern::query()
            ->acrossWorkshops()
            ->where('workshop_id', $workshopId)
            ->where('name', $name)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function pieceAttributes(PatternPiece $piece): array
    {
        return [
            'code' => $piece->code,
            'name' => $piece->name,
            'layer' => $piece->layer,
            'cut_quantity' => $piece->cut_quantity,
            'on_fold' => $piece->on_fold,
            'mirror' => $piece->mirror,
            'outline' => $piece->outline,
            'grainline' => $piece->grainline,
            'darts' => $piece->darts,
            'notches' => $piece->notches,
            'drills' => $piece->drills,
            'pleats' => $piece->pleats,
            'markers' => $piece->markers,
            'edge_allowances' => $piece->edge_allowances,
            'meta' => $piece->meta,
            'sort' => $piece->sort,
        ];
    }

    /** عکس لحظه‌ای الگو برای تاریخچه نسخه‌ها. */
    public function snapshot(Pattern $pattern): array
    {
        return [
            'pattern' => [
                'name' => $pattern->name,
                'base_size' => $pattern->base_size,
                'measurements' => $pattern->measurements,
                'ease' => $pattern->ease,
                'seam_allowances' => $pattern->seam_allowances,
                'params' => $pattern->params,
                'sewing_relations' => $pattern->sewing_relations,
            ],
            'pieces' => $pattern->pieces->map(fn (PatternPiece $piece) => $this->pieceAttributes($piece))->all(),
        ];
    }
}
