<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api, errorMessage } from '../api/client';
import SyncStatus from '../components/SyncStatus.vue';

const organizations = ref([]);
const url = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');

let poller = null;

async function load({ silent = false } = {}) {
    if (!silent) {
        loading.value = true;
    }

    try {
        const { data } = await api.get('/organizations');
        organizations.value = data.data;
        schedulePolling();
    } catch (exception) {
        error.value = errorMessage(exception);
    } finally {
        loading.value = false;
    }
}

async function add() {
    saving.value = true;
    error.value = '';

    try {
        await api.post('/organizations', { url: url.value });
        url.value = '';
        await load({ silent: true });
    } catch (exception) {
        error.value = errorMessage(exception);
    } finally {
        saving.value = false;
    }
}

async function refresh(organization) {
    try {
        await api.post(`/organizations/${organization.id}/sync`, { force: true });
        await load({ silent: true });
    } catch (exception) {
        error.value = errorMessage(exception);
    }
}

async function remove(organization) {
    if (!window.confirm(`Удалить «${organization.title ?? organization.business_id}» вместе с отзывами?`)) {
        return;
    }

    try {
        await api.delete(`/organizations/${organization.id}`);
        await load({ silent: true });
    } catch (exception) {
        error.value = errorMessage(exception);
    }
}

/**
 * Пока идёт фоновая выгрузка, обновляем список раз в две секунды,
 * чтобы прогресс был виден.
 */
function schedulePolling() {
    const inProgress = organizations.value.some((organization) => organization.sync.in_progress);

    if (inProgress && poller === null) {
        poller = window.setInterval(() => load({ silent: true }), 2000);
    }

    if (!inProgress && poller !== null) {
        window.clearInterval(poller);
        poller = null;
    }
}

onMounted(() => load());
onBeforeUnmount(() => poller !== null && window.clearInterval(poller));
</script>

<template>
    <div class="space-y-8">
        <section>
            <h1 class="text-lg font-semibold text-slate-900">Настройки</h1>
            <p class="mt-1 text-sm text-slate-500">
                Вставьте ссылку на карточку организации в Яндекс.Картах — отзывы и рейтинг подтянутся автоматически.
            </p>

            <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="add">
                <input
                    v-model="url"
                    type="text"
                    placeholder="https://yandex.ru/maps/org/..."
                    class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                >
                <button
                    type="submit"
                    :disabled="saving || url.trim() === ''"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                >
                    {{ saving ? 'Добавляем…' : 'Добавить организацию' }}
                </button>
            </form>

            <p v-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        </section>

        <section class="space-y-3">
            <p v-if="loading" class="text-sm text-slate-500">Загружаем список…</p>

            <p v-else-if="organizations.length === 0" class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                Пока не подключено ни одной организации.
            </p>

            <article
                v-for="organization in organizations"
                :key="organization.id"
                class="rounded-lg border border-slate-200 bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <RouterLink
                            :to="{ name: 'organization', params: { id: organization.id } }"
                            class="text-base font-medium text-slate-900 hover:underline"
                        >
                            {{ organization.title ?? 'Карточка ' + organization.business_id }}
                        </RouterLink>
                        <p class="mt-0.5 text-sm text-slate-500">{{ organization.address ?? organization.url }}</p>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span>Рейтинг: <strong>{{ organization.rating ?? '—' }}</strong></span>
                            <span>Оценок: {{ organization.ratings_total }}</span>
                            <span>Отзывов на карточке: {{ organization.reviews_total }}</span>
                            <span>Загружено: {{ organization.reviews_parsed }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <SyncStatus :organization="organization" />

                        <div class="flex gap-2">
                            <RouterLink
                                :to="{ name: 'organization', params: { id: organization.id } }"
                                class="rounded-md border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:bg-slate-100"
                            >
                                Отзывы
                            </RouterLink>
                            <button
                                type="button"
                                :disabled="organization.sync.in_progress"
                                class="rounded-md border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
                                @click="refresh(organization)"
                            >
                                Обновить
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-1 text-sm text-red-600 transition hover:bg-red-50"
                                @click="remove(organization)"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="organization.last_synced_at" class="mt-3 text-xs text-slate-400">
                    Последняя выгрузка: {{ new Date(organization.last_synced_at).toLocaleString('ru-RU') }}
                </p>
            </article>
        </section>
    </div>
</template>
