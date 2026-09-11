<?php

namespace App\Services\YandexMaps\Exceptions;

/**
 * Карточка организации не найдена: ссылка битая либо организация удалена.
 */
class OrganizationNotFoundException extends YandexMapsException
{
    public static function forUrl(string $url): self
    {
        return new self("По ссылке не нашлось карточки организации: {$url}");
    }

    public static function forBusinessId(string $businessId): self
    {
        return new self("Карточка организации {$businessId} недоступна");
    }
}
