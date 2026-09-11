<?php

namespace App\Services\YandexMaps\Support;

use App\Services\YandexMaps\Exceptions\InvalidYandexMapsUrlException;

/**
 * Разбор и проверка ссылки на карточку организации в Яндекс.Картах.
 *
 * Поддерживаются оба вида ссылок, которые копирует сам Яндекс:
 *  - https://yandex.ru/maps/org/kompaniya/1234567890/reviews/
 *  - https://yandex.ru/maps/-/CCUabc123 (короткая, id карточки в ней нет)
 *
 * Ссылка приводится к каноническому виду без вкладок и параметров карты,
 * чтобы одну организацию нельзя было добавить дважды разными адресами.
 */
final class YandexMapsUrl
{
    private const HOST_PATTERN = '/^(?:[a-z0-9-]+\.)*yandex\.[a-z.]{2,10}$/i';

    /** Карточка вида /maps/org/<slug>/<id>/... — id есть прямо в адресе. */
    private const ORG_WITH_ID_PATTERN = '#^(?<base>/maps/org/[^/]+/(?<id>\d+))(?:/.*)?$#';

    /** Карточка без id в адресе: /maps/org/<slug> */
    private const ORG_WITHOUT_ID_PATTERN = '#^(?<base>/maps/org/[^/]+)/?$#';

    /** Короткая ссылка: /maps/-/CCUabc123 */
    private const SHORT_LINK_PATTERN = '#^(?<base>/maps/-/[^/]+)/?.*$#';

    private function __construct(
        public readonly string $host,
        public readonly string $path,
        public readonly ?string $businessId,
        public readonly string $normalized,
        public readonly bool $isShortLink,
    ) {}

    public static function parse(string $url): self
    {
        $url = trim($url);

        if ($url === '') {
            throw InvalidYandexMapsUrlException::notYandex($url);
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw InvalidYandexMapsUrlException::notYandex($url);
        }

        $host = mb_strtolower($parts['host']);
        $path = $parts['path'] ?? '/';

        if (! preg_match(self::HOST_PATTERN, $host)) {
            throw InvalidYandexMapsUrlException::notYandex($url);
        }

        [$basePath, $businessId, $isShortLink] = self::matchPath($path, $url);

        return new self(
            host: $host,
            path: $basePath,
            businessId: $businessId,
            normalized: 'https://'.$host.$basePath,
            isShortLink: $isShortLink,
        );
    }

    public static function fromBusinessId(string $businessId, string $host = 'yandex.ru'): self
    {
        return new self(
            host: $host,
            path: "/maps/org/{$businessId}",
            businessId: $businessId,
            normalized: "https://{$host}/maps/org/{$businessId}",
            isShortLink: false,
        );
    }

    /**
     * Адрес, по которому запрашивается страница карточки. У короткой ссылки
     * ничего не дописываем: она сама редиректит на карточку.
     */
    public function pageUrl(): string
    {
        return $this->isShortLink ? $this->normalized.'/' : $this->normalized.'/reviews/';
    }

    /**
     * @return array{0: string, 1: string|null, 2: bool}
     */
    private static function matchPath(string $path, string $url): array
    {
        if (preg_match(self::ORG_WITH_ID_PATTERN, $path, $matches) === 1) {
            return [$matches['base'], $matches['id'], false];
        }

        if (preg_match(self::SHORT_LINK_PATTERN, $path, $matches) === 1) {
            return [$matches['base'], null, true];
        }

        if (preg_match(self::ORG_WITHOUT_ID_PATTERN, $path, $matches) === 1) {
            return [$matches['base'], null, false];
        }

        throw InvalidYandexMapsUrlException::notAnOrganizationPage($url);
    }
}
