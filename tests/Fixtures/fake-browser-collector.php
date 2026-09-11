<?php

/**
 * Двойник скрипта headless-сбора для тестов: печатает тот же JSON,
 * что возвращает browser-fallback/collect-reviews.mjs, но без браузера.
 *
 * Используется тестом через config('yandex-maps.browser_fallback'): команда php,
 * скрипт — этот файл.
 */
$html = file_get_contents(__DIR__.'/org-page.html');

$review = static fn (string $id, string $text, int $rating): array => [
    'reviewId' => $id,
    'author' => ['name' => 'Автор '.$id],
    'text' => $text,
    'rating' => $rating,
    'updatedTime' => '2026-08-04T13:07:09.580Z',
    'reactions' => ['likes' => 1, 'dislikes' => 0],
];

$payload = [
    'data' => [
        'params' => ['page' => 1, 'limit' => 50, 'count' => 3, 'totalPages' => 1],
        'reviews' => [
            $review('browser-1', 'Отзыв, собранный браузером', 5),
            $review('browser-2', 'Второй отзыв', 4),
            $review('browser-3', 'Третий отзыв', 1),
        ],
    ],
];

echo json_encode(['html' => $html, 'payloads' => [$payload]], JSON_UNESCAPED_UNICODE);
