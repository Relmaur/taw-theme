<?php

/**
 * Menu Block Template
 */

use TAW\Core\Menu\Menu;
use TAW\Core\OptionsPage\OptionsPage;

$menu = Menu::get('primary');
?>

<div class="menu flex flex-col" x-data="Menu">
    <div class="nav__top py-3 bg-primary flex-1">
        <div class="site-branding section-container--sm flex items-center justify-between">
            <div class="logo">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <p class="site-title">
                        <a class="text-white" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
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
            </div>
            <div class="search-bar-and-address flex items-center">
                <?php if (OptionsPage::get('company_address')) : ?>
                    <div class="text-white! pr-4 mr-4 border-r text-sm text-right font-light">
                        <?php echo wp_kses_post(OptionsPage::get('company_address')); ?>
                    </div>
                <?php endif; ?>
                <button class="search-icon text-white cursor-pointer" @click="open = true" aria-label="Open search">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Search Overlay -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="search-overlay"
        @click.self="closeSearch()"
        role="dialog"
        aria-modal="true"
        aria-label="Search"
    >
        <div class="search-overlay__panel">
            <div class="search-overlay__header">
                <label for="taw-search-input" class="search-overlay__label">What are you looking for?</label>
                <button class="search-overlay__close" @click="closeSearch()" aria-label="Close search">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="search-overlay__input-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="search-overlay__input-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    id="taw-search-input"
                    x-ref="searchInput"
                    x-model="query"
                    type="search"
                    class="search-overlay__input"
                    placeholder="Search posts and pages&hellip;"
                    autocomplete="off"
                />
                <div x-show="loading" class="search-overlay__spinner" aria-hidden="true"></div>
            </div>

            <!-- Results -->
            <ul x-show="results.length > 0" class="search-overlay__results">
                <template x-for="result in results" :key="result.id">
                    <li class="search-overlay__result">
                        <a :href="result.url" class="search-overlay__result-link">
                            <div class="search-overlay__result-body">
                                <span class="search-overlay__result-title" x-text="result.title"></span>
                                <span class="search-overlay__result-meta" x-text="result.subtype"></span>
                            </div>
                        </a>
                    </li>
                </template>
            </ul>

            <!-- Empty state -->
            <p
                x-show="!loading && results.length === 0 && query.trim().length > 0"
                class="search-overlay__empty"
            >No results found for &ldquo;<span x-text="query"></span>&rdquo;.</p>
        </div>
    </div>

    <div class="nav__bottom py-3 bg-secondary flex-1">
        <div class="section-container--sm flex gap-4 items-center">
            <nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e('Primary Menu', 'taw-theme'); ?>">
                <?php if ($menu && $menu->hasItems()) : ?>
                    <nav class="flex items-center gap-4">
                        <?php foreach ($menu->items() as $item) : ?>
                            <div
                                class="relative group"
                                <?php if ($item->hasChildren()) : ?>
                                    x-data="{ open: false }"
                                    @mouseenter="open = true"
                                    @mouseleave="open = false"
                                <?php endif; ?>
                            >
                                <a
                                    href="<?php echo esc_url($item->url()); ?>"
                                    class="text-white transition-colors<?php echo $item->isInActiveTrail() ? ' font-semibold' : ''; ?>"
                                >
                                    <?php echo esc_html($item->title()); ?>
                                </a>

                                <?php if ($item->hasChildren()) : ?>
                                    <div x-show="open" x-transition class="absolute top-full left-0 mt-2 bg-white shadow-xl rounded-lg py-2 min-w-48">
                                        <?php foreach ($item->children() as $child) : ?>
                                            <a
                                                href="<?php echo esc_url($child->url()); ?>"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50<?php echo $child->isActive() ? ' font-semibold text-primary' : ''; ?>"
                                            >
                                                <?php echo esc_html($child->title()); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </nav>

            <div class="contact flex items-center gap-4">
                <?php if (OptionsPage::get('company_email')) : ?>
                    <a class="text-white text-sm" href="mailto:<?php echo esc_attr(OptionsPage::get('company_email')); ?>">
                        <?php echo esc_html(OptionsPage::get('company_email')); ?>
                    </a>
                <?php endif; ?>
                <?php if (OptionsPage::get('company_phone')) : ?>
                    <a class="text-white text-sm" href="tel:<?php echo esc_attr(OptionsPage::get('company_phone')); ?>">
                        <?php echo esc_html(OptionsPage::get('company_phone')); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
