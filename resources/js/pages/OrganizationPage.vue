<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api, errorMessage } from '../api/client';
import Pagination from '../components/Pagination.vue';
import ReviewCard from '../components/ReviewCard.vue';
import StarRating from '../components/StarRating.vue';
import SyncStatus from '../components/SyncStatus.vue';

const route = useRoute();

const organization = ref(null);
const reviews = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const snapshots = ref([]);

const loading = ref(true);
const reviewsLoading = ref(false);
const error = ref('');

let poller = null;

async function loadOrganization() {
    try {
        const { data } = await api.get(`/organizations/${route.params.id}`);
        organization.value = data.data;

        if (organization.value.sync.in_progress) {
            startPolling();
        } else {
            stopPolling();
        }
    } catch (exception) {
        error.value = errorMessage(exception);
    }
}

async function loadReviews(page = 1) {
    reviewsLoading.value = true;

    try {
        const { data } = await api.get(`/organizations/${route.params.id}/reviews`, { params: { page } });
        reviews.value = data.data;
        meta.value = data.meta;
    } catch (exception) {
        error.value = errorMessage(exception);
    } finally {
        reviewsLoading.value = false;
    }
}

async function loadSnapshots() {
    try {
        const { data } = await api.get(`/organizations/${route.params.id}/snapshots`);
        snapshots.value = data.data;
    } catch {
        snapshots.value = [];
    }
}

async function refresh() {
    await api.post(`/organizations/${route.params.id}/sync`, { force: true });
    await loadOrganization();
}

function startPolling() {
    if (poller === null) {
        poller = window.setInterval(async () => {
            await loadOrganization();

            if (!organization.value?.sync.in_progress) {
                stopPolling();
                await Promise.all([loadReviews(meta.value.current_page), loadSnapshots()]);
            }
        }, 1500);
    }
}

function stopPolling() {
    if (poller !== null) {
        window.clearInterval(poller);
        poller = null;
    }
}

watch(() => route.params.id, async () => {
    await Promise.all([loadOrganization(), loadReviews(), loadSnapshots()]);
    loading.value = false;
});

onMounted(async () => {
    await Promise.all([loadOrganization(), loadReviews(), loadSnapshots()]);
    loading.value = false;
});

onBeforeUnmount(stopPolling);
</script>

<template>
    <div class="space-y-6">
        <RouterLink :to="{ name: 'settings' }" class="text-sm text-slate-500 hover:text-slate-700">
            ← К настройкам
        </RouterLink>

        <p v-if="loading" class="text-sm text-slate-500">Загружаем данные…</p>
        <p v-else-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <template v-if="organization">
            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">
                            {{ organization.title ?? 'Карточка ' + organization.business_id }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">{{ organization.address }}</p>
                        <a
                            :href="organization.url"
                            target="_blank"
                            rel="noreferrer"
                            class="mt-1 inline-block text-sm text-slate-400 hover:text-slate-600"
                        >
                            {{ organization.url }}
                        </a>
                    </div>

                    <button
                        type="button"
                        :disabled="organization.sync.in_progress"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
                        @click="refresh"
                    >
                        Обновить данные
                    </button>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Средний рейтинг</dt>
                        <dd class="mt-1 flex items-baseline gap-2">
                            <span class="text-2xl font-semibold text-slate-900">{{ organization.rating ?? '—' }}</span>
                            <StarRating :rating="organization.rating" />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Всего оценок</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ organization.ratings_total }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Всего отзывов</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ organization.reviews_total }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Загружено отзывов</dt>
                        <dd class="mt-1 text-2xl font-semibold text-slate-900">{{ organization.reviews_parsed }}</dd>
                    </div>
                </dl>

                <div class="mt-4">
                    <SyncStatus :organization="organization" />
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-medium text-slate-900">Отзывы</h2>
                    <span v-if="reviewsLoading" class="text-sm text-slate-400">Обновляем…</span>
                </div>

                <p v-if="!reviewsLoading && reviews.length === 0" class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                    Отзывов пока нет. Если выгрузка ещё идёт, список появится автоматически.
                </p>

                <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />

                <Pagination :meta="meta" @change="loadReviews" />
            </section>

            <section v-if="snapshots.length > 1" class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-base font-medium text-slate-900">История выгрузок</h2>
                <p class="mt-1 text-sm text-slate-500">Что изменилось между парсингами карточки.</p>

                <table class="mt-4 w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="pb-2">Дата</th>
                            <th class="pb-2">Рейтинг</th>
                            <th class="pb-2">Оценок</th>
                            <th class="pb-2">Отзывов</th>
                            <th class="pb-2">Новых / обновлённых</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="snapshot in snapshots" :key="snapshot.id">
                            <td class="py-2 text-slate-500">{{ new Date(snapshot.captured_at).toLocaleString('ru-RU') }}</td>
                            <td class="py-2">
                                {{ snapshot.rating ?? '—' }}
                                <span
                                    v-if="snapshot.changes?.rating?.diff"
                                    :class="snapshot.changes.rating.diff > 0 ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    ({{ snapshot.changes.rating.diff > 0 ? '+' : '' }}{{ snapshot.changes.rating.diff }})
                                </span>
                            </td>
                            <td class="py-2 text-slate-600">{{ snapshot.ratings_total }}</td>
                            <td class="py-2 text-slate-600">{{ snapshot.reviews_total }}</td>
                            <td class="py-2 text-slate-600">
                                +{{ snapshot.changes?.reviews_added ?? 0 }} / {{ snapshot.changes?.reviews_updated ?? 0 }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>
