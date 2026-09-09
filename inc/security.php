<?php

/**
 * TAW Theme — Security Hardening
 *
 * This is your file. It is never touched by `update-theme` (same status as
 * inc/performance.php and inc/customizations.php — not listed in taw/core's
 * update-manifest.json, so the sync never reads or overwrites it). Loaded
 * from inc/customizations.php. Safe to trim per site.
 *
 * Focus: username / user-ID enumeration. Two layers:
 *
 *   1. The REST users endpoint — handled by the framework
 *      (TAW\Core\Security\Hardening), called explicitly below. Removes the
 *      public /wp/v2/users collection + single-user route for anonymous
 *      requests, across every routing form (/wp-json/, ?rest_route=,
 *      /batch/v1) because it filters at REST dispatch. /wp/v2/users/me and
 *      all logged-in access stay intact.
 *
 *   2. The classic ?author=N → /author/{slug}/ probe — handled here, not in
 *      the framework: it's site policy (it also kills author-archive query
 *      URLs) and overlaps with what security plugins like WP Defender's
 *      "Prevent User Enumeration" already do, so it stays opt-out-able at
 *      the theme level.
 */

// Layer 1 — REST users endpoint lockdown. taw/core's Theme::boot() already
// calls this by default; the explicit call here documents the intent and
// is idempotent. Opt this site back out (e.g. a headless front end that
// reads /wp/v2/users anonymously) with:
//   add_filter('taw_security_hide_users_endpoint', '__return_false');
TAW\Core\Security\Hardening::hideUsersEndpoint();

/**
 * Layer 2 — block the `?author=N` → /author/{slug}/ enumeration probe for
 * logged-out visitors on the front end. Author archives themselves stay
 * reachable via their pretty permalink; only the numeric-ID query form is
 * redirected. Remove this block if a site genuinely needs `?author=N` URLs.
 */
add_action('parse_request', function (): void {
    if (is_admin() || is_user_logged_in()) {
        return;
    }

    $probed_author = isset($_GET['author'])
        && preg_match('/^\d+$/', (string) wp_unslash($_GET['author']));

    if ($probed_author) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});
