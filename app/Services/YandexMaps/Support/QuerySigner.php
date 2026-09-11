<?php

namespace App\Services\YandexMaps\Support;

/**
 * Подпись запросов к внутреннему API Яндекс.Карт.
 *
 * Фронтенд карточки добавляет к каждому ajax-запросу параметр `s`: строка
 * параметров с ключами, отсортированными по алфавиту, хешируется алгоритмом
 * djb2-xor и переводится в беззнаковое 32-битное число.
 *
 * Алгоритм восстановлен по бандлу Яндекс.Карт (функция подписи в модуле
 * общедоступного клиента карточки) и проверен на живых запросах.
 */
final class QuerySigner
{
    /**
     * Дописывает к параметрам запроса подпись `s`.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function sign(array $params): array
    {
        return [...$params, 's' => self::hash(self::stringify($params))];
    }

    /**
     * Собирает query-строку так же, как это делает библиотека qs на фронте:
     * ключи сортируются на каждом уровне, вложенные объекты превращаются в
     * `key[subkey]`, пустой объект — в `key=`, значение кодируется по RFC 3986.
     *
     * @param  array<string, mixed>  $params
     */
    public static function stringify(array $params): string
    {
        $pairs = [];
        self::flatten('', $params, $pairs);

        return implode('&', $pairs);
    }

    /**
     * djb2-xor, результат приводится к беззнаковому 32-битному числу.
     */
    public static function hash(string $value): string
    {
        $hash = 5381;

        foreach (str_split($value) as $char) {
            $hash = ((33 * $hash) ^ ord($char)) & 0xFFFFFFFF;
        }

        return (string) $hash;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $pairs
     */
    private static function flatten(string $prefix, mixed $value, array &$pairs): void
    {
        if ($value === []) {
            $pairs[] = self::encode($prefix).'=';

            return;
        }

        if (is_array($value) && ! array_is_list($value)) {
            $keys = array_keys($value);
            usort($keys, static fn ($left, $right) => strcasecmp((string) $left, (string) $right));

            foreach ($keys as $key) {
                self::flatten(self::join($prefix, (string) $key), $value[$key], $pairs);
            }

            return;
        }

        if (is_array($value)) {
            foreach ($value as $index => $item) {
                self::flatten(self::join($prefix, (string) $index), $item, $pairs);
            }

            return;
        }

        $pairs[] = self::encode($prefix).'='.self::encode(self::scalarToString($value));
    }

    private static function join(string $prefix, string $key): string
    {
        return $prefix === '' ? $key : $prefix.'['.$key.']';
    }

    private static function scalarToString(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value === true => 'true',
            $value === false => 'false',
            default => (string) $value,
        };
    }

    private static function encode(string $value): string
    {
        return rawurlencode($value);
    }
}
