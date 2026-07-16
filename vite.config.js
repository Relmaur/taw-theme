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
 *
 * Also sets `server.config.server.origin` here, once the real port is known —
 * see the comment on `server.origin` below for why this can't just be a
 * static config value. `listening` fires the instant the HTTP server starts
 * accepting connections, before any request can possibly arrive, so there's
 * no window where a stale/unset origin could be read by a real asset request.
 */
function hotFilePlugin(hotPath = 'public/build/hot') {
    const cleanup = () => { try { unlinkSync(hotPath); } catch {} };
    return {
        name: 'taw-hot-file',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                mkdirSync(dirname(hotPath), { recursive: true });
                const { port } = server.httpServer.address();
                const origin = `http://localhost:${port}`;
                writeFileSync(hotPath, origin);
                server.config.server.origin = origin;
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
        // Auto-increments to the next free port (5174, 5175, ...) if 5173 is
        // already taken by another TAW project's dev server — this is what
        // makes running `npm run dev` in multiple projects simultaneously
        // work, instead of the second one refusing to start. Safe now that
        // `origin` (below) is set dynamically rather than hardcoded, so it
        // can never go stale no matter which port Vite actually lands on.
        strictPort: false,
        cors: true,
        // Tell Vite to embed full absolute URLs for assets in injected CSS.
        // Without this, Vite writes `/resources/fonts/...` (absolute path)
        // which the browser resolves against the page origin (taw.local),
        // not the Vite server — causing font 404s. Deliberately not set as a
        // static value here: hotFilePlugin() above sets it the moment the
        // real listening port is known, so it always matches whichever port
        // Vite actually started on.
        watch: {
            usePolling: true,
        },
    },
}));