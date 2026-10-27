import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const ddevUrl = process.env.DDEV_PRIMARY_URL;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: ddevUrl
        ? {
              host: '0.0.0.0',
              port: 5173,
              strictPort: true,
              origin: `${ddevUrl.replace(/:\d+$/, '')}:5173`,
              cors: { origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/ },
              watch: { ignored: ['**/storage/framework/views/**'] },
          }
        : { watch: { ignored: ['**/storage/framework/views/**'] } },
});
