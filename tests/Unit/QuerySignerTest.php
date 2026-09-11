<?php

namespace Tests\Unit;

use App\Services\YandexMaps\Support\QuerySigner;
use PHPUnit\Framework\TestCase;

/**
 * Подпись запросов — самая хрупкая часть парсера: если Яндекс её поменяет,
 * отзывы перестанут приходить. Поэтому алгоритм закреплён тестом на реальном
 * запросе, снятом в браузере.
 */
class QuerySignerTest extends TestCase
{
    public function test_подпись_совпадает_с_подписью_браузера(): void
    {
        $params = [
            'ajax' => '1',
            'categoryIconsExtendedParams[advert_page_id]' => 'desktop_maps_main_2',
            'center' => '37.610736,55.766248',
            'checkDiscoveryCollections' => 'false',
            'csrfToken' => 'dada1406e3818a6366c454ef075471ba25adc067:1789132777',
            'disableAdvertIcons' => 'false',
            'discoveryFeed[expBoxes]' => '1676987,0,62;187288,0,12;1673099,0,52;1643271,0,66;745392,0,24;965705,0,11;1002327,0,29;1676634,0,83;1682906,0,90;1602027,0,92;1678960,0,25;1688706,0,6;1683352,0,70;663874,0,20;663860,0,64',
            'lang' => 'ru',
            'locale' => 'ru_RU',
            'sessionId' => '1789132777683000-3474804325426311570-balancer-l7leveler-kubr-yp-sas-146-BAL',
            'zoom' => '16',
        ];

        $query = QuerySigner::stringify($params);

        $this->assertSame('3607251623', QuerySigner::hash($query));
        $this->assertSame(
            QuerySigner::hash($query),
            QuerySigner::sign($params)['s'],
        );
    }

    public function test_сортирует_ключи_и_разворачивает_вложенные_объекты(): void
    {
        $query = QuerySigner::stringify([
            'page' => 2,
            'businessId' => '123',
            'nested' => ['b' => 2, 'a' => 1],
            'empty' => [],
            'flag' => true,
        ]);

        $this->assertSame(
            'businessId=123&empty=&flag=true&nested%5Ba%5D=1&nested%5Bb%5D=2&page=2',
            $query,
        );
    }

    public function test_подпись_устойчива_к_порядку_ключей_во_входном_массиве(): void
    {
        $first = QuerySigner::sign(['csrfToken' => 'a:b', 'page' => 1, 'locale' => 'ru_RU']);
        $second = QuerySigner::sign(['locale' => 'ru_RU', 'page' => 1, 'csrfToken' => 'a:b']);

        $this->assertSame($first['s'], $second['s']);
    }
}
