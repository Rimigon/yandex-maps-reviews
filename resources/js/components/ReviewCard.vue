<script setup>
import { computed } from 'vue';
import StarRating from './StarRating.vue';

const props = defineProps({
    review: { type: Object, required: true },
});

const publishedAt = computed(() => {
    if (!props.review.published_at) {
        return '';
    }

    return new Date(props.review.published_at).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <article class="rounded-lg border border-slate-200 bg-white p-5">
        <header class="flex items-start gap-3">
            <img
                v-if="review.author.avatar"
                :src="review.author.avatar"
                :alt="review.author.name"
                class="h-9 w-9 rounded-full bg-slate-100"
                loading="lazy"
            >
            <div v-else class="h-9 w-9 rounded-full bg-slate-200" />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="truncate text-sm font-medium text-slate-900">{{ review.author.name }}</span>
                    <span v-if="review.is_pinned" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">
                        закреплён
                    </span>
                </div>
                <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                    <StarRating :rating="review.rating" size="sm" />
                    <span>{{ publishedAt }}</span>
                </div>
            </div>
        </header>

        <div class="mt-3">
            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ review.text || '—' }}</p>
        </div>

        <p v-if="review.business_comment" class="mt-3 border-l-2 border-slate-300 pl-3 text-sm text-slate-600">
            <span class="font-medium text-slate-700">Ответ компании:</span>
            {{ review.business_comment }}
        </p>

        <footer v-if="review.likes || review.dislikes" class="mt-3 text-xs text-slate-400">
            Полезно: {{ review.likes }} · Не полезно: {{ review.dislikes }}
        </footer>
    </article>
</template>
