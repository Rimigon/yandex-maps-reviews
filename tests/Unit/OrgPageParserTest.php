<?php

namespace Tests\Unit;

use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use App\Services\YandexMaps\Exceptions\OrganizationNotFoundException;
use App\Services\YandexMaps\Parsing\OrgPageParser;
use PHPUnit\Framework\TestCase;

class OrgPageParserTest extends TestCase
{
    private const PAGE_URL = 'https://yandex.ru/maps/org/surf_coffee_x_flow/69353267050/reviews/';

    private OrgPageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new OrgPageParser;
    }

    public function test_достаёт_карточку_и_параметры_сессии(): void
    {
        $page = $this->parser->parse($this->fixture('org-page.html'), self::PAGE_URL);

        $this->assertSame('69353267050', $page->card->businessId);
        $this->assertSame('Тестовая кофейня', $page->card->title);
        $this->assertSame('Москва, Страстной бульвар, 8А', $page->card->address);
        $this->assertSame(4.9, $page->card->rating);
        $this->assertSame(1231, $page->card->ratingsTotal);
        $this->assertSame(638, $page->card->reviewsTotal);

        $this->assertSame('token-from-page:1789131991', $page->csrfToken);
        $this->assertSame('1789131991374622-balancer', $page->sessionId);
        $this->assertSame('https://yandex.ru/maps', $page->apiBaseUrl);
        $this->assertSame('ru_RU', $page->locale);
    }

    public function test_берёт_рейтинг_из_микроразметки_если_структура_состояния_изменилась(): void
    {
        $html = $this->fixture('org-page.html');
        $html = str_replace('"ratingData":{"ratingCount":1231,"ratingValue":4.900000095367432,"reviewCount":638}', '"ratingData":null', $html);

        $card = $this->parser->parse($html, self::PAGE_URL)->card;

        $this->assertSame(4.9, $card->rating);
        $this->assertSame(1231, $card->ratingsTotal);
        $this->assertSame(638, $card->reviewsTotal);
    }

    public function test_распознаёт_капчу(): void
    {
        $this->expectException(BlockedException::class);

        $this->parser->parse($this->fixture('captcha-page.html'), self::PAGE_URL);
    }

    public function test_падает_с_понятной_ошибкой_если_разметка_переписана(): void
    {
        $this->expectException(LayoutChangedException::class);
        $this->expectExceptionMessageMatches('/state-view/');

        $this->parser->parse('<html><body><div class="organization-card"></div></body></html>', self::PAGE_URL);
    }

    public function test_отличает_отсутствующую_карточку_от_поломки_разметки(): void
    {
        // Яндекс отдаёт 200 и обычную страницу карт, если карточки нет:
        // это не смена разметки, и сообщение об ошибке должно быть другим.
        $this->expectException(OrganizationNotFoundException::class);

        $this->parser->parse($this->fixture('org-not-found.html'), self::PAGE_URL);
    }

    public function test_ошибка_о_ненайденной_карточке_содержит_ссылку(): void
    {
        try {
            $this->parser->parse($this->fixture('org-not-found.html'), self::PAGE_URL);
            $this->fail('Ожидалась ошибка о ненайденной карточке');
        } catch (OrganizationNotFoundException $exception) {
            $this->assertStringContainsString('maps/org/surf_coffee_x_flow/69353267050', $exception->getMessage());
        }
    }

    public function test_достаёт_первую_страницу_отзывов_из_состояния_страницы(): void
    {
        $html = <<<'HTML'
        <html><body>
        <script type="application/json" class="state-view">{"config":{"csrfToken":"t:1","query":{"orgpage":{"id":"42"}}},"reviews":{"businessReviews":{}},"stack":[{"results":{"items":[{"id":"42","title":"Тест","ratingData":{"ratingCount":10,"ratingValue":4.5,"reviewCount":2},"reviewResults":{"params":{"page":1,"limit":50,"count":2,"totalPages":1},"reviews":[{"reviewId":"r1","rating":5,"text":"Первый"},{"reviewId":"r2","rating":4,"text":"Второй"}]}}]}}]}</script>
        </body></html>
        HTML;

        $results = $this->parser->stateReviewResults($html);

        $this->assertIsArray($results);
        $this->assertSame(2, $results['params']['count']);
        $this->assertCount(2, $results['reviews']);
        $this->assertSame('r1', $results['reviews'][0]['reviewId']);
    }

    public function test_если_отзывов_в_состоянии_нет_возвращается_null(): void
    {
        $this->assertNull($this->parser->stateReviewResults($this->fixture('org-page.html')));
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);
    }
}
