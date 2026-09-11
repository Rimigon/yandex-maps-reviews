<script setup>
defineProps({
    rating: { type: Number, default: null },
    size: { type: String, default: 'md' },
});
</script>

<template>
    <span class="inline-flex items-center gap-0.5" :class="size === 'sm' ? 'text-xs' : 'text-sm'">
        <span
            v-for="star in 5"
            :key="star"
            :class="rating !== null && star <= Math.round(rating) ? 'text-amber-500' : 'text-slate-300'"
        >★</span>
    </span>
</template>
