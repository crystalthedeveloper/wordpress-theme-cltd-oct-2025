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

if (function_exists('cltd_theme_disable_sensitive_cache')) {
    cltd_theme_disable_sensitive_cache();
}

$play_form_action = home_url('/account/');

$high_score = get_user_meta($user_id, 'clownhunt_highscore', true);
$kills      = null;
$rank       = null;

$high_score = $high_score !== '' ? $high_score : __('Not recorded yet', 'cltd-theme-oct-2025');

if (function_exists('cltd_theme_fetch_player_profile_from_lambda')) {
    $remote_profile = cltd_theme_fetch_player_profile_from_lambda($user_id);
    if (is_array($remote_profile)) {
        if (array_key_exists('kills', $remote_profile)) {
            $kills = (int) $remote_profile['kills'];
        }
        if (array_key_exists('rank', $remote_profile)) {
            $rank = (int) $remote_profile['rank'];
        }
    }
}

$kills = is_numeric($kills) ? (int) $kills : 0;
$rank  = is_numeric($rank) ? (int) $rank : 0;

$reset_url = home_url('/forgot-your-password');
$logout_url = wp_logout_url(home_url('/'));
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <p class="cltd-auth__description">
        <?php esc_html_e('Welcome! Here is a snapshot of your profile and Clown Hunt stats.', 'cltd-theme-oct-2025'); ?>
    </p>

    <div class="cltd-auth__form_flex">
        <div class="cltd-auth__field_flex">
            <label><?php esc_html_e('Name', 'cltd-theme-oct-2025'); ?></label>
            <h3 class="cltd-auth__compact-message"><?php echo esc_html($user->display_name ?: $user->user_login); ?></h3>
        </div>
        <div class="cltd-auth__field_flex">
            <label><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
            <h3 class="cltd-auth__compact-message"><?php echo esc_html($user->user_email); ?></h3>
        </div>
    </div>

    <div class="cltd-auth__stats">
        <div class="cltd-auth__stat">
            <span class="cltd-auth__stat-label"><?php esc_html_e('Kills', 'cltd-theme-oct-2025'); ?></span>
            <strong class="cltd-auth__stat-value">
                <span id="user-kills"><?php echo esc_html($kills); ?></span>
            </strong>
        </div>
        <div class="cltd-auth__stat">
            <span class="cltd-auth__stat-label"><?php esc_html_e('Rank', 'cltd-theme-oct-2025'); ?></span>
            <strong class="cltd-auth__stat-value">
                <span id="user-rank"><?php echo esc_html($rank); ?></span>
            </strong>
        </div>
    </div>
    
    <div class="cltd-auth__game">
        <p class="cltd-auth__description"><?php esc_html_e('Ready to jump into Clown Hunt?', 'cltd-theme-oct-2025'); ?></p>
        <p class="cltd-auth__note"><?php esc_html_e('Your Clown Hunt login token expires in 5 minutes.', 'cltd-theme-oct-2025'); ?></p>
        <form method="post" action="<?php echo esc_url($play_form_action); ?>" data-cltd-play-form="1" data-popup="false">
            <?php wp_nonce_field('cltd_play_clownhunt', 'cltd_play_clownhunt_nonce'); ?>
            <input type="hidden" name="cltd_play_clownhunt" value="1">
            <button type="submit" class="cltd-button cltd-auth__button">
                <?php esc_html_e('Play Clown Hunt', 'cltd-theme-oct-2025'); ?>
            </button>
        </form>
    </div>
    
    <br>

    <div class="cltd-auth__links">
        <a href="<?php echo esc_url($reset_url); ?>">
            <?php esc_html_e('Need to reset your password?', 'cltd-theme-oct-2025'); ?>
        </a>

        <br>

        <a href="<?php echo esc_url($logout_url); ?>">
            <?php esc_html_e('Log out', 'cltd-theme-oct-2025'); ?>
        </a>
    </div>
</div>
