import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF: enviar token em toda requisição (evita 419 no primeiro login / página em cache)
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
}
window.axios.interceptors.request.use((config) => {
    const token = getCsrfToken();
    if (token) {
        config.headers['X-XSRF-TOKEN'] = token;
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});
