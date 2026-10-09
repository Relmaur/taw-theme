<?php
/**
 * @var string $heading
 * @var string $placeholder
 */

use TAW\Blocks\Chatbot\Chatbot;
use TAW\Core\Form\Turnstile;
use TAW\Core\Rag\RagSettings;

// Kill switch on: no launcher. (A page cached before the switch still has
// one; the endpoint then answers `paused` and script.js says so.)
if (RagSettings::chatPaused()) {
    return;
}

if (Chatbot::needsTurnstile()) {
    Turnstile::enqueueScript();
}
?>

<div x-data="Chatbot()" x-cloak class="taw-chatbot fixed bottom-5 right-5 z-50 font-sans">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center justify-center w-14 h-14 rounded-full bg-blue-600 text-white text-2xl shadow-lg hover:bg-blue-700 transition-colors"
        :aria-expanded="open.toString()"
        aria-label="<?php esc_attr_e('Open chat', 'taw-theme'); ?>"
    >
        <span x-show="!open" aria-hidden="true">💬</span>
        <span x-show="open" x-cloak aria-hidden="true">✕</span>
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute bottom-16 right-0 w-80 sm:w-96 h-112 bg-white rounded-xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden"
    >
        <div class="px-4 py-3 bg-blue-600 text-white font-medium text-sm">
            <?php echo esc_html($heading); ?>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3" x-ref="log">
            <template x-for="(entry, index) in messages" :key="index">
                <div :class="entry.role === 'user' ? 'text-right' : 'text-left'">
                    <div
                        class="inline-block max-w-[85%] px-3 py-2 rounded-lg text-sm [&_a]:underline [&_p+p]:mt-2"
                        :class="entry.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900'"
                        x-html="renderMarkdown(entry.content)"
                    ></div>
                </div>
            </template>

            <div x-show="loading" x-cloak class="text-left">
                <div class="inline-block px-3 py-2 rounded-lg text-sm bg-gray-100 text-gray-500">
                    <?php esc_html_e('Thinking…', 'taw-theme'); ?>
                </div>
            </div>

            <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>
        </div>

        <?php if (Chatbot::needsTurnstile()) : ?>
            <!-- Turnstile mounts here on the first message, "interaction-only":
                 invisible unless Cloudflare needs a click. Never display:none,
                 or the challenge can't render when it is needed. -->
            <div
                x-ref="turnstile"
                class="flex justify-center"
                data-sitekey="<?php echo esc_attr((string) Turnstile::siteKey()); ?>"
                data-theme="light"
            ></div>
        <?php endif; ?>

        <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-gray-200 p-3">
            <label class="sr-only" for="taw-chatbot-input"><?php echo esc_html($placeholder); ?></label>
            <input
                id="taw-chatbot-input"
                type="text"
                x-model="input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                :maxlength="maxChars"
                :disabled="loading"
                autocomplete="off"
                class="flex-1 text-sm border border-gray-300 rounded-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <button
                type="submit"
                :disabled="loading || !input.trim()"
                class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-600 text-white disabled:opacity-40"
                aria-label="<?php esc_attr_e('Send', 'taw-theme'); ?>"
            >
                &uarr;
            </button>
        </form>
    </div>
</div>
