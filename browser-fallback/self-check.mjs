/**
 * Самопроверка сборщика без обращения к Яндексу: локальный сервер отдаёт
 * страницу-двойник карточки, которая сама запрашивает отзывы по мере
 * прокрутки. Проверяем, что скрипт такие ответы перехватывает.
 *
 * Запуск: node self-check.mjs
 */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { collectReviews } from './collect-reviews.mjs';

const TOTAL_PAGES = 3;
const REVIEWS_PER_PAGE = 2;

function reviewsPayload(page) {
    const reviews = Array.from({ length: REVIEWS_PER_PAGE }, (_, index) => ({
        reviewId: `local-page${page}-${index}`,
        author: { name: `Автор ${page}-${index}` },
        text: `Отзыв ${page}-${index}`,
        rating: 4,
        updatedTime: '2026-08-04T13:07:09.580Z',
    }));

    return {
        data: {
            params: { page, limit: REVIEWS_PER_PAGE, count: TOTAL_PAGES * REVIEWS_PER_PAGE, totalPages: TOTAL_PAGES },
            reviews,
        },
    };
}

const pageHtml = `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"></head><body>
<div class="scroll__container" style="height:200px;overflow:auto">
  <span itemScope itemProp="aggregateRating" itemType="http://schema.org/AggregateRating">
    <meta itemProp="reviewCount" content="${TOTAL_PAGES * REVIEWS_PER_PAGE}"/>
    <meta itemProp="ratingCount" content="42"/>
    <meta itemProp="ratingValue" content="4.5"/>
  </span>
  <div id="reviews"></div>
</div>
<script type="application/json" class="state-view">{"config":{"csrfToken":"local:1","query":{"orgpage":{"id":"1"}}}}</script>
<script>
  let page = 0;
  const list = document.querySelector('.scroll__container');
  list.addEventListener('scroll', async () => {
    if (page >= ${TOTAL_PAGES} || list.scrollTop + list.clientHeight < list.scrollHeight - 10) return;
    page += 1;
    const response = await fetch('/maps/api/business/fetchReviews?page=' + page);
    const payload = await response.json();
    document.getElementById('reviews').insertAdjacentHTML(
      'beforeend',
      payload.data.reviews.map((review) => '<article>' + review.text + '</article>').join(''),
    );
    await fetch('/maps/api/business/fetchReviews?page=' + (page + 1));
    list.scrollTop = list.scrollHeight;
  });
  list.dispatchEvent(new Event('scroll'));
</script>
</body></html>`;

const server = createServer((request, response) => {
    if (request.url.startsWith('/maps/api/business/fetchReviews')) {
        const page = Number(new URL(request.url, 'http://localhost').searchParams.get('page') ?? 1);

        if (page > TOTAL_PAGES) {
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ data: { params: { page, limit: 2, count: 6, totalPages: 3 }, reviews: [] } }));

            return;
        }

        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify(reviewsPayload(page)));

        return;
    }

    response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    response.end(pageHtml);
});

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const { port } = server.address();

try {
    const result = await collectReviews(`http://127.0.0.1:${port}/maps/org/test/1/reviews/`, { maxPages: 5 });

    assert.ok(result.html.includes('state-view'), 'в собранном HTML нет блока состояния страницы');
    assert.ok(result.payloads.length >= 1, 'ответы метода отзывов не перехвачены');

    const captured = result.payloads.flatMap((payload) => payload.data?.reviews ?? []);
    assert.ok(captured.length > 0, 'в перехваченных ответах нет отзывов');

    console.log(`OK: перехвачено страниц — ${result.payloads.length}, отзывов — ${captured.length}`);
} finally {
    server.close();
}
