<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Dto\ReviewPage;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use App\Services\YandexMaps\Parsing\OrgPageParser;
use App\Services\YandexMaps\Parsing\ReviewsPayloadParser;
use App\Services\YandexMaps\Support\QuerySigner;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use App\Services\YandexMaps\Transport\YandexMapsHttpClient;
use Generator;

/**
 * Работа с карточкой организации на Яндекс.Картах.
 *
 * Официального API у Яндекса нет, поэтому используется тот же внутренний
 * метод, что и у самой карточки: business/fetchReviews. Он отдаёт JSON
 * страницами по 50 отзывов, но требует csrf-токен и подпись запроса,
 * которые берутся со страницы карточки. Подробнее — в README.
 */
final class YandexMapsClient
{
    /** Сколько раз пробуем обновлённый csrf-токен, прежде чем сдаться. */
    private const MAX_CSRF_ATTEMPTS = 3;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly YandexMapsHttpClient $http,
        private readonly OrgPageParser $pageParser,
        private readonly ReviewsPayloadParser $reviewsParser,
        private readonly array $config = [],
    ) {}

    /**
     * Открывает сессию по ссылке на карточку: одним запросом получает данные
     * организации и параметры, нужные для дальнейших запросов.
     */
    public function openSession(string $url, ?string $knownBusinessId = null): YandexMapsSession
    {
        $parsed = YandexMapsUrl::parse($url);

        $cookies = $this->http->newCookieJar();
        $profile = $this->http->newProfile();
        $referer = $parsed->pageUrl();

        $html = $this->http->getHtml($referer, $cookies, $profile);
        $page = $this->pageParser->parse($html, $referer, $knownBusinessId ?? $parsed->businessId);

        return new YandexMapsSession(
            page: $page,
            cookies: $cookies,
            profile: $profile,
            referer: $referer,
            csrfToken: $page->csrfToken,
        );
    }

    /**
     * Страница отзывов. Если Яндекс в ответ на запрос отдаёт новый
     * csrf-токен вместо данных, запрос повторяется с ним.
     */
    public function fetchReviewsPage(YandexMapsSession $session, int $page): ReviewPage
    {
        $url = $session->page->apiBaseUrl.'/api/business/fetchReviews';

        for ($attempt = 1; $attempt <= self::MAX_CSRF_ATTEMPTS; $attempt++) {
            $query = QuerySigner::sign($session->requestParams([
                'businessId' => $session->businessId(),
                'page' => $page,
                'pageSize' => $this->pageSize(),
                'ranking' => 'by_time',
            ]));

            $payload = $this->http->getJson($url, $query, $session->cookies, $session->profile, $session->referer);

            if (! ReviewsPayloadParser::isCsrfRotation($payload)) {
                return $this->reviewsParser->parsePage($payload, $page);
            }

            $session->rotateCsrfToken((string) $payload['csrfToken']);
        }

        throw new YandexMapsException('Яндекс не принял csrf-токен за '.self::MAX_CSRF_ATTEMPTS.' попытки');
    }

    /**
     * Отзывы страница за страницей, пока Яндекс их отдаёт и пока не
     * упёрлись в лимит выдачи.
     *
     * @return Generator<int, ReviewPage>
     */
    public function streamReviews(YandexMapsSession $session): Generator
    {
        for ($page = 1; $page <= $this->maxPages(); $page++) {
            $reviews = $this->fetchReviewsPage($session, $page);

            yield $reviews;

            if ($reviews->isEmpty() || ! $reviews->hasNextPage()) {
                return;
            }
        }
    }

    public function pageSize(): int
    {
        return max(1, (int) ($this->config['page_size'] ?? 50));
    }

    /**
     * Яндекс отдаёт не больше ~600 отзывов на карточку, поэтому глубину
     * выгрузки ограничиваем — иначе получили бы 12 страниц данных и 100
     * пустых запросов.
     */
    public function maxPages(): int
    {
        return max(1, (int) ($this->config['max_pages'] ?? 12));
    }
}
