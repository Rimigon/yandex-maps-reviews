<?php

namespace App\Services\YandexMaps\Exceptions;

/**
 * Разметка или формат ответа Яндекса изменились настолько, что данные
 * разобрать нельзя. Именно этот тип ошибки не даёт парсеру «молча» вернуть
 * пустой список отзывов.
 */
class LayoutChangedException extends YandexMapsException
{
    public static function forPage(string $reason): self
    {
        return new self("Не удалось разобрать страницу карточки: {$reason}");
    }

    public static function forReviews(string $reason): self
    {
        return new self("Не удалось разобрать ответ метода отзывов: {$reason}");
    }
}
