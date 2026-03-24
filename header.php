<?php

use TAW\Blocks\Menu\Menu;

// Queue Menu assets before wp_head() so the <link> lands in <head>.
// (new Menu())->enqueueAssets(); // To implement the custom search functionality on Menu.php, uncomment this line to load the necessary assets.
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <?php if (is_singular() && pings_open(get_queried_object())) : ?>
        <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">
    <?php endif; ?>

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <a class="skip-link screen-reader-text" href="#content"><?php esc_html_e('Skip to content', 'taw-theme'); ?></a>

    <header id="masthead" class="site-header flex items-center justify-between py-3 px-5 border-b border-gray-200" role="banner">
        <div class="site-branding">
            <?php if (has_custom_logo()) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <p class="site-title">
                    <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                        <?php bloginfo('name'); ?>
                    </a>
                </p>
                <?php
                $description = get_bloginfo('description', 'display');
                if ($description || is_customize_preview()) :
                ?>
                    <p class="site-description"><?php echo $description; ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div><!-- .site-branding -->

        <div class="nav-and-phone flex gap-4 items-center">
            <nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e('Primary Menu', 'taw-theme'); ?>">
                <?php /* No needed!
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'menu_class'     => 'primary-menu',
                    'container'      => false,
                    'fallback_cb'    => false,
                ));
                */ ?>
                <?php

                $menu = TAW\Core\Menu\Menu::get('primary');
                ?>
                <?php if ($menu && $menu->hasItems()): ?>
                    <nav class="flex items-center gap-4">
                        <?php foreach ($menu->items() as $item): ?>
                            <div class="relative group" <?php if ($item->hasChildren()): ?>x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" <?php endif; ?>>
                                <a href="<?php echo esc_url($item->url()); ?>" class="<?php echo $item->isInActiveTrail() ? 'text-blue-600 font-semibold' : 'text-gray-700 hover:text-blue-600'; ?> transition-colors">
                                    <?php echo esc_html($item->title()); ?>
                                </a>
                                <?php if ($item->hasChildren()): ?>
                                    <div x-show="open" x-transition class="absolute top-full left-0 mt-2 bg-white shadow-xl rounded-lg py-2 min-w-48">
                                        <?php foreach ($item->children() as $child): ?>
                                            <a href="<?php echo esc_url($child->url()); ?>"
                                                class="block px-4 py-2 text-sm <?php echo $child->isActive() ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-50'; ?>">
                                                <?php echo esc_html($child->title()); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </nav><!-- #site-navigation -->
            <?php if (TAW\Core\OptionsPage\OptionsPage::get('company_phone')) : ?>
                <a href="tel:<?php echo esc_attr(TAW\Core\OptionsPage\OptionsPage::get('company_phone')); ?>">
                    <?php echo esc_html(TAW\Core\OptionsPage\OptionsPage::get('company_phone')); ?>
                </a>
            <?php endif; ?>
        </div>
    </header><!-- #masthead -->

    <main id="content" class="site-main" role="main">