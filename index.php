<?php
// index.php (or front-page.php)

use TAW\Core\Block\BlockRegistry;

// 1. Declare which blocks this page needs (BEFORE get_header)
BlockRegistry::queue('hero', 'pricing_table');

// Example
// BlockRegistry::queue('hero', 'stats', 'testimonials', 'cta');

// 2. get_header triggers wp_enqueue_scripts → wp_head → styles in <head>
get_header();
?>

<?php // 3. Render blocks (HTML only, assets already handled) 
?>

<?php BlockRegistry::render('hero'); ?>
<?php BlockRegistry::render('pricing_table'); ?>

<?php get_footer(); ?>