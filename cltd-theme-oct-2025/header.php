<?php

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="top-nav top-nav--php">
    <div class="site-branding">
        <a class="site-branding__link" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
            <?php bloginfo('name'); ?>
        </a>
    </div>
    <?php if (has_nav_menu('primary')) : ?>
        <?php
        wp_nav_menu(
            [
                'theme_location' => 'primary',
                'container'      => 'nav',
                'container_class'=> 'auth-links',
                'menu_class'     => 'auth-links__menu',
                'fallback_cb'    => false,
            ]
        );
        ?>
    <?php endif; ?>
</header>
