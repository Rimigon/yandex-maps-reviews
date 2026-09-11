<?php

namespace App\Services\YandexMaps\Exceptions;

/**
 * Ссылку нельзя принять как ссылку на карточку организации в Яндекс.Картах.
 */
class InvalidYandexMapsUrlException extends YandexMapsException
{
    public static function notYandex(string $url): self
    {
        return new self("Ссылка должна вести на Яндекс.Карты: {$url}");
    }

    public static function notAnOrganizationPage(string $url): self
    {
        return new self("Ссылка должна вести на карточку организации: {$url}");
    }
}
