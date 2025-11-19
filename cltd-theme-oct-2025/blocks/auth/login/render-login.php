<?php

if (!defined('ABSPATH')) {
    exit;
}

$classes  = ['cltd-auth', 'cltd-auth--login'];
$feedback = function_exists('cltd_theme_get_auth_feedback') ? cltd_theme_get_auth_feedback('login') : ['errors' => [], 'success' => [], 'old' => []];
$old      = isset($feedback['old']) && is_array($feedback['old']) ? $feedback['old'] : [];
$errors   = isset($feedback['errors']) && is_array($feedback['errors']) ? $feedback['errors'] : [];

$login_email_value = isset($old['email']) ? $old['email'] : '';
$query_login_email = isset($_GET['cltd_login_email']) ? sanitize_text_field(wp_unslash($_GET['cltd_login_email'])) : '';
if (!$login_email_value && $query_login_email) {
    $login_email_value = $query_login_email;
}

$login_error_key = isset($_GET['cltd_login_error']) ? sanitize_key(wp_unslash($_GET['cltd_login_error'])) : '';
if (empty($errors) && $login_error_key) {
    if (function_exists('cltd_theme_resolve_login_error_message')) {
        $errors[] = cltd_theme_resolve_login_error_message($login_error_key);
    } else {
        $errors[] = __('Login failed. Please check your details and try again.', 'cltd-theme-oct-2025');
    }
}

if (is_user_logged_in()) :
    $current_user = wp_get_current_user();
?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <p class="cltd-auth__compact-message">
            <?php
            printf(
                /* translators: %s: display name */
                esc_html__('You are already logged in as %s.', 'cltd-theme-oct-2025'),
                esc_html($current_user->display_name ?: $current_user->user_email)
            );
            ?>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                <?php esc_html_e('Log out?', 'cltd-theme-oct-2025'); ?>
            </a>
        </p>
    </div>
<?php
    return;
endif;

$lost_password_url = home_url('/forgot-your-password');
$signup_url        = home_url('/sign-up');
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <h3 class="cltd-auth__description">
        <?php esc_html_e('Enter your email and password to access your account.', 'cltd-theme-oct-2025'); ?>
    </h3>

    <?php if (!empty($errors)) : ?>
        <div class="cltd-auth__notice cltd-auth__notice--error" data-cltd-login-errors="1">
            <ul>
                <?php foreach ($errors as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="cltd-auth__form" method="post" action="" data-cltd-auth-login="1">
        <?php wp_nonce_field('cltd_auth_login_action', 'cltd_auth_login_nonce'); ?>
        <input type="hidden" name="cltd_auth_action" value="login">

        <div class="cltd-auth__field">
            <label for="cltd-login-email"><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-login-email" type="email" name="cltd_login_email" value="<?php echo esc_attr($login_email_value); ?>" required autocomplete="email">
        </div>

        <div class="cltd-auth__field">
            <label for="cltd-login-password"><?php esc_html_e('Password', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-login-password" type="password" name="cltd_login_password" required autocomplete="current-password">
        </div>

        <button type="submit" class="cltd-button cltd-auth__button">
            <?php esc_html_e('Log In', 'cltd-theme-oct-2025'); ?>
        </button>
    </form>

    <div class="cltd-auth__links">
        <a href="<?php echo esc_url($lost_password_url); ?>">
            <?php esc_html_e('Forgot your password?', 'cltd-theme-oct-2025'); ?>
        </a>

        <br />
        <p>
            <?php esc_html_e('Don’t have an account?', 'cltd-theme-oct-2025'); ?>
            <a href="<?php echo esc_url($signup_url); ?>">
                <?php esc_html_e('Sign Up', 'cltd-theme-oct-2025'); ?>
            </a>
        </p>
    </div>

    <div class="cltd-auth__guest" data-cltd-guest-area>
        <button type="button" class="cltd-auth__guest-toggle" data-cltd-guest-toggle>
            <span class="cltd-auth__guest-toggle-label"><?php esc_html_e('Play as Guest', 'cltd-theme-oct-2025'); ?></span>
            <span class="cltd-auth__guest-toggle-icon" aria-hidden="true">▼</span>
        </button>
        <div class="cltd-auth__guest-panel" data-cltd-guest-panel hidden>
            <p class="cltd-auth__note"><?php esc_html_e('Enter your name and email to receive a temporary guest pass that expires in 5 minutes.', 'cltd-theme-oct-2025'); ?></p>
            <div data-cltd-guest-errors></div>
            <form class="cltd-auth__form" method="post" action="" data-cltd-guest-login="1">
                <div class="cltd-auth__field">
                    <label for="cltd-guest-first-name"><?php esc_html_e('First Name', 'cltd-theme-oct-2025'); ?></label>
                    <input id="cltd-guest-first-name" type="text" name="cltd_guest_first_name" required autocomplete="given-name">
                </div>
                <div class="cltd-auth__field">
                    <label for="cltd-guest-email"><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
                    <input id="cltd-guest-email" type="email" name="cltd_guest_email" required autocomplete="email">
                </div>
                <button type="submit" class="cltd-button cltd-auth__button cltd-auth__button--alt">
                    <?php esc_html_e('Get Guest Token', 'cltd-theme-oct-2025'); ?>
                </button>
            </form>
        </div>
    </div>
</div>
