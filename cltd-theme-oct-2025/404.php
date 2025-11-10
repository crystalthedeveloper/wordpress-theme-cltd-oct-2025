<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>
<main class="cltd-404">
    <div class="cltd-404__inner">
        <p class="cltd-404__eyebrow"><?php esc_html_e('Error 404', 'cltd-theme-oct-2025'); ?></p>
        <h1 class="cltd-404__title">
            <?php esc_html_e('We can’t seem to find that page.', 'cltd-theme-oct-2025'); ?>
        </h1>
        <p class="cltd-404__lead">
            <?php esc_html_e('It might have been moved, removed, renamed, or may never have existed.', 'cltd-theme-oct-2025'); ?>
        </p>

        <div class="cltd-404__actions">
            <a class="wp-block-cltd-button__link" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="wp-block-cltd-button__label">
                    <?php esc_html_e('Back to Home', 'cltd-theme-oct-2025'); ?>
                </span>
            </a>
            <a class="cltd-404__secondary-link" href="<?php echo esc_url(home_url('/contact')); ?>">
                <?php esc_html_e('Contact Crystal The Developer', 'cltd-theme-oct-2025'); ?>
            </a>
        </div>

        <div class="cltd-404__search">
            <?php get_search_form(); ?>
        </div>
    </div>
</main>
<?php

cltd_theme_render_popup_modal();

get_footer();
