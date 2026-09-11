<?php

namespace Tests\Unit;

use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use App\Services\YandexMaps\Parsing\ReviewsPayloadParser;
use PHPUnit\Framework\TestCase;

class ReviewsPayloadParserTest extends TestCase
{
    private ReviewsPayloadParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ReviewsPayloadParser;
    }

    public function test_разбирает_страницу_отзывов(): void
    {
        $page = $this->parser->parsePage($this->payload(), 1);

        $this->assertSame(5859, $page->totalCount);
        $this->assertSame(118, $page->lastPage);
        $this->assertSame(50, $page->perPage);
        $this->assertTrue($page->hasNextPage());
        $this->assertCount(2, $page->reviews);

        $first = $page->reviews[0];

        $this->assertSame('Mw-ALTjIQlgRbFAWCOnH1MCDyyo0VSuM', $first->externalId);
        $this->assertSame('Валерий', $first->authorName);
        $this->assertSame(1, $first->rating);
        $this->assertSame(5, $first->likes);
        $this->assertSame('Спасибо, что сообщили.', $first->businessComment);
        $this->assertSame('2026-08-04 13:07:09', $first->publishedAt?->toDateTimeString());
    }

    public function test_пустая_страница_не_считается_поломкой(): void
    {
        $payload = $this->payload();
        $payload['data']['reviews'] = [];

        $page = $this->parser->parsePage($payload, 13);

        $this->assertTrue($page->isEmpty());
    }

    public function test_узнаёт_запрос_с_новым_csrf_токеном(): void
    {
        $this->assertTrue(ReviewsPayloadParser::isCsrfRotation(['csrfToken' => 'new-token']));
        $this->assertFalse(ReviewsPayloadParser::isCsrfRotation($this->payload()));
    }

    public function test_капча_вместо_данных(): void
    {
        $this->expectException(BlockedException::class);

        $this->parser->parsePage(['type' => 'captcha'], 1);
    }

    public function test_чужая_схема_ответа_это_ошибка_а_не_пустой_список(): void
    {
        $this->expectException(LayoutChangedException::class);

        $this->parser->parsePage(['data' => ['reviews' => []]], 1);
    }

    public function test_отзыв_без_идентификатора_ломает_разбор(): void
    {
        $this->expectException(LayoutChangedException::class);

        $this->parser->parsePage(['data' => [
            'params' => ['count' => 1, 'totalPages' => 1, 'page' => 1, 'limit' => 50],
            'reviews' => [['rating' => 5, 'text' => 'Отзыв без reviewId']],
        ]], 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'data' => [
                'params' => [
                    'offset' => 0,
                    'limit' => 50,
                    'count' => 5859,
                    'page' => 1,
                    'totalPages' => 118,
                ],
                'reviews' => [
                    [
                        'reviewId' => 'Mw-ALTjIQlgRbFAWCOnH1MCDyyo0VSuM',
                        'businessId' => '1124715036',
                        'author' => ['name' => 'Валерий'],
                        'text' => 'Текст отзыва',
                        'rating' => 1,
                        'updatedTime' => '2026-08-04T13:07:09.580Z',
                        'reactions' => ['likes' => 5, 'dislikes' => 1],
                        'businessComment' => ['text' => 'Спасибо, что сообщили.'],
                    ],
                    [
                        'reviewId' => 'second-review-id',
                        'author' => ['name' => 'Ольга В.', 'avatarUrl' => 'https://avatars.mds.yandex.net/avatar/{size}'],
                        'text' => 'Второй отзыв',
                        'rating' => 5,
                        'updatedTime' => '2026-01-01T00:00:00.000Z',
                    ],
                ],
            ],
        ];
    }
}
