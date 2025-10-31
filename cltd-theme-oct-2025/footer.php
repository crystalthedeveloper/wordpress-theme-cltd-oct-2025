<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<footer class="footer footer--php">
    <p class="footer__text">© <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?></p>
    <?php if (has_nav_menu('footer')) : ?>
        <?php
        wp_nav_menu(
            [
                'theme_location' => 'footer',
                'container'      => 'nav',
                'container_class'=> 'footer-links',
                'menu_class'     => 'footer-links__menu',
                'fallback_cb'    => false,
            ]
        );
        ?>
    <?php endif; ?>
</footer>
<?php

wp_footer();
?>
</body>
</html>
