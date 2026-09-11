<?php

namespace App\Services\YandexMaps\Parsing;

use App\Services\YandexMaps\Dto\ReviewData;
use App\Services\YandexMaps\Dto\ReviewPage;
use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\LayoutChangedException;

/**
 * Разбор ответа внутреннего метода business/fetchReviews.
 *
 * Ответ выглядит так:
 *   { "data": { "params": { "count": 5859, "totalPages": 118, ... },
 *               "reviews": [ { "reviewId": "...", "rating": 5, ... } ] } }
 *
 * Любое расхождение с этой схемой приводит к LayoutChangedException, а не
 * к «пустому списку отзывов» — так поломка парсера становится заметной.
 */
final class ReviewsPayloadParser
{
    /**
     * Ответ с новым csrf-токеном вместо данных: Яндекс так просит повторить
     * запрос (токен живёт недолго и привязан к сессии).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function isCsrfRotation(array $payload): bool
    {
        return isset($payload['csrfToken']) && ! isset($payload['data']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePage(array $payload, int $page): ReviewPage
    {
        if (($payload['type'] ?? null) === 'captcha') {
            throw BlockedException::captcha('метод fetchReviews');
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw LayoutChangedException::forReviews('в ответе нет блока data');
        }

        $reviews = $data['reviews'] ?? null;
        $params = $data['params'] ?? null;

        if (! is_array($reviews) || ! is_array($params)) {
            throw LayoutChangedException::forReviews('в ответе нет reviews или params');
        }

        if (! is_numeric($params['count'] ?? null)) {
            throw LayoutChangedException::forReviews('в params нет общего количества отзывов (count)');
        }

        return new ReviewPage(
            page: (int) ($params['page'] ?? $page),
            perPage: (int) ($params['limit'] ?? count($reviews)),
            totalCount: (int) $params['count'],
            lastPage: (int) ($params['totalPages'] ?? 1),
            reviews: array_values(array_filter(array_map(
                static fn (array $raw): ReviewData => ReviewData::fromApi($raw),
                array_filter($reviews, 'is_array'),
            ))),
        );
    }
}
