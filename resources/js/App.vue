<script setup>
import { onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { auth, loadUser, logout } from './auth';

const router = useRouter();

onMounted(() => {
    if (!auth.ready) {
        loadUser();
    }
});

async function signOut() {
    await logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div v-if="auth.user" class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <RouterLink :to="{ name: 'settings' }" class="text-sm font-semibold text-slate-900">
                    Отзывы с Яндекс.Карт
                </RouterLink>

                <div class="flex items-center gap-4 text-sm text-slate-500">
                    <span>{{ auth.user.email }}</span>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1 text-slate-600 transition hover:bg-slate-100"
                        @click="signOut"
                    >
                        Выйти
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-8">
            <RouterView />
        </main>
    </div>

    <RouterView v-else />
</template>
