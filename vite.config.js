import os from 'node:os';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

function localNetworkAddress() {
    let addresses = [];
    try {
        addresses = Object.values(os.networkInterfaces())
            .flat()
            .filter((item) => item && item.family === 'IPv4' && !item.internal)
            .map((item) => item.address);
    } catch {
        return 'localhost';
    }
    const rank = (address) =>
        address.startsWith('192.168.') ? 0 :
        address.startsWith('10.') ? 1 :
        /^172\.(1[6-9]|2\d|3[01])\./.test(address) ? 2 : 3;

    return addresses.sort((a, b) => rank(a) - rank(b))[0] || 'localhost';
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const hmrHost = env.VITE_HMR_HOST || process.env.VITE_HMR_HOST || localNetworkAddress();

    return {
        plugins: [
            react(),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                buildDirectory: 'uebtv-build-v2',
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            hmr: { host: hmrHost },
        },
    };
});
