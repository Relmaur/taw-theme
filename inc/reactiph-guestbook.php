<?php

/**
 * Reactiph RPC-round-trip verification spike (follow-up to ADR 0021's
 * Counter block check) — ported verbatim from
 * reactiph/examples/wordpress-plugin/reactiph-demo.php's Guestbook +
 * render_guestbook_shortcode(), minus its own rest_api_init hook (already
 * registered in inc/customizations.php). Temporary; remove once verified.
 */

declare(strict_types=1);

namespace ReactiphDemo;

use Reactiph\Component\BaseComponent;
use Reactiph\WordPressBridge\WordPressBridge;

final class Guestbook extends BaseComponent
{
    private const OPTION_NAME = 'reactiph_guestbook_count';

    public int $signatureCount = 0;

    public function template(): string
    {
        return <<<'HTML'
<div class="guestbook"><p>Signatures so far: <span class="signature-count">{$signatureCount}</span></p><button type="button" id="reactiph-guestbook-sign">Sign (real server round-trip)</button></div>
HTML;
    }

    public function sign(): void
    {
        $count = (int) get_option(self::OPTION_NAME, 0);
        ++$count;
        update_option(self::OPTION_NAME, $count);
        $this->signatureCount = $count;
    }
}

function render_guestbook_shortcode(): string
{
    $guestbook = new Guestbook();
    $guestbook->signatureCount = (int) get_option('reactiph_guestbook_count', 0);

    $rpcUrl = (new WordPressBridge())->rpcEndpointUrl();
    $nonce = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <?= $guestbook->render() ?>
    <script>
    document.getElementById('reactiph-guestbook-sign').addEventListener('click', function () {
        fetch(<?= wp_json_encode($rpcUrl) ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': <?= wp_json_encode($nonce) ?>,
            },
            body: JSON.stringify({
                component: 'ReactiphDemo\\Guestbook',
                method: 'sign',
                state: {},
                args: [],
            }),
        })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                document.querySelector('.signature-count').textContent = result.state.signatureCount;
            });
    });
    </script>
    <?php
    return (string) ob_get_clean();
}
