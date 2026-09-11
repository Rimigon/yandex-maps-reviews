<?php

namespace App\Services\YandexMaps\Transport;

/**
 * «Отпечаток» клиента: User-Agent и прокси, которые использует одна сессия
 * парсинга. Меняются от сессии к сессии, чтобы объёмная выгрузка не выглядела
 * как один и тот же бот (см. README, анти-бан).
 */
final class HttpProfile
{
    public function __construct(
        public readonly string $userAgent,
        public readonly ?string $proxy = null,
    ) {}
}
