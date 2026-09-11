<?php

namespace App\Services\Sync;

/**
 * Итог сохранения страницы отзывов: сколько записей добавилось, изменилось
 * и осталось прежним. По этим числам строится отчёт «что изменилось».
 */
final class ReviewSaveResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $unchanged = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            created: $this->created + $other->created,
            updated: $this->updated + $other->updated,
            unchanged: $this->unchanged + $other->unchanged,
        );
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->unchanged;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
        ];
    }
}
