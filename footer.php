<?php

use TAW\Core\OptionsPage\OptionsPage;

?>

</main><!-- #content -->

<footer id="colophon" class="site-footer border-t border-gray-100" role="contentinfo">
    <div class="footer-inner mx-auto max-w-360 w-[90%] flex items-center justify-between py-8">

        <p class="text-sm text-gray-500">
            &copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>. <?php echo esc_html(OptionsPage::get('footer_text') ?: 'All rights reserved.'); ?>
        </p>

        <?php

        use TAW\Core\Menu\Menu;

        $footerMenu = Menu::get('footer');
        ?>

        <?php if ($footerMenu && $footerMenu->hasItems()) : ?>
            <nav aria-label="<?php esc_attr_e('Footer Menu', 'taw-theme'); ?>">
                <ul class="flex items-center gap-6 list-none m-0 p-0">
                    <?php foreach ($footerMenu->items() as $item) : ?>
                        <li>
                            <a href="<?php echo esc_url($item->url()); ?>"
                                class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                <?php echo esc_html($item->title()); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</footer><!-- #colophon -->

<?php wp_footer(); ?>
</body>

</html>