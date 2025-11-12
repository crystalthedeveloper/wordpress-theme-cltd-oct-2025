<?php

if (!defined('ABSPATH')) {
    exit;
}

$classes = ['cltd-auth', 'cltd-auth--login'];

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

$login_form = wp_login_form([
    'echo'           => false,
    'label_username' => __('Email', 'cltd-theme-oct-2025'),
    'label_password' => __('Password', 'cltd-theme-oct-2025'),
    'label_remember' => __('Remember Me', 'cltd-theme-oct-2025'),
    'remember'       => false,
    'redirect'       => home_url('/'),
    'value_username' => '',
]);
$login_form = str_replace(
    'class="button button-primary"',
    'class="button button-primary cltd-button cltd-auth__button"',
    $login_form
);
$login_form = str_replace(
    [
        '<p class="login-username">',
        '<p class="login-password">',
        '<p class="login-remember">',
        '<p class="login-submit">'
    ],
    [
        '<div class="login-username">',
        '<div class="login-password">',
        '<div class="login-remember">',
        '<div class="login-submit">'
    ],
    $login_form
);
$login_form = str_replace('</p>', '</div>', $login_form);

$lost_password_url = home_url('/forgot-your-password');
$signup_url        = home_url('/sign-up');
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <h3 class="cltd-auth__description">
        <?php esc_html_e('Enter your email and password to access your account.', 'cltd-theme-oct-2025'); ?>
    </h3>

    <div class="cltd-auth__form">
        <?php echo $login_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
        ?>
    </div>

    <br />

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
</div>