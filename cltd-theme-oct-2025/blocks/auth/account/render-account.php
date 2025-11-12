<?php

if (!defined('ABSPATH')) {
    exit;
}

$classes = ['cltd-auth', 'cltd-auth--account'];

if (!is_user_logged_in()) : ?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <h3 class="cltd-auth__compact-message">
            <?php esc_html_e('Please log in to view your account.', 'cltd-theme-oct-2025'); ?>
        </h3>
        <div class="cltd-auth__links">
            <p>
                <?php esc_html_e('Already have an account?', 'cltd-theme-oct-2025'); ?>
                <a href="<?php echo esc_url(home_url('/log-in')); ?>">
                    <?php esc_html_e('Log In', 'cltd-theme-oct-2025'); ?>
                </a>
            </p>
            <p>
                <?php esc_html_e('Don’t have an account?', 'cltd-theme-oct-2025'); ?>
                <a href="<?php echo esc_url(home_url('/sign-up')); ?>">
                    <?php esc_html_e('Sign Up', 'cltd-theme-oct-2025'); ?>
                </a>
            </p>
        </div>
    </div>
<?php
    return;
endif;

$user = wp_get_current_user();
$user_id = $user->ID;

$high_score = get_user_meta($user_id, 'clownhunt_highscore', true);
$kills      = get_user_meta($user_id, 'clownhunt_kills', true);
$rank       = get_user_meta($user_id, 'clownhunt_rank', true);

$high_score = $high_score !== '' ? $high_score : __('Not recorded yet', 'cltd-theme-oct-2025');
$kills      = $kills !== '' ? $kills : __('Not recorded yet', 'cltd-theme-oct-2025');
$rank       = $rank !== '' ? $rank : __('Unranked', 'cltd-theme-oct-2025');

$reset_url = home_url('/forgot-your-password/');
$logout_url = wp_logout_url(home_url('/'));
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <h2 class="cltd-auth__title"><?php esc_html_e('My Account', 'cltd-theme-oct-2025'); ?></h2>
    <p class="cltd-auth__description">
        <?php esc_html_e('Welcome back! Here is a snapshot of your profile and Clown Hunt stats.', 'cltd-theme-oct-2025'); ?>
    </p>

    <div class="cltd-auth__form">
        <div class="cltd-auth__field">
            <label><?php esc_html_e('Name', 'cltd-theme-oct-2025'); ?></label>
            <p class="cltd-auth__compact-message"><?php echo esc_html($user->display_name ?: $user->user_login); ?></p>
        </div>
        <div class="cltd-auth__field">
            <label><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
            <p class="cltd-auth__compact-message"><?php echo esc_html($user->user_email); ?></p>
        </div>
    </div>

    <div class="cltd-auth__stats">
        <div class="cltd-auth__stat">
            <span class="cltd-auth__stat-label"><?php esc_html_e('Your High Score', 'cltd-theme-oct-2025'); ?></span>
            <strong class="cltd-auth__stat-value"><?php echo esc_html($high_score); ?></strong>
        </div>
        <div class="cltd-auth__stat">
            <span class="cltd-auth__stat-label"><?php esc_html_e('Kills', 'cltd-theme-oct-2025'); ?></span>
            <strong class="cltd-auth__stat-value"><?php echo esc_html($kills); ?></strong>
        </div>
        <div class="cltd-auth__stat">
            <span class="cltd-auth__stat-label"><?php esc_html_e('Rank', 'cltd-theme-oct-2025'); ?></span>
            <strong class="cltd-auth__stat-value"><?php echo esc_html($rank); ?></strong>
        </div>
    </div>

    <div class="cltd-auth__links">
        <a href="<?php echo esc_url($reset_url); ?>">
            <?php esc_html_e('Need to reset your password?', 'cltd-theme-oct-2025'); ?>
        </a>
        <a href="<?php echo esc_url($logout_url); ?>">
            <?php esc_html_e('Log out of your account', 'cltd-theme-oct-2025'); ?>
        </a>
    </div>
</div>