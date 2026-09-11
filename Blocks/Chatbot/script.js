/**
 * Chatbot Block Script
 *
 * Talks only to taw-core's POST /wp-json/taw/v1/chat — no LLM base URL or
 * API key ever appears here. window.tawChatbot (localized by Chatbot.php)
 * carries the REST URL and, when the endpoint is restricted to logged-in
 * users, a nonce.
 */
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const MAX_HISTORY_TURNS = 10;

document.addEventListener('alpine:init', () => {
    Alpine.data('Chatbot', () => ({
        open: false,
        input: '',
        loading: false,
        error: null,
        messages: [],

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
                const headers = { 'Content-Type': 'application/json' };
                if (window.tawChatbot?.nonce) {
                    headers['X-WP-Nonce'] = window.tawChatbot.nonce;
                }

                const res = await fetch(window.tawChatbot?.restUrl ?? '/wp-json/taw/v1/chat', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ message, history }),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(data.message || data.error || res.statusText);
                }

                this.messages.push({ role: 'assistant', content: data.message ?? '' });
            } catch (e) {
                this.error = 'Something went wrong — please try again.';
            } finally {
                this.loading = false;
            }
        },
    }));
});
