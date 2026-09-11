<?php

namespace App\Services\YandexMaps\Dto;

/**
 * Данные карточки организации, снятые со страницы Яндекс.Карт.
 */
final class OrganizationCard
{
    public function __construct(
        public readonly string $businessId,
        public readonly ?string $title,
        public readonly ?string $address,
        public readonly ?float $rating,
        public readonly int $ratingsTotal,
        public readonly int $reviewsTotal,
    ) {}
}
