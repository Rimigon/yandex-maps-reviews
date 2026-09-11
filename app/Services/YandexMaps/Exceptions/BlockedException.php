<?php

namespace App\Services\YandexMaps\Exceptions;

/**
 * Яндекс показал капчу или заблокировал запросы.
 *
 * Такие ошибки не имеет смысла ретраить сразу — нужен бэкофф и, возможно,
 * смена прокси/User-Agent (см. README, раздел про анти-бан).
 */
class BlockedException extends YandexMapsException
{
    public static function captcha(string $url): self
    {
        return new self("Яндекс.Карты показали капчу, запрос отклонён: {$url}");
    }

    public static function forbidden(int $status, string $url): self
    {
        return new self("Яндекс.Карты отклонили запрос (HTTP {$status}), вероятно, сработала защита от ботов: {$url}");
    }
}
