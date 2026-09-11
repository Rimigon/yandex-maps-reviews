<?php

namespace App\Models;

use Database\Factories\OrganizationSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Снимок состояния карточки на момент выгрузки: нужен, чтобы показать,
 * что изменилось между парсингами (было -> стало).
 */
class OrganizationSnapshot extends Model
{
    /** @use HasFactory<OrganizationSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'rating',
        'ratings_total',
        'reviews_total',
        'reviews_parsed',
        'changes',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'changes' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
