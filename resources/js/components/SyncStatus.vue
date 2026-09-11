<script setup>
import { computed } from 'vue';

const props = defineProps({
    organization: { type: Object, required: true },
});

const badgeClass = computed(() => ({
    'idle': 'bg-slate-100 text-slate-600',
    'queued': 'bg-amber-50 text-amber-700',
    'running': 'bg-blue-50 text-blue-700',
    'completed': 'bg-emerald-50 text-emerald-700',
    'failed': 'bg-red-50 text-red-700',
}[props.organization.sync.status] ?? 'bg-slate-100 text-slate-600'));
</script>

<template>
    <div class="space-y-1">
        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="badgeClass">
            {{ organization.sync.status_label }}
        </span>

        <div v-if="organization.sync.in_progress" class="space-y-1">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                    class="h-full rounded-full bg-slate-500 transition-all"
                    :style="{ width: organization.sync.progress + '%' }"
                />
            </div>
            <p class="text-xs text-slate-500">
                Страница {{ organization.sync.pages_done }} из {{ organization.sync.pages_total || '…' }}
            </p>
        </div>

        <p v-if="organization.sync.error" class="text-xs text-red-600">
            {{ organization.sync.error }}
        </p>
    </div>
</template>
