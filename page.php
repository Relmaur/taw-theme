<?php
/**
 * page.php — Template for standard WordPress pages.
 *
 * Pages in TAW are block-driven (the editor is removed for the 'page'
 * post type). Queue the blocks this page needs before get_header(), then
 * render them below.
 *
 * To support multiple page layouts, use get_page_template_slug() to branch,
 * or create dedicated page templates (e.g. page-about.php, page-contact.php).
 */

use TAW\Core\Block\BlockRegistry;

BlockRegistry::queue('hero');

get_header();
?>

<?php BlockRegistry::render('hero'); ?>

<?php get_footer();
