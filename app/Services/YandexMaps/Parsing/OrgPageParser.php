<?php

namespace App\Services\YandexMaps\Parsing;

use App\Services\YandexMaps\Dto\OrganizationCard;
use App\Services\YandexMaps\Dto\OrganizationPage;
use App\Services\YandexMaps\Exceptions\BlockedException;
use App\Services\YandexMaps\Exceptions\LayoutChangedException;
use App\Services\YandexMaps\Exceptions\OrganizationNotFoundException;

/**
 * Разбор HTML страницы карточки организации.
 *
 * Страница приходит отрендеренной на сервере: в теге
 * `<script type="application/json" class="state-view">` лежит состояние
 * приложения, из которого берутся csrf-токен, id сессии аналитики, id
 * организации и её рейтинг со счётчиками. Парсер не привязан к классам
 * вёрстки, поэтому косметические изменения дизайна его не ломают.
 * Рейтинг дополнительно читается из микроразметки schema.org.
 */
final class OrgPageParser
{
    private const STATE_VIEW_PATTERN = '#<script\b[^>]*class="[^"]*\bstate-view\b[^"]*"[^>]*>(.*?)</script>#is';

    private const CAPTCHA_MARKERS = [
        'showcaptcha',
        'SmartCaptcha',
        'captcha-page',
        'Подтвердите, что запросы отправляли вы',
    ];

    /**
     * Признаки того, что карточки по ссылке нет: Яндекс отдаёт на такую ссылку
     * HTTP 200 и обычную страницу карт без данных организации. Без этой проверки
     * «организация не найдена» выглядела бы как поломка парсера.
     */
    private const MISSING_ORGANIZATION_MARKERS = [
        'Ничего не найдено',
        'ничего не нашлось',
    ];

    public function parse(string $html, string $pageUrl, ?string $expectedBusinessId = null): OrganizationPage
    {
        $state = $this->extractState($html);
        $config = is_array($state['config'] ?? null) ? $state['config'] : [];

        $card = $this->extractCard(
            state: $state,
            html: $html,
            pageUrl: $pageUrl,
            expectedBusinessId: $expectedBusinessId,
        );

        $csrfToken = $this->string($config['csrfToken'] ?? null);

        if ($csrfToken === null) {
            throw LayoutChangedException::forPage('в состоянии страницы нет csrfToken');
        }

        return new OrganizationPage(
            card: $card,
            csrfToken: $csrfToken,
            sessionId: $this->string(data_get($config, 'counters.analytics.sessionId')),
            hostConfig: is_array($config['hostConfig'] ?? null) ? $config['hostConfig'] : [],
            apiBaseUrl: $this->apiBaseUrl($config, $pageUrl),
            locale: $this->string($config['locale'] ?? null) ?? 'ru_RU',
        );
    }

    /**
     * Первая страница отзывов приезжает уже отрендеренной в состоянии
     * страницы — запасной путь берёт её оттуда, а не запрашивает повторно.
     *
     * @return array<string, mixed>|null
     */
    public function stateReviewResults(string $html): ?array
    {
        return $this->findReviewResults($this->extractState($html));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function findReviewResults(array $state, int $depth = 0): ?array
    {
        if ($depth > 12) {
            return null;
        }

        if (is_array($state['reviewResults'] ?? null) && isset($state['reviewResults']['reviews'])) {
            return $state['reviewResults'];
        }

        foreach ($state as $value) {
            if (! is_array($value)) {
                continue;
            }

            $found = $this->findReviewResults($value, $depth + 1);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractState(string $html): array
    {
        if (preg_match_all(self::STATE_VIEW_PATTERN, $html, $matches) > 0) {
            foreach ($matches[1] as $json) {
                $state = json_decode($json, true);

                if (is_array($state) && is_array($state['config'] ?? null)) {
                    return $state;
                }
            }
        }

        if ($this->looksLikeCaptcha($html)) {
            throw BlockedException::captcha('страница карточки');
        }

        throw LayoutChangedException::forPage('не найден блок состояния страницы (<script class="state-view">)');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function extractCard(array $state, string $html, string $pageUrl, ?string $expectedBusinessId): OrganizationCard
    {
        $node = $this->findBusinessNode($state);
        $ratingData = is_array($node['ratingData'] ?? null) ? $node['ratingData'] : [];

        $businessId = $this->string(data_get($state, 'config.query.orgpage.id'))
            ?? $this->string($node['id'] ?? null)
            ?? $expectedBusinessId;

        $rating = $this->float($ratingData['ratingValue'] ?? null) ?? $this->microdata($html, 'ratingValue');
        $ratingsTotal = $this->int($ratingData['ratingCount'] ?? null) ?? $this->microdata($html, 'ratingCount');
        $reviewsTotal = $this->int($ratingData['reviewCount'] ?? null) ?? $this->microdata($html, 'reviewCount');

        if ($rating === null && $ratingsTotal === null && $reviewsTotal === null) {
            // Данных организации на странице нет вообще. Это либо отсутствующая
            // карточка, либо новая разметка — различаем по признакам страницы.
            if ($this->looksLikeMissingOrganization($html)) {
                throw OrganizationNotFoundException::forUrl($pageUrl);
            }

            throw LayoutChangedException::forPage('в разметке нет рейтинга и счётчиков отзывов');
        }

        if ($businessId === null) {
            throw LayoutChangedException::forPage('не удалось определить id организации');
        }

        return new OrganizationCard(
            businessId: $businessId,
            title: $this->string($node['title'] ?? null),
            address: $this->string($node['fullAddress'] ?? null) ?? $this->string($node['address'] ?? null),
            rating: $rating,
            ratingsTotal: $ratingsTotal ?? 0,
            reviewsTotal: $reviewsTotal ?? 0,
        );
    }

    /**
     * Данные организации лежат в дереве состояния карточки, но точный путь
     * менялся между версиями фронта, поэтому ищем первый узел с ratingData.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function findBusinessNode(array $state, int $depth = 0): ?array
    {
        if ($depth > 12) {
            return null;
        }

        if (isset($state['ratingData'], $state['id'])) {
            return $state;
        }

        foreach ($state as $value) {
            if (! is_array($value)) {
                continue;
            }

            $found = $this->findBusinessNode($value, $depth + 1);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Рейтинг и счётчики из микроразметки schema.org — запасной источник,
     * если структура состояния изменится.
     */
    private function microdata(string $html, string $property): ?float
    {
        $pattern = '#<meta\b[^>]*itemProp="'.preg_quote($property, '#').'"[^>]*content="([^"]+)"#i';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[1]);
    }

    private function looksLikeCaptcha(string $html): bool
    {
        foreach (self::CAPTCHA_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeMissingOrganization(string $html): bool
    {
        foreach (self::MISSING_ORGANIZATION_MARKERS as $marker) {
            if (mb_stripos($html, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function apiBaseUrl(array $config, string $pageUrl): string
    {
        $origin = $this->string($config['origin'] ?? null) ?? $this->origin($pageUrl);
        $basePath = $this->string($config['apiBaseUrl'] ?? null) ?? '/maps';

        return rtrim($origin, '/').'/'.trim($basePath, '/');
    }

    private function origin(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST) ?: 'yandex.ru';

        return "{$scheme}://{$host}";
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
