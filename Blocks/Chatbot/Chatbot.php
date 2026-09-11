<?php

declare(strict_types=1);

namespace TAW\Blocks\Chatbot;

use TAW\Core\Block\Block;
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
            'nonce' => RagSettings::publicChatEnabled() ? null : wp_create_nonce('wp_rest'),
        ]);
    }
}
