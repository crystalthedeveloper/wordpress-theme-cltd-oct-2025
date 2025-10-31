<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();

echo cltd_theme_get_legacy_layout_markup();    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
