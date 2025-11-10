<?php

if (!defined('ABSPATH')) {
    exit;
}

$classes = ['cltd-auth', 'cltd-auth--signup'];
$feedback = function_exists('cltd_theme_get_auth_feedback') ? cltd_theme_get_auth_feedback('signup') : ['errors' => [], 'success' => [], 'old' => []];

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

$login_url    = home_url('/log-in/');
$old          = isset($feedback['old']) && is_array($feedback['old']) ? $feedback['old'] : [];
$old_value    = function ($key) use ($old) {
    return isset($old[$key]) ? $old[$key] : '';
};
?>

<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <h3 class="cltd-auth__description">
        <?php esc_html_e('Fill out your details to access your account and track your progress and purchases.', 'cltd-theme-oct-2025'); ?>
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
        <?php wp_nonce_field('cltd_auth_signup_action', 'cltd_auth_signup_nonce'); ?>
        <input type="hidden" name="cltd_auth_action" value="signup">

        <div class="cltd-auth__field">
            <label for="cltd-signup-email"><?php esc_html_e('Email', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-signup-email" type="email" name="cltd_signup_email" value="<?php echo esc_attr($old_value('email')); ?>" required autocomplete="email">
        </div>

        <div class="cltd-auth__field">
            <label for="cltd-signup-first-name"><?php esc_html_e('First Name', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-signup-first-name" type="text" name="cltd_signup_first_name" value="<?php echo esc_attr($old_value('first_name')); ?>" required autocomplete="given-name">
        </div>

        <div class="cltd-auth__field">
            <label for="cltd-signup-last-name"><?php esc_html_e('Last Name', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-signup-last-name" type="text" name="cltd_signup_last_name" value="<?php echo esc_attr($old_value('last_name')); ?>" required autocomplete="family-name">
        </div>

        <div class="cltd-auth__field">
            <label for="cltd-signup-password"><?php esc_html_e('Password', 'cltd-theme-oct-2025'); ?></label>
            <input id="cltd-signup-password" type="password" name="cltd_signup_password" required autocomplete="new-password" minlength="6">
        </div>

        <br>

        <label class="cltd-auth__checkbox">
            <input type="checkbox" name="cltd_signup_terms" value="1" <?php checked($old_value('terms'), '1'); ?> required>
            <span><?php esc_html_e('By creating an account, I agree to this website’s Privacy Policy and Terms of Service.', 'cltd-theme-oct-2025'); ?></span>
        </label>

        <br>

        <label class="cltd-auth__checkbox">
            <input type="checkbox" name="cltd_signup_marketing" value="1" <?php checked($old_value('marketing'), '1'); ?>>
            <span><?php esc_html_e('I consent to receive marketing emails.', 'cltd-theme-oct-2025'); ?></span>
        </label>

        <br>

        <button type="submit" class="cltd-button cltd-auth__button">
            <?php esc_html_e('Sign Up', 'cltd-theme-oct-2025'); ?>
        </button>
    </form>

    <p class="cltd-auth__footer">
        <?php esc_html_e('Already have an account?', 'cltd-theme-oct-2025'); ?>
        <a href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Log In', 'cltd-theme-oct-2025'); ?></a>
    </p>
</div>
