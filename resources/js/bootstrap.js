import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Drop a stale X-CSRF-TOKEN default so Laravel can validate the encrypted
// XSRF-TOKEN cookie that axios sends as X-XSRF-TOKEN on each POST.
window.axios.interceptors.request.use((config) => {
    if (typeof config.headers?.delete === 'function') {
        config.headers.delete('X-CSRF-TOKEN');
    }

    delete config.headers?.['X-CSRF-TOKEN'];

    if (config.headers?.common) {
        delete config.headers.common['X-CSRF-TOKEN'];
    }

    return config;
});

const ONLINE_PING_PATH = '/online/ping';
const CSRF_RELOAD_GUARD_KEY = 'csrf:auto-reload-at';
const CSRF_RELOAD_GUARD_WINDOW_MS = 10000;

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419 && typeof window !== 'undefined') {
            const requestUrl = error.config?.url ?? '';

            // Фоновый онлайн-пинг не должен перезагружать страницу: он молча
            // игнорирует ошибки и не влияет на пользователя. Иначе стабильный
            // 419 на пинге зацикливает reload при загрузке страницы.
            if (requestUrl.includes(ONLINE_PING_PATH)) {
                return Promise.reject(error);
            }

            // Защита от бесконечного цикла: если недавно уже перезагружались
            // из-за 419, а токен так и не починился — не перезагружаемся снова.
            let lastReloadAt = 0;
            try {
                lastReloadAt = Number(window.sessionStorage.getItem(CSRF_RELOAD_GUARD_KEY)) || 0;
            } catch (storageError) {
                lastReloadAt = 0;
            }

            if (Date.now() - lastReloadAt < CSRF_RELOAD_GUARD_WINDOW_MS) {
                return Promise.reject(error);
            }

            try {
                window.sessionStorage.setItem(CSRF_RELOAD_GUARD_KEY, String(Date.now()));
            } catch (storageError) {
                // ignore
            }

            window.location.reload();

            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);
