<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Dto\OrganizationCard;
use App\Services\YandexMaps\Dto\OrganizationPage;
use App\Services\YandexMaps\Transport\HttpProfile;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Сессия парсинга одной организации.
 *
 * Держит всё изменяемое состояние между запросами: cookie, «отпечаток»
 * клиента и csrf-токен, который Яндекс умеет обновлять прямо в ответе.
 */
final class YandexMapsSession
{
    public function __construct(
        public readonly OrganizationPage $page,
        public readonly CookieJar $cookies,
        public readonly HttpProfile $profile,
        public readonly string $referer,
        private string $csrfToken,
    ) {}

    public function card(): OrganizationCard
    {
        return $this->page->card;
    }

    public function businessId(): string
    {
        return $this->page->card->businessId;
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    public function rotateCsrfToken(string $csrfToken): void
    {
        $this->csrfToken = $csrfToken;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function requestParams(array $extra): array
    {
        $params = [
            'csrfToken' => $this->csrfToken(),
            'sessionId' => $this->page->sessionId,
            'host_config' => $this->page->hostConfig,
            'ajax' => '1',
            'locale' => $this->page->locale,
        ];

        // Пустые значения не отправляем: Яндекс отвечает 400 и на `host_config=`,
        // и на отсутствующий параметр, если он не нужен (сверено с запросами
        // самой карточки в браузере).
        return array_filter(
            [...$params, ...$extra],
            static fn ($value) => $value !== null && $value !== [],
        );
    }
}
