<?php
/**
 * @var string $heading
 * @var string $placeholder
 */
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

        <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-gray-200 p-3">
            <label class="sr-only" for="taw-chatbot-input"><?php echo esc_html($placeholder); ?></label>
            <input
                id="taw-chatbot-input"
                type="text"
                x-model="input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
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
