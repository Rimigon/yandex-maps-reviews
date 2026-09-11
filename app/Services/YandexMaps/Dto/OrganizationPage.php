<?php

namespace App\Services\YandexMaps\Dto;

/**
 * Всё, что нужно для дальнейшей работы, снятое со страницы карточки:
 * данные организации плюс параметры сессии для запросов к внутреннему API.
 */
final class OrganizationPage
{
    /**
     * @param  array<string, mixed>  $hostConfig
     */
    public function __construct(
        public readonly OrganizationCard $card,
        public readonly string $csrfToken,
        public readonly ?string $sessionId,
        public readonly array $hostConfig,
        public readonly string $apiBaseUrl,
        public readonly string $locale,
    ) {}
}
