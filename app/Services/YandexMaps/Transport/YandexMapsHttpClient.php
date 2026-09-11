<?php

namespace App\Services\YandexMaps\Transport;

use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\OrganizationNotFoundException;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

/**
 * Транспорт: HTTP-запросы к Яндекс.Картам с «человеческим» поведением.
 *
 * Отвечает за то, что не относится к разбору данных: паузы между запросами,
 * ротацию User-Agent и прокси, повторы с бэкоффом, хранение cookie между
 * запросами одной сессии и распознавание блокировки/капчи.
 */
final class YandexMapsHttpClient
{
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    private const HTML_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';

    private const CAPTCHA_MARKERS = ['showcaptcha', 'SmartCaptcha', 'captcha-page'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly array $config = [],
    ) {}

    public function newCookieJar(): CookieJar
    {
        return new CookieJar;
    }

    /**
     * Новый «отпечаток» клиента для сессии парсинга.
     */
    public function newProfile(): HttpProfile
    {
        $agents = $this->config['user_agents'] ?? [];
        $proxies = $this->config['proxies'] ?? [];

        return new HttpProfile(
            userAgent: $agents === [] ? self::DEFAULT_USER_AGENT : (string) Arr::random($agents),
            proxy: $proxies === [] ? null : (string) Arr::random($proxies),
        );
    }

    public function getHtml(string $url, CookieJar $jar, HttpProfile $profile): string
    {
        return $this->send($url, [], $jar, $profile, self::HTML_ACCEPT, null)->body();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function getJson(string $url, array $query, CookieJar $jar, HttpProfile $profile, ?string $referer = null): array
    {
        $payload = $this->send($url, $query, $jar, $profile, 'application/json, text/plain, */*', $referer)->json();

        if (! is_array($payload)) {
            throw new YandexMapsException("Яндекс вернул не JSON на запрос {$url}");
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $url, array $query, CookieJar $jar, HttpProfile $profile, string $accept, ?string $referer): Response
    {
        $attempts = max(1, (int) ($this->config['retry']['times'] ?? 3));
        $sleepMs = (int) ($this->config['retry']['sleep_ms'] ?? 800);
        $error = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->throttle();

            try {
                $response = $this->http
                    ->withOptions($this->options($jar, $profile))
                    ->withHeaders($this->headers($accept, $referer, $url))
                    ->withUserAgent($profile->userAgent)
                    ->get($url, $query);

                if ($this->looksLikeCaptcha($response)) {
                    throw BlockedException::captcha($url);
                }

                if (in_array($response->status(), [403, 429], true)) {
                    throw BlockedException::forbidden($response->status(), $url);
                }

                if ($response->status() === 404) {
                    throw OrganizationNotFoundException::forUrl($url);
                }

                if ($response->successful()) {
                    return $response;
                }

                $error = new YandexMapsException(sprintf(
                    'Яндекс.Карты ответили HTTP %d на запрос %s: %s',
                    $response->status(),
                    $url,
                    mb_substr(trim($response->body()), 0, 500),
                ));
            } catch (ConnectionException $exception) {
                $error = new YandexMapsException("Не удалось соединиться с Яндекс.Картами: {$exception->getMessage()}", 0, $exception);
            }

            $this->logger->warning('Запрос к Яндекс.Картам не удался', [
                'url' => $url,
                'attempt' => $attempt,
                'attempts' => $attempts,
                'error' => $error?->getMessage(),
            ]);

            if ($attempt < $attempts) {
                usleep($sleepMs * $attempt * 1000); // линейный бэкофф: 1x, 2x, 3x...
            }
        }

        throw $error ?? new YandexMapsException("Запрос к Яндекс.Картам не выполнен: {$url}");
    }

    /**
     * @return array<string, mixed>
     */
    private function options(CookieJar $jar, HttpProfile $profile): array
    {
        $options = [
            'cookies' => $jar,
            'timeout' => (int) ($this->config['timeout'] ?? 20),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 10),
            // Короткие ссылки Яндекса (/maps/-/...) ведут на карточку через 301.
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
        ];

        if ($profile->proxy !== null) {
            $options['proxy'] = $profile->proxy;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accept, ?string $referer, string $url): array
    {
        $headers = [
            'Accept' => $accept,
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'X-Retpath-Y' => $referer ?? $url,
            'sec-ch-ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ];

        if ($referer !== null) {
            $headers['Referer'] = $referer;
        }

        return $headers;
    }

    /**
     * Пауза между запросами: выгрузка 600 отзывов не должна выглядеть как
     * поток запросов «в упор».
     */
    private function throttle(): void
    {
        $min = (int) ($this->config['throttle_ms']['min'] ?? 0);
        $max = (int) ($this->config['throttle_ms']['max'] ?? 0);

        if ($max > 0) {
            usleep(random_int($min, max($min, $max)) * 1000);
        }
    }

    private function looksLikeCaptcha(Response $response): bool
    {
        if (in_array($response->status(), [403, 429], true)) {
            return false; // отдельная ветка обработки
        }

        if ($response->header('X-Yandex-Captcha') !== '') {
            return true;
        }

        $body = mb_substr($response->body(), 0, 4000);

        foreach (self::CAPTCHA_MARKERS as $marker) {
            if (str_contains($body, $marker)) {
                return true;
            }
        }

        return false;
    }
}
