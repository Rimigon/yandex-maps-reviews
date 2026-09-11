<script setup>
const props = defineProps({
    meta: { type: Object, required: true },
});

const emit = defineEmits(['change']);

function goTo(page) {
    if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
        emit('change', page);
    }
}
</script>

<template>
    <nav v-if="meta.last_page > 1" class="flex items-center justify-between gap-4 text-sm">
        <button
            type="button"
            :disabled="meta.current_page <= 1"
            class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
            @click="goTo(meta.current_page - 1)"
        >
            Назад
        </button>

        <span class="text-slate-500">
            Страница {{ meta.current_page }} из {{ meta.last_page }}
            <span class="text-slate-400">({{ meta.total }} отзывов)</span>
        </span>

        <button
            type="button"
            :disabled="meta.current_page >= meta.last_page"
            class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
            @click="goTo(meta.current_page + 1)"
        >
            Вперёд
        </button>
    </nav>
</template>
