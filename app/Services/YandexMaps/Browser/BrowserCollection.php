<?php

namespace App\Services\YandexMaps\Browser;

use App\Services\YandexMaps\Dto\OrganizationPage;
use App\Services\YandexMaps\Dto\ReviewPage;

/**
 * Результат сбора отзывов браузером: та же пара «карточка + страницы отзывов»,
 * что даёт прямой HTTP-путь, поэтому дальше данные обрабатываются одинаково.
 */
final class BrowserCollection
{
    /**
     * @param  list<ReviewPage>  $pages
     */
    public function __construct(
        public readonly OrganizationPage $page,
        public readonly array $pages,
    ) {}
}
