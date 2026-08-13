import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Laravel Echo + Reverb WebSocket setup (loaded lazily — only when VITE_REVERB_APP_KEY is set)
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
if (reverbKey) {
    import('laravel-echo').then(({ default: Echo }) => {
        import('pusher-js').then(({ default: Pusher }) => {
            window.Pusher = Pusher;
            window.Echo = new Echo({
                broadcaster:  'reverb',
                key:          reverbKey,
                wsHost:       import.meta.env.VITE_REVERB_HOST ?? 'localhost',
                wsPort:       import.meta.env.VITE_REVERB_PORT ?? 8080,
                wssPort:      import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS:     (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        });
    });
}
