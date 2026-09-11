<?php

namespace App\Http\Requests;

use App\Services\YandexMaps\Exceptions\InvalidYandexMapsUrlException;
use App\Services\YandexMaps\Support\YandexMapsUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                new YandexMapsUrlRule,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $parsed = YandexMapsUrl::parse((string) $value);
                    } catch (InvalidYandexMapsUrlException) {
                        return; // ошибку формата уже показало правило YandexMapsUrlRule
                    }

                    $exists = $this->user()->organizations()
                        ->matchingUrl($parsed)
                        ->exists();

                    if ($exists) {
                        $fail('Эта организация уже добавлена.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Вставьте ссылку на карточку организации в Яндекс.Картах.',
            'url.max' => 'Ссылка слишком длинная.',
        ];
    }
}
