<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Services\YandexMaps\Dto\OrganizationCard;
use App\Services\YandexMaps\Dto\SyncProgress;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_url',
        'normalized_url',
        'business_id',
        'title',
        'address',
        'rating',
        'ratings_total',
        'reviews_total',
        'reviews_parsed',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'sync_status' => SyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /**
     * Поиск по любой форме ссылки на карточку: одна организация может быть
     * добавлена и короткой ссылкой, и обычной.
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeMatchingUrl(Builder $query, YandexMapsUrl $url): Builder
    {
        return $query->where(function (Builder $query) use ($url): void {
            $query->where('normalized_url', $url->normalized);

            if ($url->businessId !== null) {
                $query->orWhere('business_id', $url->businessId);
            }
        });
    }

    /**
     * Данные уже выгружались и ещё не устарели — повторный парсинг не нужен.
     */
    public function hasFreshData(): bool
    {
        if ($this->last_synced_at === null || $this->reviews()->doesntExist()) {
            return false;
        }

        return $this->last_synced_at->gt(now()->subSeconds((int) config('yandex-maps.cache_ttl', 900)));
    }

    public function markQueued(): void
    {
        $this->forceFill([
            'sync_status' => SyncStatus::Queued,
            'sync_error' => null,
            'sync_pages_done' => 0,
            'sync_pages_total' => 0,
        ])->save();
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'sync_status' => SyncStatus::Running,
            'sync_error' => null,
            'sync_pages_done' => 0,
        ])->save();
    }

    /**
     * Данные карточки со страницы: название, адрес, рейтинг, счётчики.
     */
    public function applyCard(OrganizationCard $card): void
    {
        $this->forceFill([
            'business_id' => $card->businessId,
            'title' => $card->title ?? $this->title,
            'address' => $card->address ?? $this->address,
            'rating' => $card->rating,
            'ratings_total' => $card->ratingsTotal,
            'reviews_total' => $card->reviewsTotal,
        ])->save();
    }

    public function updateProgress(SyncProgress $progress): void
    {
        $this->forceFill([
            'sync_pages_done' => $progress->pagesDone,
            'sync_pages_total' => $progress->pagesTotal,
            'reviews_parsed' => $progress->reviewsStored,
        ])->save();
    }

    public function markCompleted(int $reviewsParsed): void
    {
        $this->forceFill([
            'sync_status' => SyncStatus::Completed,
            'sync_error' => null,
            'reviews_parsed' => $reviewsParsed,
            'last_synced_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'sync_status' => SyncStatus::Failed,
            'sync_error' => mb_substr($error, 0, 1000),
        ])->save();
    }

    /**
     * Прогресс в процентах — для полосы загрузки на фронте.
     */
    public function progressPercent(): int
    {
        if ($this->sync_status === SyncStatus::Completed) {
            return 100;
        }

        if ($this->sync_pages_total === 0) {
            return 0;
        }

        return (int) min(100, round($this->sync_pages_done / $this->sync_pages_total * 100));
    }
}
