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

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419 && typeof window !== 'undefined') {
            window.location.reload();

            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);
