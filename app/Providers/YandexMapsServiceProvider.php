<?php

namespace App\Providers;

use App\Services\YandexMaps\Browser\BrowserCollector;
use App\Services\YandexMaps\Parsing\OrgPageParser;
use App\Services\YandexMaps\Parsing\ReviewsPayloadParser;
use App\Services\YandexMaps\Transport\YandexMapsHttpClient;
use App\Services\YandexMaps\YandexMapsClient;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Сборка клиента Яндекс.Карт: настройки из config/yandex-maps.php кладутся
 * в конструкторы, чтобы сервисы не зависели от глобального config().
 */
class YandexMapsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrgPageParser::class);
        $this->app->singleton(ReviewsPayloadParser::class);

        $this->app->bind(BrowserCollector::class, fn ($app) => new BrowserCollector(
            pageParser: $app->make(OrgPageParser::class),
            reviewsParser: $app->make(ReviewsPayloadParser::class),
            logger: $app->make(LoggerInterface::class),
            config: $app->make(Repository::class)->get('yandex-maps'),
        ));

        $this->app->bind(YandexMapsHttpClient::class, fn ($app) => new YandexMapsHttpClient(
            http: $app->make(HttpFactory::class),
            logger: $app->make(LoggerInterface::class),
            config: $app->make(Repository::class)->get('yandex-maps'),
        ));

        $this->app->bind(YandexMapsClient::class, fn ($app) => new YandexMapsClient(
            http: $app->make(YandexMapsHttpClient::class),
            pageParser: $app->make(OrgPageParser::class),
            reviewsParser: $app->make(ReviewsPayloadParser::class),
            config: $app->make(Repository::class)->get('yandex-maps'),
        ));
    }
}
