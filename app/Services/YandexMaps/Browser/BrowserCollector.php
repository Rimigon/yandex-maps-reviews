<?php

namespace App\Services\YandexMaps\Browser;

use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use App\Services\YandexMaps\Exceptions\YandexMapsException;
use App\Services\YandexMaps\Parsing\OrgPageParser;
use App\Services\YandexMaps\Parsing\ReviewsPayloadParser;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Запасной сбор отзывов через headless-браузер.
 *
 * Включается переменной YANDEX_MAPS_BROWSER_FALLBACK=true и запускается
 * только тогда, когда прямой путь не сработал (капча, 403/429, смена защиты).
 * Браузер открывает карточку как обычный посетитель, а скрипт возвращает
 * HTML страницы и перехваченные ответы метода отзывов — их разбирают те же
 * парсеры, что и в основном пути.
 */
final class BrowserCollector
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly OrgPageParser $pageParser,
        private readonly ReviewsPayloadParser $reviewsParser,
        private readonly LoggerInterface $logger,
        private readonly array $config = [],
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['browser_fallback']['enabled'] ?? false);
    }

    public function collect(string $url): BrowserCollection
    {
        $output = $this->run($url);
        $decoded = json_decode($output, true);

        if (! is_array($decoded) || ! is_string($decoded['html'] ?? null)) {
            throw new YandexMapsException('Headless-браузер вернул неожиданный ответ');
        }

        $pageUrl = YandexMapsUrl::parse($url)->pageUrl();
        $page = $this->pageParser->parse($decoded['html'], $pageUrl);

        // Первая страница отзывов лежит в состоянии страницы, остальные — в перехваченных ответах.
        $payloads = [];
        $firstPage = $this->pageParser->stateReviewResults($decoded['html']);

        if ($firstPage !== null) {
            $payloads[] = ['data' => $firstPage];
        }

        foreach ($decoded['payloads'] ?? [] as $payload) {
            if (is_array($payload) && ! ReviewsPayloadParser::isCsrfRotation($payload)) {
                $payloads[] = $payload;
            }
        }

        $pages = [];

        foreach ($payloads as $payload) {
            $reviewPage = $this->reviewsParser->parsePage($payload, count($pages) + 1);

            // Одна и та же страница может прийти и в состоянии, и в ответе метода.
            $pages[$reviewPage->page] ??= $reviewPage;
        }

        if ($pages === []) {
            throw LayoutChangedException::forReviews('браузер не получил ни одной страницы отзывов');
        }

        ksort($pages);

        return new BrowserCollection($page, array_values($pages));
    }

    private function run(string $url): string
    {
        $script = (string) ($this->config['browser_fallback']['script'] ?? '');
        $command = (string) ($this->config['browser_fallback']['command'] ?? 'node');

        if (! is_file($script)) {
            throw new YandexMapsException("Не найден скрипт headless-сбора: {$script}. Выполните npm install в каталоге browser-fallback.");
        }

        $process = new Process([$command, $script, $url, (string) ($this->config['max_pages'] ?? 12)]);
        $process->setTimeout((float) ($this->config['browser_fallback']['timeout'] ?? 180));

        $this->logger->info('Собираю отзывы через headless-браузер', ['url' => $url]);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new YandexMapsException("Headless-браузер не запустился: {$exception->getMessage()}", 0, $exception);
        }

        if (! $process->isSuccessful()) {
            throw new YandexMapsException('Headless-браузер завершился с ошибкой: '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
