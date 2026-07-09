import { defineConfig } from 'vite';
import fullReload from 'vite-plugin-full-reload';
import tailwindcss from '@tailwindcss/vite';
import { readdirSync, writeFileSync, unlinkSync, mkdirSync } from 'fs';
import { dirname } from 'path';

const componentAssets = readdirSync('Blocks', { recursive: true })
    .filter(f => f.endsWith('style.css') || f.endsWith('style.scss') || f.endsWith('script.js'))
    .map(f => `Blocks/${f}`);

/**
 * Writes public/build/hot with the dev server's actual URL on start, removes
 * it on stop. ViteLoader::isDevServerRunning() (taw/core) reads this file and
 * verifies it's actually reachable, instead of just probing whether *something*
 * is listening on port 5173 — a bare port probe can false-positive if an
 * unrelated process (Docker, another dev tool, anything) happens to occupy
 * that port, which makes the theme serve dead localhost:5173 asset URLs in
 * production even though `npm run build` succeeded. This also means dev mode
 * keeps working correctly if Vite has to pick a different port because 5173
 * is already taken.
 */
function hotFilePlugin(hotPath = 'public/build/hot') {
    const cleanup = () => { try { unlinkSync(hotPath); } catch {} };
    return {
        name: 'taw-hot-file',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                mkdirSync(dirname(hotPath), { recursive: true });
                const { port } = server.httpServer.address();
                writeFileSync(hotPath, `http://localhost:${port}`);
            });
            process.on('exit', cleanup);
            for (const sig of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
                process.on(sig, () => { cleanup(); process.exit(); });
            }
        },
    };
}

export default defineConfig(({ command }) => ({
    // './' makes font/asset URLs relative in the compiled CSS so they
    // resolve correctly from any subdirectory (e.g. WordPress theme paths).
    // Dev mode must stay '/' — Vite's HMR breaks with a relative base when
    // scripts are served cross-origin (localhost:5173 → taw.local).
    base: command === 'build' ? './' : '/',
    plugins: [
        hotFilePlugin(),
        tailwindcss(),
        fullReload(['**/*.php', 'resources/views/**/*.twig']),
    ],
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: [
                'resources/scss/critical.scss',
                'resources/js/app.js',
                ...componentAssets,
            ],
        },
    },
    server: {
        host: 'localhost',
        port: 5173,
        // Deliberately NOT auto-picking a free port on conflict. `origin` below
        // is hardcoded to port 5173 (required for correct absolute font URLs —
        // see the comment on `origin`); if Vite silently moved to a different
        // port, that value would go stale with no error, and only fonts/some
        // absolute-URL assets would quietly 404. Failing loudly here is safer:
        // if this fails to start, something else has port 5173 — find it with
        // `lsof -i :5173` and stop it (or reconfigure that other project),
        // rather than just letting two dev servers collide.
        strictPort: true,
        cors: true,
        // Tell Vite to embed full absolute URLs for assets in injected CSS.
        // Without this, Vite writes `/resources/fonts/...` (absolute path)
        // which the browser resolves against the page origin (taw.local),
        // not the Vite server (localhost:5173) — causing font 404s.
        origin: 'http://localhost:5173',
        watch: {
            usePolling: true,
        },
    },
}));