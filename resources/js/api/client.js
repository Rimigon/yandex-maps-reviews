import axios from 'axios';
import { auth } from '../auth';

/**
 * Общий HTTP-клиент SPA: сессионная cookie + заголовки, которые ждёт Laravel.
 */
export const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * Sanctum выдаёт csrf-cookie перед первым не-GET запросом.
 */
export async function ensureCsrfCookie() {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            auth.user = null;
        }

        return Promise.reject(error);
    },
);

/**
 * Текст ошибки для интерфейса: сначала ошибки валидации, потом сообщение API.
 */
export function errorMessage(error) {
    const data = error.response?.data;

    if (data?.errors) {
        return Object.values(data.errors).flat().join(' ');
    }

    if (data?.message) {
        return data.message;
    }

    return 'Не удалось выполнить запрос. Проверьте соединение и попробуйте снова.';
}
