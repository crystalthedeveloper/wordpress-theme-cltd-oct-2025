<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('cltd_theme_render_leaderboard_component')) {
    return;
}

echo cltd_theme_render_leaderboard_component(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
