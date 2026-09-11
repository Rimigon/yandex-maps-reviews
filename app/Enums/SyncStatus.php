<?php

namespace App\Enums;

/**
 * Состояние выгрузки отзывов по организации.
 */
enum SyncStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Ещё не выгружалось',
            self::Queued => 'В очереди',
            self::Running => 'Выгружаем отзывы',
            self::Completed => 'Готово',
            self::Failed => 'Ошибка выгрузки',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
