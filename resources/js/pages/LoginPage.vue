<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { errorMessage } from '../api/client';
import { login } from '../auth';

const router = useRouter();

const email = ref('test@example.com');
const password = ref('password');
const error = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await login(email.value, password.value);
        await router.push({ name: 'settings' });
    } catch (exception) {
        error.value = errorMessage(exception);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <h1 class="text-lg font-semibold text-slate-900">Отзывы с Яндекс.Карт</h1>
            <p class="mt-1 text-sm text-slate-500">Войдите, чтобы подключить карточку организации.</p>

            <form class="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-6" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">E-mail</label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Пароль</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500"
                    >
                </div>

                <p v-if="error" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                >
                    {{ loading ? 'Входим…' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>
