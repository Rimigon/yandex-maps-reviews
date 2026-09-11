<?php

namespace Tests\Unit;

use App\Services\YandexMaps\Exceptions\InvalidYandexMapsUrlException;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YandexMapsUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function validUrls(): array
    {
        return [
            'полная ссылка на отзывы' => [
                'https://yandex.ru/maps/org/surf_coffee_x_flow/69353267050/reviews/',
                'https://yandex.ru/maps/org/surf_coffee_x_flow/69353267050',
                '69353267050',
            ],
            'ссылка на карточку с параметрами карты' => [
                'https://yandex.ru/maps/org/alenka/92098396399/?ll=37.6%2C55.7&z=17',
                'https://yandex.ru/maps/org/alenka/92098396399',
                '92098396399',
            ],
            'ссылка без схемы' => [
                'yandex.com/maps/org/test/1234567890/reviews',
                'https://yandex.com/maps/org/test/1234567890',
                '1234567890',
            ],
            'региональный поддомен' => [
                'https://yandex.kz/maps/org/test/1234567890/',
                'https://yandex.kz/maps/org/test/1234567890',
                '1234567890',
            ],
        ];
    }

    #[DataProvider('validUrls')]
    public function test_нормализует_ссылку_на_карточку(string $input, string $normalized, ?string $businessId): void
    {
        $url = YandexMapsUrl::parse($input);

        $this->assertSame($normalized, $url->normalized);
        $this->assertSame($businessId, $url->businessId);
    }

    public function test_короткая_ссылка_проходит_без_идентификатора(): void
    {
        $url = YandexMapsUrl::parse('https://yandex.ru/maps/-/CCUabc123');

        $this->assertTrue($url->isShortLink);
        $this->assertNull($url->businessId);
        $this->assertSame('https://yandex.ru/maps/-/CCUabc123/', $url->pageUrl());
    }

    public function test_ссылка_на_отзывы_открывается_именно_на_вкладке_отзывов(): void
    {
        $this->assertSame(
            'https://yandex.ru/maps/org/test/123/reviews/',
            YandexMapsUrl::parse('https://yandex.ru/maps/org/test/123/')->pageUrl(),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'пусто' => [''],
            'не ссылка' => ['просто текст'],
            'чужой домен' => ['https://2gis.ru/moscow/firm/123'],
            'похожий домен' => ['https://yandex.ru.evil.com/maps/org/test/123'],
            'не карточка организации' => ['https://yandex.ru/maps/213/moscow/search/%D0%BA%D0%BE%D1%84%D0%B5/'],
            'личный кабинет' => ['https://yandex.ru/maps/profile'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_отклоняет_неподходящие_ссылки(string $input): void
    {
        $this->expectException(InvalidYandexMapsUrlException::class);

        YandexMapsUrl::parse($input);
    }

    #[DataProvider('invalidUrls')]
    public function test_в_сообщении_об_ошибке_показывает_то_что_ввёл_пользователь(string $input): void
    {
        try {
            YandexMapsUrl::parse($input);
            $this->fail('Ожидалась ошибка разбора ссылки');
        } catch (InvalidYandexMapsUrlException $exception) {
            // Схема дописывается только для разбора: в тексте ошибки её быть не должно.
            $this->assertStringNotContainsString('https://'.$input, $exception->getMessage());
        }
    }
}
