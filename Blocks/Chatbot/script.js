/**
 * Chatbot Block Script
 *
 * Talks only to taw-core's POST /wp-json/taw/v1/chat — no LLM base URL or
 * API key ever appears here. window.tawChatbot (localized by Chatbot.php)
 * carries the REST URLs, the human-check mode and Turnstile site key, the
 * message cap, the refusal copy, and (when the endpoint is restricted to
 * logged-in users) a nonce.
 *
 * Guardrails (taw/core v1.81.0, ADR-0017): while the human check is on,
 * each conversation first trades a Turnstile token for a signed session at
 * POST /wp-json/taw/v1/chat/session, then sends it as X-TAW-Chat-Session.
 * Refusals come back with a stable `code`, mapped to `config.copy`.
 */
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const config = window.tawChatbot || {};
const MAX_HISTORY_TURNS = 6; // mirrors ChatLimits::HISTORY_TURNS server-side
const SESSION_HEADER = 'X-TAW-Chat-Session';
const SESSION_STORAGE_KEY = 'tawChatSession';
const TURNSTILE_TIMEOUT_MS = 120000;

class ChatError extends Error {
    constructor(code) {
        super(code);
        this.code = code;
    }
}

// sessionStorage may be unavailable (private mode, blocked site data); the
// session then lives in memory only, which just means one more human check.
function readStoredSession() {
    try {
        const stored = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_KEY) || 'null');
        return stored && typeof stored.token === 'string' ? stored : null;
    } catch {
        return null;
    }
}

function storeSession(session) {
    try {
        if (session) sessionStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify(session));
        else sessionStorage.removeItem(SESSION_STORAGE_KEY);
    } catch {
        // memory only
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('Chatbot', () => ({
        open: false,
        input: '',
        loading: false,
        error: null,
        messages: [],
        maxChars: parseInt(config.maxChars, 10) || 1000,
        session: readStoredSession(), // { token, expiresAt }

        init() {
            this.$watch('messages', () => {
                this.$nextTick(() => {
                    const log = this.$refs.log;
                    if (log) log.scrollTop = log.scrollHeight;
                });
            });
        },

        renderMarkdown(text) {
            return DOMPurify.sanitize(marked.parse(text ?? ''));
        },

        async send() {
            const message = this.input.trim();
            if (!message || this.loading) return;

            this.error = null;
            const history = this.messages.slice(-MAX_HISTORY_TURNS).map(({ role, content }) => ({ role, content }));
            this.messages.push({ role: 'user', content: message });
            this.input = '';
            this.loading = true;

            try {
                let res = await this.postMessage(message, history);

                // Session expired or used up: one new human check, one retry.
                if (res.status === 401) {
                    this.forgetSession();
                    res = await this.postMessage(message, history);
                }

                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new ChatError(data.code || 'generic');

                this.messages.push({ role: 'assistant', content: data.message ?? '' });
            } catch (e) {
                // Unanswered: put the question back in the box so a retry
                // doesn't mean retyping it.
                this.messages.pop();
                this.input = message;
                this.error = config.copy?.[e?.code] || config.copy?.generic || 'Something went wrong — please try again.';
            } finally {
                this.loading = false;
            }
        },

        async postMessage(message, history) {
            const headers = { 'Content-Type': 'application/json' };
            if (config.nonce) {
                headers['X-WP-Nonce'] = config.nonce;
            }
            if (config.humanCheck === 'turnstile') {
                headers[SESSION_HEADER] = await this.sessionToken();
            }

            return fetch(config.restUrl ?? '/wp-json/taw/v1/chat', {
                method: 'POST',
                headers,
                body: JSON.stringify({ message, history }),
            });
        },

        // The conversation's session, or a new one bought with a Turnstile
        // token. The 5 s margin avoids sending a token that expires in flight.
        async sessionToken() {
            if (this.session && this.session.expiresAt > Date.now() + 5000) {
                return this.session.token;
            }

            const turnstileToken = await this.solveTurnstile();
            const res = await fetch(config.sessionUrl ?? '/wp-json/taw/v1/chat/session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ turnstile_token: turnstileToken }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || typeof data.token !== 'string') {
                throw new ChatError(data.code || 'generic');
            }

            this.session = { token: data.token, expiresAt: Date.now() + data.expires_in * 1000 };
            storeSession(this.session);

            return this.session.token;
        },

        forgetSession() {
            this.session = null;
            storeSession(null);
        },

        // Resolves with a single-use Turnstile token. Rendered once, with
        // callbacks that settle whichever request is waiting; later checks
        // reset the widget for a fresh token instead of re-rendering it.
        solveTurnstile() {
            const el = this.$refs.turnstile;
            if (!el || !window.TAWTurnstile || !config.turnstileSiteKey) {
                return Promise.reject(new ChatError('generic'));
            }

            return new Promise((resolve, reject) => {
                const timer = setTimeout(() => reject(new ChatError('human_check_failed')), TURNSTILE_TIMEOUT_MS);
                this.turnstileSettle = (token, failure) => {
                    clearTimeout(timer);
                    this.turnstileSettle = null;
                    if (token) resolve(token);
                    else reject(failure);
                };

                if (el.dataset.tawWidgetId) {
                    window.TAWTurnstile.reset(el);
                    return;
                }

                window.TAWTurnstile.render(el, {
                    appearance: 'interaction-only',
                    callback: (token) => this.turnstileSettle?.(token),
                    'error-callback': () => this.turnstileSettle?.(null, new ChatError('human_check_failed')),
                    'expired-callback': () => this.turnstileSettle?.(null, new ChatError('human_check_failed')),
                });
            });
        },
    }));
});
