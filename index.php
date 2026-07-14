<?php
/**
 * index.php — WordPress's universal fallback template.
 *
 * On a brand-new install (no posts published yet, viewing the default
 * blog-index homepage) this renders a standalone welcome screen instead of
 * an empty post loop — the "you just installed this" moment, in the spirit
 * of Laravel's default welcome page. It's intentionally NOT wrapped in
 * get_header()/get_footer(): a first-run screen shouldn't depend on the nav
 * menu being configured or `npm run build` having been run yet.
 *
 * Once real posts exist (or a static front page is configured, which routes
 * WordPress to front-page.php/page.php instead of here), this same file
 * falls through to a normal, block-driven post loop below.
 */

if (is_home() && !have_posts()) :
    $taw_core_version = \Composer\InstalledVersions::isInstalled('taw/core')
        ? \Composer\InstalledVersions::getPrettyVersion('taw/core')
        : null;
    global $wp_version;
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>

    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php bloginfo('name'); ?> — Welcome to TAW</title>
        <style>
            :root {
                --taw-bg: #f8fafc;
                --taw-surface: #ffffff;
                --taw-border: #e2e8f0;
                --taw-text: #0f172a;
                --taw-muted: #64748b;
                --taw-accent: #2563eb;
                --taw-accent-soft: #eff6ff;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --taw-bg: #0b1120;
                    --taw-surface: #111827;
                    --taw-border: #1f2937;
                    --taw-text: #f1f5f9;
                    --taw-muted: #94a3b8;
                    --taw-accent: #60a5fa;
                    --taw-accent-soft: #172554;
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1.5rem;
                background: var(--taw-bg);
                color: var(--taw-text);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            }

            .taw-wrap {
                width: 100%;
                max-width: 42rem;
            }

            .taw-mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.75rem;
                height: 2.75rem;
                border-radius: 0.75rem;
                background: var(--taw-accent);
                color: #fff;
                font-weight: 700;
                font-size: 1.125rem;
                letter-spacing: -0.02em;
            }

            h1 {
                font-size: 1.75rem;
                font-weight: 700;
                margin: 1.25rem 0 0.375rem;
            }

            .taw-tagline {
                color: var(--taw-muted);
                margin: 0 0 2rem;
                font-size: 0.9375rem;
            }

            .taw-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
                gap: 0.75rem;
            }

            .taw-card {
                display: block;
                padding: 1rem 1.125rem;
                border-radius: 0.75rem;
                border: 1px solid var(--taw-border);
                background: var(--taw-surface);
                text-decoration: none;
                color: inherit;
                transition: border-color 0.15s ease, transform 0.15s ease;
            }

            .taw-card:hover {
                border-color: var(--taw-accent);
                transform: translateY(-1px);
            }

            .taw-card__title {
                font-weight: 600;
                font-size: 0.9375rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .taw-card__desc {
                color: var(--taw-muted);
                font-size: 0.8125rem;
                margin-top: 0.25rem;
                line-height: 1.4;
            }

            .taw-arrow {
                color: var(--taw-accent);
                font-size: 0.875rem;
            }

            .taw-note {
                margin-top: 1.5rem;
                padding: 0.875rem 1.125rem;
                border-radius: 0.75rem;
                background: var(--taw-accent-soft);
                font-size: 0.8125rem;
                color: var(--taw-text);
            }

            .taw-note code {
                background: rgba(127, 127, 127, 0.15);
                padding: 0.1rem 0.35rem;
                border-radius: 0.3rem;
                font-size: 0.8em;
            }

            footer {
                margin-top: 2rem;
                font-size: 0.75rem;
                color: var(--taw-muted);
            }
        </style>
    </head>

    <body>
        <div class="taw-wrap">
            <span class="taw-mark">TAW</span>
            <h1><?php bloginfo('name'); ?> is ready.</h1>
            <p class="taw-tagline">Tailwind + Alpine + WordPress — a component-based block architecture. Two example blocks (<code>Hero</code>, <code>Button</code>) are already scaffolded in <code>Blocks/</code> to show the pattern.</p>

            <div class="taw-grid">
                <a class="taw-card" href="https://taw.mlizardo.com/" target="_blank" rel="noopener">
                    <div class="taw-card__title">Documentation<span class="taw-arrow">→</span></div>
                    <div class="taw-card__desc">Full framework reference, from Metabox fields to the Vite pipeline.</div>
                </a>
                <a class="taw-card" href="https://taw.mlizardo.com/quickstart" target="_blank" rel="noopener">
                    <div class="taw-card__title">Quickstart<span class="taw-arrow">→</span></div>
                    <div class="taw-card__desc">Project setup, block creation, and the metabox framework in ten minutes.</div>
                </a>
                <a class="taw-card" href="https://github.com/Relmaur/taw-theme" target="_blank" rel="noopener">
                    <div class="taw-card__title">taw-theme<span class="taw-arrow">→</span></div>
                    <div class="taw-card__desc">This theme's canonical scaffold on GitHub.</div>
                </a>
                <a class="taw-card" href="https://github.com/Relmaur/taw-core" target="_blank" rel="noopener">
                    <div class="taw-card__title">taw-core<span class="taw-arrow">→</span></div>
                    <div class="taw-card__desc">The framework package — blocks, metaboxes, forms, mail, and more.</div>
                </a>
            </div>

            <p class="taw-note">Building this site with an AI coding agent? Point it at <code>AGENTS.md</code> in the theme root — it's the canonical reference every included skill (<code>make-metablock</code>, <code>build-page</code>, <code>figma-to-block</code>…) is written against.</p>

            <footer>
                TAW<?php echo $taw_core_version ? ' · taw/core v' . esc_html($taw_core_version) : ''; ?>
                · WordPress <?php echo esc_html($wp_version); ?>
                · PHP <?php echo esc_html(PHP_VERSION); ?>
            </footer>
        </div>
    </body>

    </html>
<?php
    return;
endif;

use TAW\Core\Block\BlockRegistry;

BlockRegistry::queue('hero');

get_header();
?>

<?php if (have_posts()) : ?>
    <?php BlockRegistry::render('hero'); ?>

    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('py-8 border-b border-gray-100'); ?>>
            <h2 class="text-2xl font-semibold"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="mt-2 text-gray-600"><?php the_excerpt(); ?></div>
        </article>
    <?php endwhile; ?>

    <?php the_posts_pagination(); ?>
<?php else : ?>
    <p class="py-16 text-center text-gray-500"><?php esc_html_e('Nothing found.', 'taw-theme'); ?></p>
<?php endif; ?>

<?php get_footer(); ?>
