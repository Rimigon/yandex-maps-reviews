/**
 * Запасной путь парсинга: сбор отзывов через headless-браузер.
 *
 * По умолчанию не используется — основной путь (внутренний JSON API карточки)
 * быстрее и не требует браузера. Скрипт нужен на случай, когда Яндекс начнёт
 * отвечать капчей или 429 на прямые запросы: браузер проходит проверку как
 * обычный пользователь, а мы перехватываем те же ответы fetchReviews, которые
 * забирает сама страница карточки.
 *
 * CLI: node collect-reviews.mjs <ссылка на карточку> [максимум страниц]
 * Вывод: JSON { html, payloads } в stdout — его разбирают парсеры на бэкенде.
 */
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const DEFAULT_USER_AGENT =
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

/**
 * Свежий headless Chromium Яндекс встречает ответом 429, поэтому запускаем его
 * без флага автоматизации и с отключённым признаком webdriver. Проверено
 * вживую: с этими аргументами карточка отдаётся как обычному браузеру.
 */
function defaultLaunch() {
    return chromium.launch({
        headless: true,
        ignoreDefaultArgs: ['--enable-automation'],
        args: ['--disable-blink-features=AutomationControlled', '--no-first-run', '--no-default-browser-check'],
    });
}

/**
 * @param {string} url ссылка на вкладку отзывов карточки
 * @param {{maxPages?: number, browserFactory?: () => Promise<import('playwright-core').Browser>}} options
 * @returns {Promise<{html: string, payloads: unknown[]}>}
 */
export async function collectReviews(url, { maxPages = 12, browserFactory } = {}) {
    const payloads = [];
    const launch = browserFactory ?? defaultLaunch;
    const browser = await launch();

    try {
        const page = await browser.newPage({
            locale: 'ru-RU',
            timezoneId: 'Europe/Moscow',
            viewport: { width: 1440, height: 900 },
            userAgent: process.env.YANDEX_MAPS_USER_AGENT ?? DEFAULT_USER_AGENT,
        });

        // Ответы метода отзывов приходят на саму карточку, поэтому просто слушаем сеть.
        page.on('response', async (response) => {
            if (!response.url().includes('/api/business/fetchReviews')) {
                return;
            }

            try {
                payloads.push(await response.json());
            } catch {
                // не-JSON (например, страница капчи) — пропускаем,
                // отсутствие данных поймает проверка на стороне бэкенда
            }
        });

        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('script.state-view', { state: 'attached', timeout: 60000 });

        // Отзывы подгружаются по мере прокрутки списка: ждём, пока Яндекс
        // отдаст все страницы, которые он вообще готов отдать.
        let idleSteps = 0;

        for (let step = 0; step < maxPages * 3 && idleSteps < 3; step++) {
            const before = payloads.length;

            await page.evaluate(() => {
                const list = document.querySelector('.scroll__container') ?? document.scrollingElement;
                list.scrollTop = list.scrollHeight;
            });
            await page.waitForTimeout(900);

            idleSteps = payloads.length === before ? idleSteps + 1 : 0;
        }

        return { html: await page.content(), payloads };
    } finally {
        await browser.close();
    }
}

const isDirectRun = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;

if (isDirectRun) {
    const url = process.argv[2];

    if (!url) {
        console.error('Укажите ссылку на карточку организации');
        process.exit(2);
    }

    try {
        const result = await collectReviews(url, { maxPages: Number(process.argv[3] ?? 12) });
        process.stdout.write(JSON.stringify(result));
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        process.exit(1);
    }
}
