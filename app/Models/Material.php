<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkshop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** متعلقات دوخت: نخ، دکمه، زیپ، لایی، آستر. */
#[Fillable(['workshop_id', 'name', 'kind', 'spec', 'unit', 'price', 'stock', 'color'])]
class Material extends Model
{
    use BelongsToWorkshop, HasFactory;

    public const KINDS = [
        'thread' => 'نخ',
        'button' => 'دکمه',
        'zipper' => 'زیپ',
        'interfacing' => 'لایی',
        'lining' => 'آستر',
        'elastic' => 'کشی',
        'other' => 'سایر',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'stock' => 'float',
        ];
    }

    public function kindLabel(): string
    {
        return static::KINDS[$this->kind] ?? $this->kind;
    }
}
