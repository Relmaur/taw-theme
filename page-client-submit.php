<?php

/**
 * page-client-submit.php — Client post-submission portal.
 *
 * Template Name: Client Post Submission
 *
 * Password-gated front-end page: a client fills in a title, body, category,
 * and featured image, and gets a draft post created for later review. Form
 * registration + the on_submit handler that creates the post live in
 * inc/client-post-submission.php (see inc/customizations.php for how it's
 * wired in — commented out by default, since this is a per-client feature).
 *
 * Requires TAW_CLIENT_PORTAL_PASSWORD defined in this site's wp-config.php
 * — never hardcode the password here; see taw-core's README.md § "Page
 * Password Protection" for why.
 */

use TAW\Core\Auth\PagePassword;
use TAW\Core\Form\Form;

// Must run before ANY output, including get_header() — PagePassword needs
// to be able to send a redirect / set a cookie, and on a locked visit it
// renders its own gate screen and exits, so nothing below this call runs
// until the correct password has been entered.
PagePassword::protect([
    'password' => defined('TAW_CLIENT_PORTAL_PASSWORD') ? TAW_CLIENT_PORTAL_PASSWORD : '',
    'title'    => __('Client Submission Portal', 'taw-theme'),
]);

get_header();
?>

<div class="max-w-2xl mx-auto w-[90%] py-16">
    <h1 class="text-3xl font-bold mb-8"><?php esc_html_e('Submit a New Post', 'taw-theme'); ?></h1>
    <?php Form::display('client_post_submission'); ?>
</div>

<?php get_footer();
