<?php

namespace App\Http\Requests;

use App\Services\YandexMaps\Exceptions\InvalidYandexMapsUrlException;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Проверка, что в поле действительно ссылка на карточку организации
 * в Яндекс.Картах. Короткая ссылка (/maps/-/...) тоже валидна — id
 * организации определится при первой выгрузке.
 */
class YandexMapsUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Ссылка должна быть строкой.');

            return;
        }

        try {
            YandexMapsUrl::parse($value);
        } catch (InvalidYandexMapsUrlException $exception) {
            $fail($exception->getMessage());
        }
    }
}
