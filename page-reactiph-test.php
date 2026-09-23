<?php
/**
 * page-reactiph-test.php — Reactiph live-verification spike (ADR 0021).
 *
 * Temporary dedicated page template so the Counter block (reactiph-counter)
 * can be verified end-to-end without touching page.php's default 'hero'
 * queue. Assign this template to a test Page in wp-admin, remove once
 * verification is done.
 */

use TAW\Core\Block\BlockRegistry;

BlockRegistry::queue('reactiph-counter');

get_header();
?>

<?php BlockRegistry::render('reactiph-counter'); ?>

<?php echo do_shortcode('[reactiph_guestbook]'); ?>

<?php get_footer();
