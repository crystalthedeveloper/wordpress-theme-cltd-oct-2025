<?php

if (!defined('ABSPATH')) {
    exit;
}

$classes = ['cltd-auth', 'cltd-auth--forgot'];
$feedback = function_exists('cltd_theme_get_auth_feedback') ? cltd_theme_get_auth_feedback('forgot') : ['errors' => [], 'success' => [], 'old' => []];
$login_url = home_url('/log-in/');
$old = isset($feedback['old']) ? $feedback['old'] : [];

if (is_user_logged_in()) :
?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <p class="cltd-auth__compact-message">
            <?php esc_html_e('You are currently logged in. You can update your password from your account settings.', 'cltd-theme-oct-2025'); ?>
        </p>
    </div>
<?php
    return;
endif;
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">

    <h3 class="cltd-auth__description">
        <?php esc_html_e('Enter your email address and we’ll send you a reset link.', 'cltd-theme-oct-2025'); ?>
    </h3>

    <?php if (!empty($feedback['errors'])) : ?>
        <div class="cltd-auth__notice cltd-auth__notice--error">
            <ul>
                <?php foreach ($feedback['errors'] as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="cltd-auth__form" method="post" action="">
        <?php wp_nonce_field('cltd_auth_forgot_action', 'cltd_auth_forgot_nonce'); ?>
        <input type="hidden" name="cltd_auth_action" value="forgot">

        <div class="cltd-auth__field">
            <label for="cltd-forgot-email"><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-forgot-email" type="email" name="cltd_forgot_email" value="<?php echo isset($old['email']) ? esc_attr($old['email']) : ''; ?>" required autocomplete="email">
        </div>

        <button type="submit" class="cltd-button cltd-auth__button">
            <?php esc_html_e('Send Reset Link', 'cltd-theme-oct-2025'); ?>
        </button>
    </form>

    <p class="cltd-auth__footer">
        <?php esc_html_e('Remember your password?', 'cltd-theme-oct-2025'); ?>
        <a href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Log In', 'cltd-theme-oct-2025'); ?></a>
    </p>
</div>