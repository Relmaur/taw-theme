<?php

declare(strict_types=1);

namespace TAW\Blocks\Chatbot;

use TAW\Core\Block\Block;
use TAW\Core\Form\Turnstile;
use TAW\Core\Rag\RagSettings;

/**
 * Site-wide RAG chatbot widget. Presentational only — every REST call it
 * makes goes to taw-core's `POST /wp-json/taw/v1/chat`, never directly to
 * an LLM provider (no API keys ever reach the browser).
 */
class Chatbot extends Block
{
    protected string $id = 'chatbot';

    protected function defaults(): array
    {
        return [
            'heading' => __('Ask us anything', 'taw-theme'),
            'placeholder' => __('Type your question…', 'taw-theme'),
        ];
    }

    /**
     * Hands script.js what it needs to pass the endpoint's gates (taw/core
     * ADR-0017, v1.81.0): where to post, whether to run the Turnstile human
     * check (and its public site key), the message-length cap, and the
     * visitor-facing copy for each refusal `code`, translatable here.
     *
     * A nonce is only needed when the endpoint is restricted to logged-in
     * users — {@see RagSettings::publicChatEnabled()} — since WP's
     * cookie-auth nonce check doesn't apply to (and isn't required for)
     * anonymous requests to a publicly-permitted route.
     */
    public function enqueueAssets(): void
    {
        parent::enqueueAssets();

        wp_localize_script('taw-block-' . $this->getId(), 'tawChatbot', [
            'restUrl' => rest_url('taw/v1/chat'),
            'sessionUrl' => rest_url('taw/v1/chat/session'),
            'humanCheck' => RagSettings::humanCheck(),
            'turnstileSiteKey' => (string) Turnstile::siteKey(),
            'maxChars' => RagSettings::maxMessageChars(),
            'nonce' => RagSettings::publicChatEnabled() ? null : wp_create_nonce('wp_rest'),
            'copy' => [
                'rate_limited' => __('You have sent a lot of messages in a row. Please try again in a few minutes.', 'taw-theme'),
                'budget_exhausted' => __('The assistant is paused for now. Please try again later.', 'taw-theme'),
                'paused' => __('The assistant is not available right now. Please try again later.', 'taw-theme'),
                'human_check_failed' => __('We could not confirm you are human. Please try again.', 'taw-theme'),
                'generic' => __('Something went wrong — please try again.', 'taw-theme'),
            ],
        ]);
    }

    /**
     * The TAWTurnstile helper prints an inline <script>, so the template
     * (rendered from footer.php) emits it, not enqueueAssets().
     */
    public static function needsTurnstile(): bool
    {
        return RagSettings::humanCheck() === 'turnstile' && Turnstile::isConfigured();
    }
}
