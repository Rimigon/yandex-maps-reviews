import { reactive } from 'vue';
import { api, ensureCsrfCookie } from './api/client';

/**
 * Состояние авторизации. Отдельная библиотека состояний тут не нужна:
 * пользователь один, данных на два поля.
 */
export const auth = reactive({
    user: null,
    ready: false,
});

export async function loadUser() {
    try {
        const { data } = await api.get('/user');
        auth.user = data.user;
    } catch {
        auth.user = null;
    } finally {
        auth.ready = true;
    }
}

export async function login(email, password) {
    await ensureCsrfCookie();

    const { data } = await api.post('/login', { email, password });
    auth.user = data.user;
}

export async function logout() {
    await api.post('/logout');
    auth.user = null;
}
