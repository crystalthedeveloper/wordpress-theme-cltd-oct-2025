<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CLTD_AWS_SAVE_PROFILE')) {
    define('CLTD_AWS_SAVE_PROFILE', 'https://1rdfzd1e59.execute-api.ca-central-1.amazonaws.com/prod/save_player_profile');
}

$resend_api_key = 're_dHLEfDPP_4oew3J9GNx981SdierjzDZyH';
if (!defined('CLTD_RESEND_API_KEY')) {
    define('CLTD_RESEND_API_KEY', $resend_api_key);
}

$GLOBALS['cltd_theme_auth_feedback_store'] = [];

/**
 * Get the default "from" address used for Resend emails.
 *
 * @return string
 */
function cltd_theme_get_resend_from_address() {
    $domain = wp_parse_url(home_url(), PHP_URL_HOST);
    $domain = $domain ? strtolower($domain) : '';
    if ($domain) {
        $domain = preg_replace('/^www\./', '', $domain);
    }
    $fallback_domain = 'crystalthedeveloper.ca';
    $domain = $domain ?: $fallback_domain;

    $from_email = sanitize_email('contact@' . $domain);
    if (!$from_email) {
        $from_email = sanitize_email('contact@' . $fallback_domain);
    }
    $from_name  = trim(wp_strip_all_tags(get_bloginfo('name')));

    return $from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
}

/**
 * Prevent WordPress core new user emails from sending alongside Resend templates.
 *
 * @param array   $email Email arguments.
 * @param WP_User $user  User object.
 * @param string  $blogname Blog name.
 *
 * @return array
 */
function cltd_theme_disable_core_new_user_email($email, $user, $blogname) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    $headers = [];
    if (!empty($email['headers'])) {
        if (is_array($email['headers'])) {
            $headers = $email['headers'];
        } else {
            $headers = array_filter(explode("\n", str_replace("\r\n", "\n", (string) $email['headers'])));
        }
    }

    $headers[] = 'X-CLTD-Template-Sent: wp-core-user-welcome-disabled';
    $headers[] = 'X-CLTD-Template-Reason: handled-via-resend';

    $email['headers'] = $headers;

    return $email;
}
add_filter('wp_new_user_notification_email', 'cltd_theme_disable_core_new_user_email', 9, 3);

/**
 * Prevent admin-facing new user notifications when Resend handles onboarding.
 *
 * @param array   $email Email arguments.
 * @param WP_User $user  User object.
 *
 * @return array
 */
function cltd_theme_disable_core_new_user_admin_email($email, $user, $blogname) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    $headers = [];
    if (!empty($email['headers'])) {
        if (is_array($email['headers'])) {
            $headers = $email['headers'];
        } else {
            $headers = array_filter(explode("\n", str_replace("\r\n", "\n", (string) $email['headers'])));
        }
    }

    $headers[] = 'X-CLTD-Template-Sent: wp-core-admin-welcome-disabled';
    $headers[] = 'X-CLTD-Template-Reason: handled-via-resend';

    $email['headers'] = $headers;

    return $email;
}
add_filter('wp_new_user_notification_email_admin', 'cltd_theme_disable_core_new_user_admin_email', 9, 3);

/**
 * Send a Resend API request with the provided payload.
 *
 * @param array $payload
 * @return bool|WP_Error
 */
function cltd_theme_dispatch_resend_request(array $payload) {
    if (!defined('CLTD_RESEND_API_KEY') || !CLTD_RESEND_API_KEY) {
        return new WP_Error('cltd_resend_missing_key', __('Resend API key is not configured.', 'cltd-theme-oct-2025'));
    }

    $response = wp_remote_post(
        'https://api.resend.com/emails',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . CLTD_RESEND_API_KEY,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 300) {
        $body    = wp_remote_retrieve_body($response);
        $message = __('Resend API returned an error.', 'cltd-theme-oct-2025');
        if ($body) {
            $decoded = json_decode($body, true);
            if (!empty($decoded['message'])) {
                $message = $decoded['message'];
            }
        }
        return new WP_Error('cltd_resend_http_error', $message, ['status' => $code]);
    }

    return true;
}

/**
 * Send a Resend template email with template variables.
 *
 * @param string       $template
 * @param string|array $recipients
 * @param array        $data
 * @return bool|WP_Error
 */
function cltd_theme_send_resend_template($template, $recipients, array $data = []) {
    $to_addresses = cltd_theme_normalize_email_list($recipients);
    if (empty($to_addresses)) {
        return new WP_Error('cltd_resend_missing_to', __('Resend email error: missing recipient.', 'cltd-theme-oct-2025'));
    }

    if (!$template) {
        return new WP_Error('cltd_resend_missing_template', __('Resend email error: missing template.', 'cltd-theme-oct-2025'));
    }

    $template_payload = [
        'id'   => $template,
        'name' => $template,
    ];

    if (!empty($data)) {
        $template_payload['data'] = cltd_theme_prepare_resend_template_data($data);
    }

    $payload = [
        'from'     => cltd_theme_get_resend_from_address(),
        'to'       => $to_addresses,
        'template' => $template_payload,
    ];

    return cltd_theme_dispatch_resend_request($payload);
}

/**
 * Prepare template data for Resend by including camelCase variants.
 *
 * @param array $data
 * @return object
 */
function cltd_theme_prepare_resend_template_data(array $data) {
    $prepared = [];

    foreach ($data as $key => $value) {
        if ($value === null) {
            continue;
        }

        $string_key = (string) $key;
        $prepared[$string_key] = $value;

        $camel = cltd_theme_to_camel_case($string_key);
        if ($camel && $camel !== $string_key && !array_key_exists($camel, $prepared)) {
            $prepared[$camel] = $value;
        }
    }

    return (object) $prepared;
}

/**
 * Convert snake_case or kebab-case strings to camelCase.
 *
 * @param string $value
 * @return string
 */
function cltd_theme_to_camel_case($value) {
    if (!is_string($value) || $value === '') {
        return '';
    }

    $parts = preg_split('/[-_\s]+/', $value);
    if (!$parts) {
        return trim($value);
    }

    $camel = strtolower(array_shift($parts));
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $camel .= ucfirst(strtolower($part));
    }

    return $camel;
}

/**
 * Send player profile data to the AWS Lambda endpoint.
 *
 * @param array $payload
 * @return void
 */
function cltd_theme_send_player_profile_to_lambda(array $payload) {
    if (!defined('CLTD_AWS_SAVE_PROFILE') || !CLTD_AWS_SAVE_PROFILE || empty($payload)) {
        return;
    }

    $response = wp_remote_post(CLTD_AWS_SAVE_PROFILE, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        error_log(sprintf('CLTD AWS save_player_profile error: %s', $response->get_error_message()));
    }
}

/**
 * Sync a brand new signup with AWS after wp_create_user() finishes.
 *
 * @param int   $user_id
 * @param array $userdata
 * @return void
 */
function cltd_theme_sync_player_profile_on_register($user_id, $userdata) {
    if (!defined('CLTD_AWS_SAVE_PROFILE')) {
        return;
    }

    $user = get_userdata($user_id);
    if (!$user || empty($user->user_email)) {
        return;
    }

    $first_name = '';
    if (!empty($userdata['first_name'])) {
        $first_name = sanitize_text_field($userdata['first_name']);
    } elseif (isset($_POST['cltd_signup_first_name'])) {
        $first_name = sanitize_text_field(wp_unslash($_POST['cltd_signup_first_name']));
    } else {
        $first_name = (string) get_user_meta($user_id, 'first_name', true);
    }

    $last_name = '';
    if (!empty($userdata['last_name'])) {
        $last_name = sanitize_text_field($userdata['last_name']);
    } elseif (isset($_POST['cltd_signup_last_name'])) {
        $last_name = sanitize_text_field(wp_unslash($_POST['cltd_signup_last_name']));
    } else {
        $last_name = (string) get_user_meta($user_id, 'last_name', true);
    }

    $timestamp = current_time('mysql');

    cltd_theme_send_player_profile_to_lambda([
        'user_id'    => $user_id,
        'email'      => $user->user_email,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'kills'      => 0,
        'rank'       => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
}
add_action('user_register', 'cltd_theme_sync_player_profile_on_register', 10, 2);

add_action('wp_login', function($user_login, $user) {
    if (!defined('CLTD_AWS_SAVE_PROFILE') || !$user instanceof WP_User) {
        return;
    }

    $timestamp  = current_time('mysql');
    $first_name = $user->first_name ?: (string) get_user_meta($user->ID, 'first_name', true);
    $last_name  = $user->last_name ?: (string) get_user_meta($user->ID, 'last_name', true);
    $kills      = (int) get_user_meta($user->ID, 'clownhunt_kills', true);
    $rank       = (int) get_user_meta($user->ID, 'clownhunt_rank', true);

    cltd_theme_send_player_profile_to_lambda([
        'user_id'    => $user->ID,
        'email'      => $user->user_email,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'kills'      => $kills > 0 ? $kills : 0,
        'rank'       => $rank > 0 ? $rank : 1,
        'updated_at' => $timestamp,
        'last_seen'  => $timestamp,
    ]);
}, 10, 2);

/**
 * Route all wp_mail() calls through the Resend API.
 *
 * @param null|bool|WP_Error $pre_wp_mail Short-circuit value.
 * @param array              $atts        Original wp_mail arguments.
 * @return bool|WP_Error|null
 */
function cltd_theme_route_wp_mail_to_resend($pre_wp_mail, $atts) {
    if (!defined('CLTD_RESEND_API_KEY') || !CLTD_RESEND_API_KEY) {
        return $pre_wp_mail;
    }

    if (isset($atts['headers']) && cltd_theme_headers_include_template_skip($atts['headers'])) {
        return true;
    }

    $result = cltd_theme_send_email_via_resend($atts);

    if (is_wp_error($result)) {
        error_log('CLTD Resend mail error: ' . $result->get_error_message());
        return $result;
    }

    return true;
}
add_filter('pre_wp_mail', 'cltd_theme_route_wp_mail_to_resend', 10, 2);

/**
 * Send a WordPress email via the Resend API.
 *
 * @param array $atts wp_mail arguments.
 * @return bool|WP_Error
 */
function cltd_theme_send_email_via_resend($atts) {
    $defaults = [
        'to'          => [],
        'subject'     => '',
        'message'     => '',
        'headers'     => [],
        'attachments' => [],
    ];

    $atts = wp_parse_args($atts, $defaults);

    $to_addresses = cltd_theme_normalize_email_list($atts['to']);
    if (empty($to_addresses)) {
        return new WP_Error('cltd_resend_missing_to', __('Resend email error: missing recipient.', 'cltd-theme-oct-2025'));
    }

    $headers = cltd_theme_parse_mail_headers($atts['headers']);
    $from    = $headers['from'];

    if (!$from) {
        $from = cltd_theme_get_resend_from_address();
    }

    $payload = [
        'from'    => $from,
        'to'      => $to_addresses,
        'subject' => (string) $atts['subject'],
        'html'    => (string) $atts['message'],
        'text'    => wp_strip_all_tags($atts['message']),
    ];

    if ($headers['reply_to']) {
        $payload['reply_to'] = $headers['reply_to'];
    }

    if (!empty($headers['cc'])) {
        $payload['cc'] = $headers['cc'];
    }

    if (!empty($headers['bcc'])) {
        $payload['bcc'] = $headers['bcc'];
    }

    $attachments = [];
    if (!empty($atts['attachments'])) {
        foreach ((array) $atts['attachments'] as $attachment) {
            $path = is_array($attachment) && isset($attachment['attachment'])
                ? $attachment['attachment']
                : $attachment;

            if (!is_string($path)) {
                continue;
            }

            $real_path = file_exists($path) ? $path : realpath($path);
            if (!$real_path || !is_readable($real_path)) {
                continue;
            }

            $content = file_get_contents($real_path);
            if (false === $content) {
                continue;
            }

            $attachments[] = [
                'filename' => basename($real_path),
                'content'  => base64_encode($content),
            ];
        }

        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }
    }

    return cltd_theme_dispatch_resend_request($payload);
}

/**
 * Normalize wp_mail headers into structured data.
 *
 * @param string|array $headers
 * @return array
 */
function cltd_theme_parse_mail_headers($headers) {
    $parsed = [
        'from'     => '',
        'reply_to' => '',
        'cc'       => [],
        'bcc'      => [],
    ];

    if (empty($headers)) {
        return $parsed;
    }

    if (!is_array($headers)) {
        $headers = str_replace("\r\n", "\n", (string) $headers);
        $headers = explode("\n", $headers);
    }

    foreach ($headers as $header) {
        if (!is_string($header) || false === strpos($header, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $header, 2);
        $name  = strtolower(trim($name));
        $value = trim($value);

        switch ($name) {
            case 'from':
                $parsed['from'] = $value;
                break;
            case 'reply-to':
                $parsed['reply_to'] = $value;
                break;
            case 'cc':
                $parsed['cc'] = cltd_theme_normalize_email_list($value);
                break;
            case 'bcc':
                $parsed['bcc'] = cltd_theme_normalize_email_list($value);
                break;
        }
    }

    return $parsed;
}

/**
 * Normalize a list of email addresses into Resend-ready strings.
 *
 * @param string|array $emails
 * @return array
 */
function cltd_theme_normalize_email_list($emails) {
    if (empty($emails)) {
        return [];
    }

    if (!is_array($emails)) {
        $emails = explode(',', (string) $emails);
    }

    $normalized = [];

    foreach ($emails as $email) {
        if (!is_string($email)) {
            continue;
        }

        $email = trim($email);
        if ('' === $email) {
            continue;
        }

        $name = '';
        $address = $email;

        if (false !== strpos($email, '<')) {
            if (preg_match('/(.*)<(.+)>/u', $email, $matches)) {
                $name    = trim(trim($matches[1]), "\"' \t");
                $address = trim($matches[2]);
            }
        }

        $sanitized = sanitize_email($address);
        if (!$sanitized) {
            continue;
        }

        if ($name) {
            $normalized[] = sprintf('%s <%s>', $name, $sanitized);
        } else {
            $normalized[] = $sanitized;
        }
    }

    return array_values(array_unique($normalized));
}

/**
 * Determine if mail headers contain the CLTD template skip flag.
 *
 * @param array|string $headers
 * @return bool
 */
function cltd_theme_headers_include_template_skip($headers) {
    if (empty($headers)) {
        return false;
    }

    if (!is_array($headers)) {
        $headers = str_replace("\r\n", "\n", (string) $headers);
        $headers = explode("\n", $headers);
    }

    foreach ($headers as $header) {
        if (!is_string($header)) {
            continue;
        }

        if (stripos($header, 'X-CLTD-Template-Sent:') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Get a user's first name with graceful fallbacks.
 *
 * @param WP_User $user
 * @return string
 */
function cltd_theme_get_user_first_name($user) {
    if (!$user instanceof WP_User) {
        return '';
    }

    if (!empty($user->first_name)) {
        return $user->first_name;
    }

    $meta_first = get_user_meta($user->ID, 'first_name', true);
    if ($meta_first) {
        return $meta_first;
    }

    if (!empty($user->display_name)) {
        return $user->display_name;
    }

    return $user->user_login;
}

/**
 * Internal store for auth feedback.
 *
 * @param string $context
 * @param array  $data
 * @return void
 */
function cltd_theme_set_auth_feedback($context, array $data) {
    global $cltd_theme_auth_feedback_store;
    if (!is_array($cltd_theme_auth_feedback_store)) {
        $cltd_theme_auth_feedback_store = [];
    }

    $context = $context ?: 'default';
    $existing = isset($cltd_theme_auth_feedback_store[$context]) ? $cltd_theme_auth_feedback_store[$context] : ['errors' => [], 'success' => [], 'old' => []];

    $sanitize_messages = function($messages) {
        if (empty($messages) || !is_array($messages)) {
            return [];
        }

        return array_values(array_filter(array_map(function($message) {
            return trim(wp_strip_all_tags((string) $message));
        }, $messages)));
    };

    $existing['errors']  = $sanitize_messages(isset($data['errors']) ? $data['errors'] : $existing['errors']);
    $existing['success'] = $sanitize_messages(isset($data['success']) ? $data['success'] : $existing['success']);
    $existing['old']     = isset($data['old']) && is_array($data['old']) ? $data['old'] : $existing['old'];

    $cltd_theme_auth_feedback_store[$context] = $existing;
}

/**
 * Retrieve feedback (errors/success/old input) for auth forms.
 *
 * @param string $context
 * @return array
 */
function cltd_theme_get_auth_feedback($context) {
    global $cltd_theme_auth_feedback_store;
    if (!is_array($cltd_theme_auth_feedback_store)) {
        $cltd_theme_auth_feedback_store = [];
    }

    $context = $context ?: 'default';

    if (!isset($cltd_theme_auth_feedback_store[$context])) {
        $cltd_theme_auth_feedback_store[$context] = ['errors' => [], 'success' => [], 'old' => []];
    }

    return $cltd_theme_auth_feedback_store[$context];
}

/**
 * Return the canonical login page URL used across CLTD auth flows.
 *
 * @return string
 */
function cltd_theme_get_login_page_url() {
    $default = home_url('/log-in/');

    /**
     * Allow overriding the canonical login page used by the theme.
     *
     * @param string $default
     */
    return apply_filters('cltd_theme_login_page_url', $default);
}

/**
 * Login error messages shared by the block and handler.
 *
 * @return array
 */
function cltd_theme_get_login_error_messages() {
    $messages = [
        'invalid_username'      => __('We couldn’t find an account with that email address. Please double-check and try again.', 'cltd-theme-oct-2025'),
        'invalid_email'         => __('We couldn’t find an account with that email address. Please double-check and try again.', 'cltd-theme-oct-2025'),
        'invalidcombo'          => __('That email and password combination did not match our records. Please try again.', 'cltd-theme-oct-2025'),
        'incorrect_password'    => __('The password you entered is incorrect. Please try again or reset it if you’ve forgotten.', 'cltd-theme-oct-2025'),
        'authentication_failed' => __('Invalid email or password. Please try again.', 'cltd-theme-oct-2025'),
        'empty_username'        => __('Please enter your email address.', 'cltd-theme-oct-2025'),
        'empty_password'        => __('Please enter your password.', 'cltd-theme-oct-2025'),
        'login_error'           => __('Login failed. Please check your details and try again.', 'cltd-theme-oct-2025'),
    ];

    return apply_filters('cltd_theme_login_error_messages', $messages);
}

/**
 * Resolve a login error code to a friendly message.
 *
 * @param string $code
 * @return string
 */
function cltd_theme_resolve_login_error_message($code) {
    $messages = cltd_theme_get_login_error_messages();
    $code = $code ? sanitize_key($code) : '';

    if ($code && isset($messages[$code])) {
        return $messages[$code];
    }

    return isset($messages['login_error']) ? $messages['login_error'] : __('Login failed. Please check your details and try again.', 'cltd-theme-oct-2025');
}

/**
 * Validate and attempt to log a user in with the provided credentials.
 *
 * @param string $email
 * @param string $password
 * @return WP_User|WP_Error
 */
function cltd_theme_process_login_submission($email, $password) {
    $email = sanitize_email((string) $email);
    $password = (string) $password;

    $errors = [];

    if ($email === '') {
        $errors[] = __('Please enter your email address.', 'cltd-theme-oct-2025');
    } elseif (!is_email($email)) {
        $errors[] = __('Please enter a valid email address.', 'cltd-theme-oct-2025');
    }

    if ($password === '') {
        $errors[] = __('Please enter your password.', 'cltd-theme-oct-2025');
    }

    if (!empty($errors)) {
        return new WP_Error('cltd_login_validation_failed', __('Validation failed.', 'cltd-theme-oct-2025'), $errors);
    }

    $user = wp_signon([
        'user_login'    => $email,
        'user_password' => $password,
        'remember'      => false,
    ]);

    if (is_wp_error($user)) {
        $codes = $user->get_error_codes();
        $message = cltd_theme_resolve_login_error_message(!empty($codes) ? $codes[0] : '');

        return new WP_Error('cltd_login_failed', $message);
    }

    return $user;
}

/**
 * Handle front-end auth form submissions.
 *
 * @return void
 */
function cltd_theme_handle_auth_requests() {
    if ('POST' !== $_SERVER['REQUEST_METHOD']) {
        return;
    }

    if (empty($_POST['cltd_auth_action'])) {
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['cltd_auth_action']));

    if (!$action) {
        return;
    }

    switch ($action) {
        case 'signup':
            cltd_theme_handle_signup_request();
            break;
        case 'forgot':
            cltd_theme_handle_forgot_request();
            break;
        case 'login':
            cltd_theme_handle_login_request();
            break;
    }
}
add_action('init', 'cltd_theme_handle_auth_requests');

/**
 * Process signup submissions coming from the block form.
 *
 * @return void
 */
function cltd_theme_handle_signup_request() {
    if (is_user_logged_in()) {
        cltd_theme_set_auth_feedback('signup', [
            'errors' => [__('You are already logged in.', 'cltd-theme-oct-2025')],
        ]);
        return;
    }

    $signup_nonce = isset($_POST['cltd_auth_signup_nonce']) ? wp_unslash($_POST['cltd_auth_signup_nonce']) : '';
    if (!$signup_nonce || !wp_verify_nonce($signup_nonce, 'cltd_auth_signup_action')) {
        cltd_theme_set_auth_feedback('signup', [
            'errors' => [__('Security check failed. Please try again.', 'cltd-theme-oct-2025')],
        ]);
        return;
    }

    $old = [
        'email'      => isset($_POST['cltd_signup_email']) ? sanitize_email(wp_unslash($_POST['cltd_signup_email'])) : '',
        'first_name' => isset($_POST['cltd_signup_first_name']) ? sanitize_text_field(wp_unslash($_POST['cltd_signup_first_name'])) : '',
        'last_name'  => isset($_POST['cltd_signup_last_name']) ? sanitize_text_field(wp_unslash($_POST['cltd_signup_last_name'])) : '',
        'terms'      => isset($_POST['cltd_signup_terms']) ? '1' : '',
        'marketing'  => isset($_POST['cltd_signup_marketing']) ? '1' : '',
    ];

    $password_raw = isset($_POST['cltd_signup_password']) ? (string) wp_unslash($_POST['cltd_signup_password']) : '';
    $errors       = [];

    if (empty($old['email']) || !is_email($old['email'])) {
        $errors[] = __('Please enter a valid email address.', 'cltd-theme-oct-2025');
    } elseif (email_exists($old['email'])) {
        $errors[] = __('An account with that email already exists.', 'cltd-theme-oct-2025');
    }

    if ($old['first_name'] === '') {
        $errors[] = __('First name is required.', 'cltd-theme-oct-2025');
    }

    if ($old['last_name'] === '') {
        $errors[] = __('Last name is required.', 'cltd-theme-oct-2025');
    }

    if (strlen($password_raw) < 6) {
        $errors[] = __('Password must be at least 6 characters long.', 'cltd-theme-oct-2025');
    }

    if ($old['terms'] !== '1') {
        $errors[] = __('You must agree to the Privacy Policy and Terms of Service.', 'cltd-theme-oct-2025');
    }

    if (!empty($errors)) {
        cltd_theme_set_auth_feedback('signup', [
            'errors' => $errors,
            'old'    => $old,
        ]);
        return;
    }

    $username = sanitize_user(current(explode('@', $old['email'])), true);
    if (!$username) {
        $username = sanitize_user($old['first_name'] . $old['last_name'], true);
    }
    if (!$username) {
        $username = 'player';
    }

    $base_username = $username;
    $suffix        = 1;
    while (username_exists($username)) {
        $username = $base_username . $suffix;
        $suffix++;
    }

    $user_id = wp_create_user($username, $password_raw, $old['email']);
    if (is_wp_error($user_id)) {
        cltd_theme_set_auth_feedback('signup', [
            'errors' => [$user_id->get_error_message()],
            'old'    => $old,
        ]);
        return;
    }

    $display_name = trim($old['first_name'] . ' ' . $old['last_name']);
    wp_update_user([
        'ID'           => $user_id,
        'first_name'   => $old['first_name'],
        'last_name'    => $old['last_name'],
        'display_name' => $display_name ?: $old['email'],
    ]);

    if ($old['marketing'] === '1') {
        update_user_meta($user_id, 'cltd_signup_marketing', '1');
    }

    wp_new_user_notification($user_id, null, 'both');

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    $user = get_userdata($user_id);
    if ($user instanceof WP_User) {
        /**
         * Manually trigger wp_login action to ensure downstream sync hooks (AWS) fire.
         */
        do_action('wp_login', $user->user_login, $user);

        $first_name_token = $old['first_name'] ?: cltd_theme_get_user_first_name($user);
        cltd_theme_send_resend_template('welcome-new-account', $user->user_email, [
            'first_name' => $first_name_token,
        ]);
    }

    $redirect_to = apply_filters('cltd_theme_signup_redirect', home_url('/account/'), $user_id);
    wp_safe_redirect($redirect_to ? $redirect_to : home_url('/'));
    exit;
}

/**
 * Process forgot password submissions.
 *
 * @return void
 */
function cltd_theme_handle_forgot_request() {
    $forgot_nonce = isset($_POST['cltd_auth_forgot_nonce']) ? wp_unslash($_POST['cltd_auth_forgot_nonce']) : '';
    if (!$forgot_nonce || !wp_verify_nonce($forgot_nonce, 'cltd_auth_forgot_action')) {
        cltd_theme_set_auth_feedback('forgot', [
            'errors' => [__('Security check failed. Please try again.', 'cltd-theme-oct-2025')],
        ]);
        return;
    }

    $old = [
        'email' => isset($_POST['cltd_forgot_email']) ? sanitize_email(wp_unslash($_POST['cltd_forgot_email'])) : '',
    ];

    $errors = [];

    if (empty($old['email']) || !is_email($old['email'])) {
        $errors[] = __('Please enter a valid email address.', 'cltd-theme-oct-2025');
    } else {
        $user = get_user_by('email', $old['email']);
        if (!$user) {
            $errors[] = __('No account found with that email address.', 'cltd-theme-oct-2025');
        }
    }

    if (!empty($errors)) {
        cltd_theme_set_auth_feedback('forgot', [
            'errors' => $errors,
            'old'    => $old,
        ]);
        return;
    }

    $result = retrieve_password($old['email']);

    if (is_wp_error($result)) {
        cltd_theme_set_auth_feedback('forgot', [
            'errors' => [$result->get_error_message()],
            'old'    => $old,
        ]);
        return;
    }

    cltd_theme_set_auth_feedback('forgot', [
        'success' => [__('Check your inbox for a password reset link.', 'cltd-theme-oct-2025')],
    ]);
}

/**
 * Process login submissions coming from the block form.
 *
 * @return void
 */
function cltd_theme_handle_login_request() {
    if (is_user_logged_in()) {
        cltd_theme_set_auth_feedback('login', [
            'errors' => [__('You are already logged in.', 'cltd-theme-oct-2025')],
        ]);
        return;
    }

    $login_nonce = isset($_POST['cltd_auth_login_nonce']) ? wp_unslash($_POST['cltd_auth_login_nonce']) : '';
    if (!$login_nonce || !wp_verify_nonce($login_nonce, 'cltd_auth_login_action')) {
        cltd_theme_set_auth_feedback('login', [
            'errors' => [__('Security check failed. Please try again.', 'cltd-theme-oct-2025')],
        ]);
        return;
    }

    $old = [
        'email' => isset($_POST['cltd_login_email']) ? sanitize_email(wp_unslash($_POST['cltd_login_email'])) : '',
    ];

    $password = isset($_POST['cltd_login_password']) ? (string) wp_unslash($_POST['cltd_login_password']) : '';
    $result   = cltd_theme_process_login_submission($old['email'], $password);

    if (is_wp_error($result)) {
        $messages = $result->get_error_data();
        if (!is_array($messages) || empty($messages)) {
            $messages = [$result->get_error_message()];
        }

        cltd_theme_set_auth_feedback('login', [
            'errors' => $messages,
            'old'    => $old,
        ]);
        return;
    }

    $redirect_to = apply_filters('cltd_theme_login_redirect', home_url('/account/'), $result);
    $redirect_to = $redirect_to ? $redirect_to : home_url('/account/');

    wp_safe_redirect($redirect_to);
    exit;
}

/**
 * AJAX handler for login submissions.
 *
 * @return void
 */
function cltd_theme_handle_login_ajax() {
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'cltd_auth_login_action')) {
        wp_send_json_error([
            'messages' => [__('Security check failed. Please try again.', 'cltd-theme-oct-2025')],
        ]);
    }

    $email = isset($_POST['cltd_login_email']) ? sanitize_email(wp_unslash($_POST['cltd_login_email'])) : '';
    $password = isset($_POST['cltd_login_password']) ? (string) wp_unslash($_POST['cltd_login_password']) : '';

    $result = cltd_theme_process_login_submission($email, $password);

    if (is_wp_error($result)) {
        $messages = $result->get_error_data();
        if (!is_array($messages) || empty($messages)) {
            $messages = [$result->get_error_message()];
        }

        $messages = array_values(array_filter(array_map(static function($message) {
            return trim(wp_strip_all_tags((string) $message));
        }, $messages)));

        wp_send_json_error([
            'messages' => $messages ? $messages : [__('Login failed. Please try again.', 'cltd-theme-oct-2025')],
        ]);
    }

    $redirect_to = apply_filters('cltd_theme_login_redirect', home_url('/account/'), $result);
    $redirect_to = $redirect_to ? $redirect_to : home_url('/account/');

    wp_send_json_success([
        'redirect' => esc_url_raw($redirect_to),
    ]);
}
add_action('wp_ajax_nopriv_cltd_auth_login', 'cltd_theme_handle_login_ajax');
add_action('wp_ajax_cltd_auth_login', 'cltd_theme_handle_login_ajax');

add_filter('retrieve_password_notification_email', 'cltd_theme_send_password_reset_template', 10, 3);
function cltd_theme_send_password_reset_template($email_data, $user, $reset_key) {
    if (!$user instanceof WP_User) {
        return $email_data;
    }

    $reset_link = network_site_url(
        'wp-login.php?action=rp&key=' . rawurlencode($reset_key) . '&login=' . rawurlencode($user->user_login),
        'login'
    );

    cltd_theme_send_resend_template('password-reset-request', $user->user_email, [
        'first_name' => cltd_theme_get_user_first_name($user),
        'reset_link' => $reset_link,
    ]);

    $headers = [];
    if (!empty($email_data['headers'])) {
        if (is_array($email_data['headers'])) {
            $headers = $email_data['headers'];
        } else {
            $headers = array_filter(explode("\n", str_replace("\r\n", "\n", (string) $email_data['headers'])));
        }
    }

    $headers[] = 'X-CLTD-Template-Sent: password-reset-request';
    $email_data['headers'] = $headers;

    return $email_data;
}

add_action('delete_user', 'cltd_theme_send_account_deletion_template', 10, 2);
function cltd_theme_send_account_deletion_template($user_id) {
    $user = get_userdata($user_id);
    if (!$user || empty($user->user_email)) {
        return;
    }

    cltd_theme_send_resend_template('account-deletion-confirmation', $user->user_email, [
        'first_name' => cltd_theme_get_user_first_name($user),
    ]);
}

/**
 * Theme setup.
 */
function cltd_theme_setup() {
    load_theme_textdomain('cltd-theme-oct-2025', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('block-templates');
    add_theme_support('editor-styles');
    add_editor_style([
        'css/editor-styles.css',
        'style.css',
    ]);

    register_nav_menus(
        [
            'primary' => __('Primary Menu', 'cltd-theme-oct-2025'),
            'footer'  => __('Footer Menu', 'cltd-theme-oct-2025'),
        ]
    );
}
add_action('after_setup_theme', 'cltd_theme_setup');

/**
 * Output Google Tag Manager script in the document head.
 */
function cltd_theme_output_gtm_head_snippet() {
    if (is_admin()) {
        return;
    }
    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-5MQ22TJV');</script>
    <!-- End Google Tag Manager -->
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S4W8HXN2RQ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-S4W8HXN2RQ');
    </script>
    <?php
}
add_action('wp_head', 'cltd_theme_output_gtm_head_snippet', 0);

/**
 * Output Google Tag Manager noscript fallback immediately after body open.
 */
function cltd_theme_output_gtm_body_snippet() {
    if (is_admin()) {
        return;
    }
    ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5MQ22TJV"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php
}
add_action('wp_body_open', 'cltd_theme_output_gtm_body_snippet', 0);

/**
 * Provide a single source of truth for styles that should load asynchronously.
 *
 * @return array
 */
function cltd_theme_get_async_style_handles() {
    // Temporarily disable async CSS loading to avoid FOUC.
    $handles = [];

    return apply_filters('cltd_theme_async_styles', $handles);
}

/**
 * Transform link tags for selected styles so they load without blocking render.
 *
 * @param string $html  Original HTML tag.
 * @param string $handle Style handle.
 * @param string $href   Source URL.
 * @param string $media  Media attribute.
 * @return string
 */
function cltd_theme_async_style_tag($html, $handle, $href, $media) {
    if (is_admin()) {
        return $html;
    }

    if (!in_array($handle, cltd_theme_get_async_style_handles(), true)) {
        return $html;
    }

    $html  = sprintf(
        "<link rel='stylesheet' id='%s' href='%s' media='print' onload=\"this.media='all'\" />",
        esc_attr($handle . '-css'),
        esc_url($href)
    );
    $html .= sprintf(
        "<noscript><link rel='stylesheet' id='%s-noscript' href='%s' media='%s' /></noscript>",
        esc_attr($handle . '-css'),
        esc_url($href),
        esc_attr($media)
    );

    return $html;
}
add_filter('style_loader_tag', 'cltd_theme_async_style_tag', 10, 4);

/**
 * Handles that should be loaded with defer.
 *
 * @return array
 */
function cltd_theme_get_deferred_scripts() {
    $handles = [
        'cltd-main',
        'cltd-stripe-products',
    ];

    return apply_filters('cltd_theme_deferred_scripts', $handles);
}

/**
 * Add defer attribute to selected scripts.
 *
 * @param string $tag    Script tag.
 * @param string $handle Handle.
 * @param string $src    Source URL.
 * @return string
 */
function cltd_theme_add_defer_to_scripts($tag, $handle, $src) {
    if (is_admin()) {
        return $tag;
    }

    if (!in_array($handle, cltd_theme_get_deferred_scripts(), true)) {
        return $tag;
    }

    if (false === stripos($tag, 'defer')) {
        $tag = str_replace('<script ', '<script defer ', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'cltd_theme_add_defer_to_scripts', 10, 3);

/**
 * Ensure CLTD block category is always available in the editor.
 *
 * @param array                   $categories Existing categories.
 * @param WP_Block_Editor_Context $editor_context Editor context.
 * @return array
 */
function cltd_theme_register_block_category($categories, $editor_context) {
    $slug = 'cltd';
    foreach ($categories as $category) {
        if (!empty($category['slug']) && $category['slug'] === $slug) {
            return $categories;
        }
    }

    $category = [
        'slug'  => $slug,
        'title' => __('CLTD', 'cltd-theme-oct-2025'),
        'icon'  => 'layout',
    ];

    array_unshift($categories, $category);
    return $categories;
}
add_filter('block_categories_all', 'cltd_theme_register_block_category', 9, 2);

/**
 * Build shared CSS variable definitions for button styles.
 *
 * @return string
 */
function cltd_theme_get_button_var_css() {
    $font_fallback = "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

    return <<<CSS
:root, body {
    --cltd-button-fill-bg: var(--wp--custom--button--color--background, var(--wp--preset--color--cltd-ink, #000000));
    --cltd-button-fill-text: var(--wp--custom--button--color--text, var(--wp--preset--color--cltd-paper, #ffffff));
    --cltd-button-font-family: var(--wp--custom--button--typography--font-family, var(--wp--preset--font-family--system-sans, {$font_fallback}));
}
CSS;
}

/**
 * Ensure button radius variable follows Site Editor controls on frontend.
 */
function cltd_theme_sync_button_radius_var() {
    $css = cltd_theme_get_button_var_css();

    $targets = ['global-styles', 'global-styles-inline-css', 'wp-block-library-theme', 'wp-block-library'];

    foreach ($targets as $handle) {
        if (wp_style_is($handle, 'enqueued')) {
            wp_add_inline_style($handle, $css);
            return;
        }
    }

    wp_register_style('cltd-inline-button-radius', false);
    wp_enqueue_style('cltd-inline-button-radius');
    wp_add_inline_style('cltd-inline-button-radius', $css);
}
add_action('wp_enqueue_scripts', 'cltd_theme_sync_button_radius_var', 80);

/**
 * Mirror button radius variable in the Site Editor.
 */
function cltd_theme_sync_button_radius_var_editor() {
    $css = cltd_theme_get_button_var_css();

    if (wp_style_is('wp-edit-blocks', 'enqueued')) {
        wp_add_inline_style('wp-edit-blocks', $css);
        return;
    }

    wp_register_style('cltd-inline-button-radius-editor', false);
    wp_enqueue_style('cltd-inline-button-radius-editor');
    wp_add_inline_style('cltd-inline-button-radius-editor', $css);
}
add_action('enqueue_block_editor_assets', 'cltd_theme_sync_button_radius_var_editor', 80);

/**
 * Register custom CLTD button block.
 */
function cltd_theme_register_auth_block_assets() {
    $base_dir = get_template_directory() . '/blocks/auth';
    $base_uri = get_template_directory_uri() . '/blocks/auth';

    $script_path = $base_dir . '/index.js';
    if (file_exists($script_path)) {
        $asset      = file_exists($base_dir . '/index.asset.php') ? include $base_dir . '/index.asset.php' : [];
        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies'])
            ? $asset['dependencies']
            : ['wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n'];
        $version = isset($asset['version']) ? $asset['version'] : filemtime($script_path);

        wp_register_script(
            'cltd-auth-blocks-editor',
            $base_uri . '/index.js',
            $dependencies,
            $version,
            true
        );
    }

    $style_path = $base_dir . '/style.css';
    if (file_exists($style_path)) {
        wp_register_style(
            'cltd-auth-blocks',
            $base_uri . '/style.css',
            [],
            filemtime($style_path)
        );
    }
}
add_action('init', 'cltd_theme_register_auth_block_assets', 14);

/**
 * Enqueue editor script for CLTD block visibility control.
 */
function cltd_theme_enqueue_block_visibility_script() {
    $script_path = get_template_directory() . '/js/block-visibility.js';
    if (!file_exists($script_path)) {
        return;
    }

    $dependencies = [
        'wp-hooks',
        'wp-i18n',
        'wp-element',
        'wp-components',
        'wp-compose',
        'wp-block-editor',
        'wp-editor',
    ];

    wp_enqueue_script(
        'cltd-block-visibility',
        get_template_directory_uri() . '/js/block-visibility.js',
        $dependencies,
        filemtime($script_path),
        true
    );
}
add_action('enqueue_block_editor_assets', 'cltd_theme_enqueue_block_visibility_script');

function cltd_theme_register_button_block() {
    $block_dir = get_template_directory() . '/blocks/cltd-button';

    if (!file_exists($block_dir . '/block.json')) {
        return;
    }

    register_block_type(
        $block_dir,
        [
            'render_callback' => 'cltd_theme_render_button_block',
        ]
    );
}
add_action('init', 'cltd_theme_register_button_block', 15);

/**
 * Register CLTD columns block (replaces core columns).
 */
function cltd_theme_register_columns_block() {
    if (!function_exists('register_block_type')) {
        return;
    }

    $script_handle     = 'cltd-theme-columns-editor';
    $style_handle      = 'cltd-theme-columns-style';
    $theme_dir         = get_template_directory();
    $theme_uri         = get_template_directory_uri();

    $script_rel      = '/blocks/cltd-columns/cltd-columns.js';
    $style_rel       = '/blocks/cltd-columns/cltd-columns.css';

    $script_path      = $theme_dir . $script_rel;
    $style_path       = $theme_dir . $style_rel;

    wp_register_script(
        $script_handle,
        $theme_uri . $script_rel,
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'],
        file_exists($script_path) ? filemtime($script_path) : false,
        true
    );

    wp_register_style(
        $style_handle,
        $theme_uri . $style_rel,
        [],
        file_exists($style_path) ? filemtime($style_path) : false
    );

    $block_args = [
        'editor_script' => $script_handle,
        'editor_style'  => $style_handle,
        'style'         => $style_handle,
    ];

    register_block_type('cltd/columns', $block_args);
}
add_action('init', 'cltd_theme_register_columns_block', 16);

/**
 * Ensure CLTD auth blocks (login/signup/forgot/account) are registered.
 */
function cltd_theme_register_auth_blocks() {
    if (!function_exists('register_block_type')) {
        return;
    }

    $blocks = ['login', 'signup', 'forgot', 'account'];
    $base_dir = get_template_directory() . '/blocks/auth';
    $registry = class_exists('WP_Block_Type_Registry') ? WP_Block_Type_Registry::get_instance() : null;

    foreach ($blocks as $block) {
        $dir = $base_dir . '/' . $block;
        if (!file_exists($dir . '/block.json')) {
            continue;
        }

        $block_name = 'cltd/auth-' . $block;
        if ($registry && $registry->is_registered($block_name)) {
            continue;
        }

        register_block_type($dir);
    }
}
add_action('init', 'cltd_theme_register_auth_blocks', 17);

/**
 * Ensure CLTD columns assets load in editor.
 */
function cltd_theme_enqueue_columns_editor_assets() {
    if (wp_script_is('cltd-theme-columns-editor', 'registered') && !wp_script_is('cltd-theme-columns-editor', 'enqueued')) {
        wp_enqueue_script('cltd-theme-columns-editor');
    }

    if (wp_style_is('cltd-theme-columns-style', 'registered') && !wp_style_is('cltd-theme-columns-style', 'enqueued')) {
        wp_enqueue_style('cltd-theme-columns-style');
    }
}
add_action('enqueue_block_editor_assets', 'cltd_theme_enqueue_columns_editor_assets', 20);

/**
 * Ensure CLTD auth block styles load on both frontend and editor.
 */
function cltd_theme_enqueue_auth_block_styles() {
    if (wp_style_is('cltd-auth-blocks', 'registered') && !wp_style_is('cltd-auth-blocks', 'enqueued')) {
        wp_enqueue_style('cltd-auth-blocks');
    }
}
add_action('enqueue_block_assets', 'cltd_theme_enqueue_auth_block_styles');

/**
 * Ensure CLTD button block styles load on the frontend/editor even if metadata registration is cached.
 */
function cltd_theme_enqueue_button_block_styles() {
    $style_path = get_template_directory() . '/blocks/cltd-button/style.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'cltd-button-block',
            get_template_directory_uri() . '/blocks/cltd-button/style.css',
            [],
            filemtime($style_path)
        );
    }
}
add_action('enqueue_block_assets', 'cltd_theme_enqueue_button_block_styles');

/**
 * Load editor-specific styles for the CLTD button block.
 */
function cltd_theme_enqueue_button_block_editor_styles() {
    $editor_style_path = get_template_directory() . '/blocks/cltd-button/editor.css';
    if (file_exists($editor_style_path)) {
        wp_enqueue_style(
            'cltd-button-block-editor',
            get_template_directory_uri() . '/blocks/cltd-button/editor.css',
            [],
            filemtime($editor_style_path)
        );
    }
}
add_action('enqueue_block_editor_assets', 'cltd_theme_enqueue_button_block_editor_styles');

add_filter(
    'block_categories_all',
    static function($categories) {
        $cltd_category = [
            'slug'  => 'cltd',
            'title' => __('CLTD', 'cltd-theme-oct-2025'),
            'icon'  => 'admin-site-alt3',
        ];

        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === $cltd_category['slug']) {
                return $categories;
            }
        }

        array_unshift($categories, $cltd_category);
        return $categories;
    },
    5
);

/**
 * Render callback for CLTD button block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function cltd_theme_render_button_block($attributes) {
    $text        = isset($attributes['text']) && $attributes['text'] !== '' ? $attributes['text'] : __('Button', 'cltd-theme-oct-2025');
    $url         = isset($attributes['url']) ? esc_url($attributes['url']) : '';
    $align       = isset($attributes['align']) ? sanitize_text_field($attributes['align']) : '';
    $rel         = isset($attributes['rel']) ? sanitize_text_field($attributes['rel']) : '';
    $link_target = isset($attributes['linkTarget']) ? sanitize_text_field($attributes['linkTarget']) : '';
    $aria_label  = isset($attributes['ariaLabel']) ? sanitize_text_field($attributes['ariaLabel']) : '';
    $border_radius = isset($attributes['borderRadius']) && $attributes['borderRadius'] !== null
        ? max(0, (int) $attributes['borderRadius'])
        : null;
    $border_width = isset($attributes['borderWidth']) && $attributes['borderWidth'] !== null
        ? max(0, (int) $attributes['borderWidth'])
        : null;
    $border_color = isset($attributes['borderColor']) ? sanitize_hex_color($attributes['borderColor']) : '';

    $wrapper_args = [];
    if ($align) {
        $wrapper_args['class'] = 'has-text-align-' . sanitize_html_class($align);
        $wrapper_args['style'] = 'text-align:' . esc_attr($align) . ';';
    }
    $wrapper_attributes = get_block_wrapper_attributes($wrapper_args);

    $link_attributes = [
        'class' => 'wp-block-cltd-button__link',
    ];

    if ($aria_label) {
        $link_attributes['aria-label'] = $aria_label;
    }

    if ($url && $link_target) {
        $link_attributes['target'] = $link_target;
    }

    if ($url && $rel) {
        $link_attributes['rel'] = $rel;
    }

    $style_tokens = [];
    if (null !== $border_radius) {
        $style_tokens[] = '--wp--custom--button--border--radius:' . $border_radius . 'px';
        $style_tokens[] = '--cltd-button-border-radius:' . $border_radius . 'px';
    }
    if (null !== $border_width) {
        $style_tokens[] = '--cltd-button-border-width:' . $border_width . 'px';
    }
    if ($border_color) {
        $style_tokens[] = '--cltd-button-border-color:' . $border_color;
    }
    if ($style_tokens) {
        $link_attributes['style'] = implode(';', $style_tokens) . ';';
    }

    $content = sprintf('<span class="wp-block-cltd-button__label">%s</span>', wp_kses_post($text));

    if ($url) {
        $link_attributes['href'] = $url;
        $attribute_pairs = [];
        foreach ($link_attributes as $key => $value) {
            $attribute_pairs[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
        }
        return sprintf(
            '<div %1$s><a %2$s>%3$s</a></div>',
            $wrapper_attributes,
            implode(' ', $attribute_pairs),
            $content
        );
    }

    $link_attributes['type'] = 'button';
    $attribute_pairs = [];
    foreach ($link_attributes as $key => $value) {
        $attribute_pairs[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
    }

    return sprintf(
        '<div %1$s><button %2$s>%3$s</button></div>',
        $wrapper_attributes,
        implode(' ', $attribute_pairs),
        $content
    );
}

/**
 * Optionally register the popup custom post type that powers modal content.
 */
function cltd_theme_register_popup_cpt() {
    $should_register = apply_filters('cltd_theme_register_popup_cpt', true);
    if (!$should_register) {
        return;
    }

    $labels = [
        'name'               => _x('Popup Items', 'post type general name', 'cltd-theme-oct-2025'),
        'singular_name'      => _x('Popup Item', 'post type singular name', 'cltd-theme-oct-2025'),
        'menu_name'          => _x('Popup Items', 'admin menu', 'cltd-theme-oct-2025'),
        'name_admin_bar'     => _x('Popup Item', 'add new on admin bar', 'cltd-theme-oct-2025'),
        'add_new'            => _x('Add New', 'popup item', 'cltd-theme-oct-2025'),
        'add_new_item'       => __('Add New Popup Item', 'cltd-theme-oct-2025'),
        'new_item'           => __('New Popup Item', 'cltd-theme-oct-2025'),
        'edit_item'          => __('Edit Popup Item', 'cltd-theme-oct-2025'),
        'view_item'          => __('View Popup Item', 'cltd-theme-oct-2025'),
        'all_items'          => __('All Popup Items', 'cltd-theme-oct-2025'),
        'search_items'       => __('Search Popup Items', 'cltd-theme-oct-2025'),
        'parent_item_colon'  => __('Parent Popup Items:', 'cltd-theme-oct-2025'),
        'not_found'          => __('No popup items found.', 'cltd-theme-oct-2025'),
        'not_found_in_trash' => __('No popup items found in Trash.', 'cltd-theme-oct-2025'),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 22,
        'menu_icon'          => 'dashicons-feedback',
        'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'has_archive'        => false,
        'rewrite'            => false,
        'show_in_nav_menus'  => false,
        'show_in_admin_bar'  => true,
        'capability_type'    => 'page',
        'map_meta_cap'       => true,
        'show_in_rest'       => true,
        'rest_base'          => 'cltd-popup-items',
    ];

    register_post_type('cltd_popup', $args);
}
add_action('init', 'cltd_theme_register_popup_cpt');

/**
 * Optionally register taxonomy used to organize popup content.
 */
// Legacy popup_group taxonomy removed in favour of popup_group custom post type.

/**
 * Register Popup Group custom post type.
 */
function cltd_theme_register_popup_group_cpt() {
    $labels = [
        'name'               => __('Popup Groups', 'cltd-theme-oct-2025'),
        'singular_name'      => __('Popup Group', 'cltd-theme-oct-2025'),
        'menu_name'          => __('Popup Groups', 'cltd-theme-oct-2025'),
        'add_new'            => __('Add New', 'cltd-theme-oct-2025'),
        'add_new_item'       => __('Add New Popup Group', 'cltd-theme-oct-2025'),
        'edit_item'          => __('Edit Popup Group', 'cltd-theme-oct-2025'),
        'new_item'           => __('New Popup Group', 'cltd-theme-oct-2025'),
        'view_item'          => __('View Popup Group', 'cltd-theme-oct-2025'),
        'search_items'       => __('Search Popup Groups', 'cltd-theme-oct-2025'),
        'not_found'          => __('No popup groups found.', 'cltd-theme-oct-2025'),
        'not_found_in_trash' => __('No popup groups found in Trash.', 'cltd-theme-oct-2025'),
    ];

    $args = [
        'label'               => __('Popup Groups', 'cltd-theme-oct-2025'),
        'labels'              => $labels,
        'public'              => true,
        'show_in_rest'        => true,
        'menu_position'       => 6,
        'supports'            => ['title', 'editor', 'revisions', 'custom-fields'],
        'has_archive'         => false,
        'hierarchical'        => false,
        'rewrite'             => ['slug' => 'popup-group'],
        'menu_icon'           => 'dashicons-screenoptions',
    ];

    register_post_type('popup_group', $args);
}
add_action('init', 'cltd_theme_register_popup_group_cpt');

/**
 * Register popup group meta for popup items.
 */
function cltd_theme_register_popup_group_meta() {
    register_post_meta(
        'popup_group',
        'cltd_popup_items',
        [
            'type'         => 'array',
            'single'       => true,
            'default'      => [],
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'type'     => ['type' => 'string'],
                            'popup_id' => ['type' => 'integer'],
                            'slug'     => ['type' => 'string'],
                            'label'    => ['type' => 'string'],
                            'excerpt'  => ['type' => 'string'],
                            'icon'     => ['type' => 'string'],
                            'order'    => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]
    );

    register_post_meta(
        'popup_group',
        'cltd_grid_span',
        [
            'type'         => 'integer',
            'single'       => true,
            'default'      => 1,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]
    );

    register_post_meta(
        'popup_group',
        'cltd_grid_order',
        [
            'type'         => 'integer',
            'single'       => true,
            'default'      => 0,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]
    );
}
add_action('init', 'cltd_theme_register_popup_group_meta');

/**
 * Get available popup posts.
 *
 * @return array
 */
function cltd_theme_get_available_popups() {
    static $cache = null;

    if (null !== $cache) {
        return $cache;
    }

    $posts = get_posts([
        'post_type'       => 'cltd_popup',
        'post_status'     => 'publish',
        'numberposts'     => -1,
        'orderby'         => 'title',
        'order'           => 'ASC',
        'suppress_filters'=> false,
    ]);

    $cache = [];
    if (!empty($posts)) {
        foreach ($posts as $post) {
            $cache[] = [
                'id'    => $post->ID,
                'title' => $post->post_title ?: $post->post_name,
                'slug'  => $post->post_name,
            ];
        }
    }

    return $cache;
}

/**
 * Register popup group item metabox.
 */
function cltd_theme_add_popup_group_metabox() {
    add_meta_box(
        'cltd-popup-items',
        __('Popup Items', 'cltd-theme-oct-2025'),
        'cltd_theme_render_popup_group_metabox',
        'popup_group',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'cltd_theme_add_popup_group_metabox');

/**
 * Render popup items metabox markup.
 */
function cltd_theme_render_popup_group_metabox($post) {
    $grid_span = (int) get_post_meta($post->ID, 'cltd_grid_span', true);
    if ($grid_span < 1) {
        $grid_span = 1;
    }
    if ($grid_span > 4) {
        $grid_span = 4;
    }

    $grid_order_meta = get_post_meta($post->ID, 'cltd_grid_order', true);
    $grid_order = '' === $grid_order_meta ? '' : (int) $grid_order_meta;

    $items = get_post_meta($post->ID, 'cltd_popup_items', true);
    $items = is_array($items) ? $items : [];

    wp_nonce_field('cltd_popup_items_nonce', 'cltd_popup_items_nonce');

    $next_index = count($items);
    $available_popups = cltd_theme_get_available_popups();
    ?>
    <div id="cltd-popup-items" class="cltd-popup-items" data-next-index="<?php echo esc_attr($next_index); ?>">
        <div class="cltd-popup-items__layout">
            <p>
                <label for="cltd-grid-span">
                    <span><?php esc_html_e('Grid Column Span', 'cltd-theme-oct-2025'); ?></span>
                    <select id="cltd-grid-span" name="cltd_grid_span">
                        <?php for ($i = 1; $i <= 3; $i++) : ?>
                            <option value="<?php echo esc_attr($i); ?>" <?php selected($grid_span, $i); ?>><?php printf(esc_html__('%d column(s)', 'cltd-theme-oct-2025'), $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <span class="description"><?php esc_html_e('Controls how many columns this group spans on larger screens.', 'cltd-theme-oct-2025'); ?></span>
            </p>
            <p>
                <label for="cltd-grid-order">
                    <span><?php esc_html_e('Grid Order', 'cltd-theme-oct-2025'); ?></span>
                    <input type="number" id="cltd-grid-order" name="cltd_grid_order" class="small-text" value="<?php echo esc_attr('' === $grid_order ? '' : $grid_order); ?>" />
                </label>
                <span class="description"><?php esc_html_e('Lower numbers appear first. Leave blank to use menu order.', 'cltd-theme-oct-2025'); ?></span>
            </p>
            <div class="cltd-popup-grid-preview" data-cltd-grid-preview data-span="<?php echo esc_attr($grid_span); ?>">
                <?php for ($i = 1; $i <= 3; $i++) : ?>
                    <span class="cltd-popup-grid-preview__cell" data-cell="<?php echo esc_attr($i); ?>"></span>
                <?php endfor; ?>
                <div class="cltd-popup-grid-preview__label"><?php esc_html_e('Preview span', 'cltd-theme-oct-2025'); ?></div>
            </div>
        </div>
        <p class="description"><?php esc_html_e('Add popup circle items with labels, popups or external links, optional SVG icons, and a custom order.', 'cltd-theme-oct-2025'); ?></p>
        <div class="cltd-popup-items__list">
            <?php
            if (!empty($items)) {
                foreach ($items as $index => $item) {
                    echo cltd_theme_get_popup_item_fields($index, $item, $available_popups); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            ?>
        </div>
        <button type="button" class="button button-secondary" data-cltd-add-popup-item><?php esc_html_e('Add Popup Item', 'cltd-theme-oct-2025'); ?></button>
    </div>
    <template id="cltd-popup-item-template">
        <?php echo cltd_theme_get_popup_item_fields('__INDEX__', [], $available_popups); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </template>
    <?php
}

/**
 * Generate popup item fieldset markup.
 *
 * @param int|string $index Index key.
 * @param array      $item  Item data.
 * @return string
 */
function cltd_theme_get_popup_item_fields($index, $item, $available_popups = null) {
    $defaults = [
        'type'  => 'popup',
        'popup_id' => 0,
        'slug'  => '',
        'order' => '',
    ];
    $item = wp_parse_args(is_array($item) ? $item : [], $defaults);

    $index_attr = esc_attr($index);
    $type = esc_attr($item['type']);
    $popup_id = isset($item['popup_id']) ? (int) $item['popup_id'] : 0;
    $slug_value = isset($item['slug']) ? sanitize_title($item['slug']) : '';
    if ($popup_id && !$slug_value) {
        $popup_post = get_post($popup_id);
        if ($popup_post && 'cltd_popup' === $popup_post->post_type) {
            $slug_value = $popup_post->post_name;
        }
    }
    if (!$popup_id && $slug_value) {
        foreach ($available_popups as $popup) {
            if ($popup['slug'] === $slug_value) {
                $popup_id = (int) $popup['id'];
                break;
            }
        }
    }
    $order = esc_attr($item['order']);

    if (null === $available_popups) {
        $available_popups = cltd_theme_get_available_popups();
    }

    ob_start();
    ?>
    <div class="cltd-popup-item" data-item-index="<?php echo $index_attr; ?>">
        <div class="cltd-popup-item__header">
            <h4><?php printf(esc_html__('Item %s', 'cltd-theme-oct-2025'), '<span data-item-label>#</span>'); ?></h4>
            <button type="button" class="button-link-delete" data-cltd-remove-popup-item><?php esc_html_e('Remove', 'cltd-theme-oct-2025'); ?></button>
        </div>
        <div class="cltd-popup-item__fields">
            <p>
                <label>
                    <span><?php esc_html_e('Item Type', 'cltd-theme-oct-2025'); ?></span>
                    <select name="cltd_popup_items[<?php echo $index_attr; ?>][type]" class="cltd-popup-item__type">
                        <option value="popup" <?php selected($type, 'popup'); ?>><?php esc_html_e('Popup (uses slug)', 'cltd-theme-oct-2025'); ?></option>
                        <option value="link" <?php selected($type, 'link'); ?>><?php esc_html_e('External Link', 'cltd-theme-oct-2025'); ?></option>
                    </select>
                </label>
            </p>
            <div class="cltd-popup-item__row" data-field="popup">
                <p>
                    <label>
                        <span><?php esc_html_e('Select Popup', 'cltd-theme-oct-2025'); ?></span>
                        <select name="cltd_popup_items[<?php echo $index_attr; ?>][popup_id]" class="cltd-popup-item__popup-select">
                            <option value="0" data-slug=""><?php esc_html_e('Select a Popup', 'cltd-theme-oct-2025'); ?></option>
                            <?php foreach ($available_popups as $popup) : ?>
                                <option value="<?php echo esc_attr($popup['id']); ?>" data-slug="<?php echo esc_attr($popup['slug']); ?>" <?php selected($popup_id, $popup['id']); ?>>
                                    <?php echo esc_html($popup['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>
                        <span><?php esc_html_e('Popup Slug', 'cltd-theme-oct-2025'); ?></span>
                        <input type="text" class="widefat cltd-popup-item__slug" name="cltd_popup_items[<?php echo $index_attr; ?>][slug]" value="<?php echo esc_attr($slug_value); ?>" />
                    </label>
                </p>
            </div>
            <div class="cltd-popup-item__row" data-field="link">
                <p>
                    <label>
                        <span class="cltd-popup-item__link-label"><?php esc_html_e('Link URL', 'cltd-theme-oct-2025'); ?></span>
                        <input type="hidden" name="cltd_popup_items[<?php echo $index_attr; ?>][link]" value="" />
                        <em><?php esc_html_e('Link items are managed directly on the popup item.', 'cltd-theme-oct-2025'); ?></em>
                    </label>
                </p>
            </div>
            <p>
                <label>
                    <span><?php esc_html_e('Order', 'cltd-theme-oct-2025'); ?></span>
                    <input type="number" class="small-text" name="cltd_popup_items[<?php echo $index_attr; ?>][order]" value="<?php echo $order; ?>" />
                </label>
            </p>
        </div>
        <hr />
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Save popup group items.
 *
 * @param int $post_id Post ID.
 */
function cltd_theme_save_popup_group_items($post_id) {
    if (!isset($_POST['cltd_popup_items_nonce']) || !wp_verify_nonce($_POST['cltd_popup_items_nonce'], 'cltd_popup_items_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['cltd_popup_items']) && is_array($_POST['cltd_popup_items'])) {
        $raw_items = wp_unslash($_POST['cltd_popup_items']);
        $sanitized = [];

        foreach ($raw_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type  = isset($item['type']) ? sanitize_key($item['type']) : 'popup';
            $popup_id = isset($item['popup_id']) ? (int) $item['popup_id'] : 0;
            $slug_input = isset($item['slug']) ? $item['slug'] : '';
            $order = isset($item['order']) ? (int) $item['order'] : 0;

            $type = in_array($type, ['popup', 'link'], true) ? $type : 'popup';

            $slug = '';
            $link = '';
            $label = '';
            $excerpt = '';
            $icon = '';
            $icon_type = '';

            if ('link' === $type) {
                // Link items must be managed directly on the popup; skip here.
                continue;
            }

            if ($popup_id) {
                $popup_post = get_post($popup_id);
                if ($popup_post && 'cltd_popup' === $popup_post->post_type) {
                    $slug = $popup_post->post_name;
                    $label = get_the_title($popup_post);
                    $excerpt = wp_strip_all_tags(get_the_excerpt($popup_post));
                    $icon = get_post_meta($popup_post->ID, 'cltd_popup_icon', true);
                    $icon = $icon ? esc_url_raw($icon) : '';
                    $icon_type = cltd_theme_get_popup_icon_type($popup_post->ID, $icon);
                } else {
                    $popup_id = 0;
                }
            }

            if (!$slug && $slug_input) {
                $slug = sanitize_title($slug_input);
                $popup_post = get_page_by_path($slug, OBJECT, 'cltd_popup');
                if ($popup_post) {
                    $popup_id = $popup_post->ID;
                    $label = get_the_title($popup_post);
                    $excerpt = wp_strip_all_tags(get_the_excerpt($popup_post));
                    $icon = get_post_meta($popup_post->ID, 'cltd_popup_icon', true);
                    $icon = $icon ? esc_url_raw($icon) : '';
                    $icon_type = cltd_theme_get_popup_icon_type($popup_post->ID, $icon);
                }
            }

            if (!$slug) {
                continue;
            }

            $sanitized[] = [
                'type'     => 'popup',
                'popup_id' => $popup_id,
                'slug'     => $slug,
                'label'    => $label,
                'excerpt'  => $excerpt,
                'icon'     => $icon,
                'icon_type'=> $icon_type,
                'order'    => $order,
            ];
        }

        update_post_meta($post_id, 'cltd_popup_items', $sanitized);
    } else {
        delete_post_meta($post_id, 'cltd_popup_items');
    }

    if (isset($_POST['cltd_grid_span'])) {
        $span = max(1, min(3, (int) wp_unslash($_POST['cltd_grid_span'])));
        update_post_meta($post_id, 'cltd_grid_span', $span);
    }

    if (isset($_POST['cltd_grid_order'])) {
        $order_raw = wp_unslash($_POST['cltd_grid_order']);
        if ($order_raw === '' || null === $order_raw) {
            delete_post_meta($post_id, 'cltd_grid_order');
        } else {
            update_post_meta($post_id, 'cltd_grid_order', (int) $order_raw);
        }
    }
}
add_action('save_post_popup_group', 'cltd_theme_save_popup_group_items');

/**
 * Enqueue popup group admin assets.
 */
function cltd_theme_enqueue_popup_group_admin_assets($hook) {
    $screen = get_current_screen();
    if (!$screen || 'popup_group' !== $screen->post_type) {
        return;
    }

    wp_enqueue_media();
    $script_path = get_template_directory() . '/js/popup-group-admin.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'cltd-popup-group-admin',
            get_template_directory_uri() . '/js/popup-group-admin.js',
            ['jquery', 'wp-util'],
            filemtime($script_path),
            true
        );
    }

    wp_enqueue_script('jquery-ui-accordion');

    $style_path = get_template_directory() . '/css/admin-popup-groups.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'cltd-popup-group-admin',
            get_template_directory_uri() . '/css/admin-popup-groups.css',
            [],
            filemtime($style_path)
        );
    }
}
add_action('admin_enqueue_scripts', 'cltd_theme_enqueue_popup_group_admin_assets');

/**
 * Retrieve popup group data with items.
 *
 * @param array $args Arguments.
 * @return array
 */
function cltd_theme_get_popup_groups_data(array $args = []) {
    $args = wp_parse_args(
        $args,
        [
            'group_ids' => [],
            'order'     => 'ASC',
        ]
    );

    $query_args = [
        'post_type'      => 'popup_group',
        'post_status'    => 'publish',
        'orderby'        => ['meta_value_num' => ('DESC' === strtoupper($args['order'])) ? 'DESC' : 'ASC', 'menu_order' => ('DESC' === strtoupper($args['order'])) ? 'DESC' : 'ASC'],
        'meta_key'       => 'cltd_grid_order',
        'order'          => ('DESC' === strtoupper($args['order'])) ? 'DESC' : 'ASC',
        'numberposts'    => -1,
        'suppress_filters' => false,
    ];

    if (!empty($args['group_ids'])) {
        $query_args['include'] = array_map('intval', (array) $args['group_ids']);
        $query_args['orderby'] = 'post__in';
    }

    $posts = get_posts($query_args);
    if (empty($posts)) {
        return [];
    }

    $groups = [];
    foreach ($posts as $post) {
        $raw_items = get_post_meta($post->ID, 'cltd_popup_items', true);
        $prepared_items = cltd_theme_prepare_popup_items($raw_items);

        $grid_span_meta = get_post_meta($post->ID, 'cltd_grid_span', true);
        $grid_span = $grid_span_meta ? (int) $grid_span_meta : 1;
        if ($grid_span < 1) {
            $grid_span = 1;
        }
        if ($grid_span > 3) {
            $grid_span = 3;
        }

        $grid_order_meta = get_post_meta($post->ID, 'cltd_grid_order', true);
        $grid_order = $grid_order_meta === '' ? null : (int) $grid_order_meta;

        $groups[] = [
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'description' => apply_filters('the_content', $post->post_content),
            'items'       => $prepared_items,
            'menu_order'  => (int) $post->menu_order,
            'grid_span'   => $grid_span,
            'grid_order'  => $grid_order,
        ];
    }

    usort(
        $groups,
        static function($a, $b) {
            $a_order = isset($a['grid_order']) && null !== $a['grid_order'] ? (int) $a['grid_order'] : ($a['menu_order'] ?? 0);
            $b_order = isset($b['grid_order']) && null !== $b['grid_order'] ? (int) $b['grid_order'] : ($b['menu_order'] ?? 0);

            if ($a_order === $b_order) {
                return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
            }

            return $a_order <=> $b_order;
        }
    );

    return $groups;
}

/**
 * Prepare popup items for output.
 *
 * @param array $raw_items Raw meta.
 * @return array
 */
function cltd_theme_prepare_popup_items($raw_items) {
    if (!is_array($raw_items)) {
        return [];
    }

    $items = [];

    foreach ($raw_items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? sanitize_text_field($item['label']) : '';
        if ('' === $label) {
            continue;
        }

        $type = isset($item['type']) ? sanitize_key($item['type']) : 'popup';
        $type = in_array($type, ['popup', 'link'], true) ? $type : 'popup';

        $popup_id = isset($item['popup_id']) ? (int) $item['popup_id'] : 0;
        $slug     = isset($item['slug']) ? sanitize_title($item['slug']) : '';
        $link     = isset($item['link']) ? esc_url($item['link']) : '';
        $icon     = isset($item['icon']) ? esc_url($item['icon']) : '';
        $icon_type = isset($item['icon_type']) ? sanitize_key($item['icon_type']) : '';
        if (!$icon_type && $icon) {
            $icon_type = cltd_theme_detect_icon_type($icon);
        }
        if (!in_array($icon_type, ['svg', 'image', 'lottie'], true)) {
            $icon_type = '';
        }
        $order    = isset($item['order']) ? (int) $item['order'] : 0;

        $url = '';

        if ('link' === $type) {
            $url = $link;
            $slug = '';
            $popup_id = 0;
        } else {
            if ($popup_id) {
                $popup_post = get_post($popup_id);
                if ($popup_post && 'cltd_popup' === $popup_post->post_type) {
                    $slug = $popup_post->post_name;
                    $popup_icon = get_post_meta($popup_post->ID, 'cltd_popup_icon', true);
                    if ($popup_icon) {
                        $icon = esc_url($popup_icon);
                        $icon_type = cltd_theme_get_popup_icon_type($popup_post->ID, $icon);
                    } else {
                        $icon = '';
                        $icon_type = '';
                    }
                }
            }

            if (!$slug && !empty($item['link'])) {
                $slug = sanitize_title($item['link']);
            }

            if (!$slug) {
                continue;
            }
            if (!$popup_id && $slug) {
                $popup_post = get_page_by_path($slug, OBJECT, 'cltd_popup');
                if ($popup_post) {
                    $popup_id = $popup_post->ID;
                    $popup_icon = get_post_meta($popup_post->ID, 'cltd_popup_icon', true);
                    if ($popup_icon) {
                        $icon = esc_url($popup_icon);
                        $icon_type = cltd_theme_get_popup_icon_type($popup_post->ID, $icon);
                    } else {
                        $icon = '';
                        $icon_type = '';
                    }
                }
            }
        }

        $items[] = [
            'label'    => $label,
            'type'     => $type,
            'slug'     => $slug,
            'popup_id' => $popup_id,
            'url'      => $url,
            'icon'     => $icon,
            'icon_type'=> $icon_type,
            'order'    => $order,
            'state'    => '',
            'disabled' => false,
        ];
    }

    usort(
        $items,
        static function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        }
    );

    return $items;
}

/**
 * Render a popup group section.
 *
 * @param array       $group       Group data.
 * @param string|null $extra_class Extra CSS classes.
 * @param array        $overrides  Optional overrides (span/order/class).
 * @return string
 */
function cltd_theme_render_popup_group_section(array $group, $extra_class = null, array $overrides = []) {
    if (empty($group['items'])) {
        return '';
    }

    $classes = ['grid-group'];
    if (!empty($extra_class)) {
        $extra = is_array($extra_class) ? $extra_class : preg_split('/\s+/', (string) $extra_class);
        foreach ($extra as $class) {
            $class = sanitize_html_class($class);
            if ($class) {
                $classes[] = $class;
            }
        }
    }

    $span_override = null;
    if (isset($overrides['span']) && $overrides['span']) {
        $span_override = max(1, min(3, (int) $overrides['span']));
    }

    $order_override = null;
    if (array_key_exists('order', $overrides) && $overrides['order'] !== null) {
        $order_override = (int) $overrides['order'];
    }

    $span = $span_override !== null
        ? $span_override
        : (isset($group['grid_span']) ? max(1, min(3, (int) $group['grid_span'])) : 1);

    $order_value = null;
    if ($order_override !== null) {
        $order_value = $order_override;
    } elseif (isset($group['grid_order']) && null !== $group['grid_order']) {
        $order_value = (int) $group['grid_order'];
    } elseif (isset($group['menu_order'])) {
        $order_value = (int) $group['menu_order'];
    }

    $style_parts = ['--cltd-grid-span:' . $span];
    if (null !== $order_value) {
        $style_parts[] = '--cltd-grid-order:' . $order_value;
    }

    $style_attr = $style_parts ? ' style="' . esc_attr(implode(';', $style_parts)) . '"' : '';

    ob_start();
    ?>
    <section class="<?php echo esc_attr(implode(' ', array_unique($classes))); ?>" data-popup-group-id="<?php echo esc_attr($group['id']); ?>"<?php echo $style_attr; ?>>
        <?php if (!empty($group['title'])) : ?>
            <h2><?php echo esc_html($group['title']); ?></h2>
        <?php endif; ?>

        <?php if (!empty($group['description'])) : ?>
            <div class="grid-group__description"><?php echo wp_kses_post($group['description']); ?></div>
        <?php endif; ?>

        <ul class="circle-row">
            <?php foreach ($group['items'] as $item) :
        $type  = isset($item['type']) ? $item['type'] : 'popup';
        $slug  = isset($item['slug']) ? $item['slug'] : '';
        $popup_id = isset($item['popup_id']) ? (int) $item['popup_id'] : 0;
        $label = isset($item['label']) ? $item['label'] : '';
        $excerpt = isset($item['excerpt']) ? $item['excerpt'] : '';
        $icon  = isset($item['icon']) ? $item['icon'] : '';
        $icon_type = isset($item['icon_type']) ? cltd_theme_normalize_icon_type($item['icon_type']) : '';
        $state = isset($item['state']) ? $item['state'] : '';
        $is_disabled = !empty($item['disabled']);

        $circle_classes = ['circle'];
        if ($state) {
            $circle_classes[] = 'circle--' . sanitize_html_class($state);
        }
        if ($is_disabled) {
            $circle_classes[] = 'circle--inactive';
        }
        if ($icon) {
            $circle_classes[] = 'circle--has-icon';
        }
        ?>
        <li class="circle-item">
        <?php if ('popup' === $type && !$is_disabled && $slug) : ?>
            <button type="button" class="<?php echo esc_attr(implode(' ', $circle_classes)); ?>" data-popup-slug="<?php echo esc_attr($slug); ?>" data-gtm-popup="<?php echo esc_attr($slug); ?>" <?php if ($popup_id) : ?>data-popup-id="<?php echo esc_attr($popup_id); ?>"<?php endif; ?> data-popup-title="<?php echo esc_attr($label); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open "%s" details', 'cltd-theme-oct-2025'), $label ?: $slug)); ?>">
                            <?php if ($icon && 'lottie' === $icon_type) : ?>
                                <span class="circle__icon circle__icon--lottie" aria-hidden="true" data-lottie-icon data-lottie-src="<?php echo esc_url($icon); ?>"></span>
                            <?php elseif ($icon) : ?>
                                <span class="circle__icon" aria-hidden="true">
                                    <img src="<?php echo esc_url($icon); ?>" alt="" loading="lazy" decoding="async" />
                                </span>
                            <?php endif; ?>
                            <span class="sr-only"><?php echo esc_html($label); ?></span>
                        </button>
                    <?php endif; ?>
                    <?php if ($label) : ?>
                        <span class="circle__label"><?php echo esc_html($label); ?></span>
                    <?php elseif ($slug) : ?>
                        <span class="circle__label"><?php echo esc_html($slug); ?></span>
                    <?php endif; ?>
                    <?php if ($excerpt) : ?>
                        <span class="circle__excerpt"><?php echo esc_html($excerpt); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Register popup group dynamic block.
 */
function cltd_theme_register_popup_group_block() {
    $block_dir = get_template_directory() . '/blocks/popup-group';
    $script_path = $block_dir . '/index.js';

    if (!file_exists($block_dir . '/block.json')) {
        return;
    }

    if (file_exists($script_path)) {
        wp_register_script(
            'cltd-popup-group-editor',
            get_template_directory_uri() . '/blocks/popup-group/index.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n', 'wp-server-side-render'],
            filemtime($script_path),
            true
        );
    }

    register_block_type(
        $block_dir,
        [
            'editor_script'   => 'cltd-popup-group-editor',
            'render_callback' => 'cltd_render_popup_group_block',
        ]
    );
}
add_action('init', 'cltd_theme_register_popup_group_block');

/**
 * Register legacy layout block wrapper.
 */
function cltd_theme_register_legacy_layout_block() {
    $block_dir = get_template_directory() . '/blocks/legacy-layout';
    if (!file_exists($block_dir . '/block.json')) {
        return;
    }

    register_block_type(
        $block_dir,
        [
            'render_callback' => 'cltd_render_legacy_layout_block',
        ]
    );
}
add_action('init', 'cltd_theme_register_legacy_layout_block');

/**
 * Render callback for legacy layout block.
 *
 * @return string
 */
function cltd_render_legacy_layout_block() {
    return do_shortcode('[cltd_legacy_layout]');
}

/**
 * Render callback for the popup group block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function cltd_render_popup_group_block($attributes) {
    $group_id = isset($attributes['groupId']) ? (int) $attributes['groupId'] : 0;
    $order    = isset($attributes['order']) && 'desc' === strtolower($attributes['order']) ? 'DESC' : 'ASC';
    $class    = isset($attributes['className']) ? $attributes['className'] : '';

    $span_override = null;
    if (isset($attributes['columnSpan']) && (int) $attributes['columnSpan'] > 0) {
        $span_override = max(1, min(3, (int) $attributes['columnSpan']));
    }

    $order_override = null;
    if (array_key_exists('columnOrder', $attributes) && null !== $attributes['columnOrder']) {
        $order_override = (int) $attributes['columnOrder'];
    }

    $render_overrides = [
        'span'  => $span_override,
        'order' => $order_override,
    ];

    $groups = cltd_theme_get_popup_groups_data([
        'group_ids' => $group_id ? [$group_id] : [],
        'order'     => $order,
    ]);

    if (empty($groups)) {
        return '<div class="wp-block-cltd-popup-group"><p class="cltd-popup-group__empty">' . esc_html__('No popup groups found.', 'cltd-theme-oct-2025') . '</p></div>';
    }

    $wrapper_classes = ['wp-block-cltd-popup-group'];
    if (!empty($class)) {
        $extra = preg_split('/\s+/', $class);
        foreach ($extra as $cls) {
            $cls = sanitize_html_class($cls);
            if ($cls) {
                $wrapper_classes[] = $cls;
            }
        }
    }

    $output = '<div class="' . esc_attr(implode(' ', array_unique($wrapper_classes))) . '">';
    foreach ($groups as $group) {
        $section_html = cltd_theme_render_popup_group_section($group, null, $render_overrides);
        if ($section_html) {
            $output .= $section_html;
        }
    }
    $output .= '</div>';

    return $output;
}

/**
 * Default hero background configuration.
 *
 * @return array
 */
function cltd_theme_get_hero_background_slide_defaults() {
    return [
        'type'       => 'image', // image | video | gif | lottie
        'src'        => '',
        'media_id'   => 0,
        'poster_src' => '',
        'poster_id'  => 0,
        'autoplay'   => true,
        'loop'       => true,
        'mute'       => true,
        'speed'      => 1,
        'overlay'    => 0,
    ];
}

function cltd_theme_get_hero_background_defaults() {
    return [
        'interval' => 7000,
        'slides'   => [
            cltd_theme_get_hero_background_slide_defaults(),
        ],
    ];
}

/**
 * Retrieve hero background configuration.
 *
 * @return array
 */
function cltd_theme_get_hero_background() {
    $stored = get_option('cltd_hero_background', []);

    if (!is_array($stored)) {
        $stored = [];
    }

    if (isset($stored['type']) && !isset($stored['slides'])) {
        $stored = [
            'interval' => isset($stored['interval']) ? absint($stored['interval']) : 7000,
            'slides'   => [
                cltd_theme_sanitize_hero_background_slide($stored),
            ],
        ];
    }

    $background = wp_parse_args(
        $stored,
        cltd_theme_get_hero_background_defaults()
    );

    $background['interval'] = isset($background['interval'])
        ? max(2000, (int) $background['interval'])
        : 7000;

    $slides = [];
    if (isset($background['slides']) && is_array($background['slides'])) {
        foreach ($background['slides'] as $slide) {
            $sanitized = cltd_theme_sanitize_hero_background_slide($slide);
            if (!empty($sanitized['src'])) {
                $slides[] = $sanitized;
            }
        }
    }

    $background['slides'] = array_values($slides);

    return apply_filters('cltd_theme_hero_background', $background);
}

/**
 * Check whether the hero background has media configured.
 *
 * @param array|null $background
 * @return bool
 */
function cltd_theme_has_hero_background($background = null) {
    if (null === $background) {
        $background = cltd_theme_get_hero_background();
    }

    if (empty($background['slides']) || !is_array($background['slides'])) {
        return false;
    }

    foreach ($background['slides'] as $slide) {
        if (!empty($slide['src'])) {
            return true;
        }
    }

    return false;
}

/**
 * Prepare hero background data for JavaScript.
 *
 * @param array $background
 * @return array
 */
function cltd_theme_format_hero_background_for_js(array $background) {
    return [
        'interval' => isset($background['interval']) ? (int) $background['interval'] : 7000,
        'slides'   => array_map(
            function($slide) {
                return [
                    'type'     => $slide['type'],
                    'src'      => $slide['src'],
                    'poster'   => $slide['poster_src'],
                    'autoplay' => (bool) $slide['autoplay'],
                    'loop'     => (bool) $slide['loop'],
                    'mute'     => (bool) $slide['mute'],
                    'speed'    => (float) $slide['speed'],
                    'overlay'  => (float) $slide['overlay'],
                ];
            },
            isset($background['slides']) && is_array($background['slides']) ? $background['slides'] : []
        ),
    ];
}

/**
 * Sanitize hero background payload.
 *
 * @param array $input
 * @return array
 */
function cltd_theme_sanitize_hero_background($input) {
    $input = is_array($input) ? $input : [];
    $defaults = cltd_theme_get_hero_background_defaults();

    $output = [
        'interval' => isset($input['interval']) ? max(2000, (int) $input['interval']) : $defaults['interval'],
        'slides'   => [],
    ];

    if (isset($input['slides']) && is_array($input['slides'])) {
        foreach ($input['slides'] as $slide) {
            $sanitized = cltd_theme_sanitize_hero_background_slide($slide);
            if (!empty($sanitized['src'])) {
                $output['slides'][] = $sanitized;
            }
        }
    } elseif (!empty($input['src'])) {
        $sanitized = cltd_theme_sanitize_hero_background_slide($input);
        if (!empty($sanitized['src'])) {
            $output['slides'][] = $sanitized;
        }
    }

    return $output;
}

function cltd_theme_sanitize_hero_background_slide($slide) {
    $slide = is_array($slide) ? $slide : [];
    $defaults = cltd_theme_get_hero_background_slide_defaults();

    $output = $defaults;

    $type = isset($slide['type']) ? sanitize_text_field($slide['type']) : $defaults['type'];
    $allowed_types = ['image', 'video', 'gif', 'lottie'];
    $output['type'] = in_array($type, $allowed_types, true) ? $type : $defaults['type'];

    $output['src'] = isset($slide['src']) ? esc_url_raw($slide['src']) : '';
    $output['media_id'] = isset($slide['media_id']) ? absint($slide['media_id']) : 0;
    $output['poster_src'] = isset($slide['poster_src']) ? esc_url_raw($slide['poster_src']) : '';
    $output['poster_id'] = isset($slide['poster_id']) ? absint($slide['poster_id']) : 0;
    $output['autoplay'] = !empty($slide['autoplay']);
    $output['loop'] = !empty($slide['loop']);
    $output['mute'] = !empty($slide['mute']);
    $output['speed'] = isset($slide['speed']) ? max(0.1, (float) $slide['speed']) : $defaults['speed'];
    $output['overlay'] = isset($slide['overlay']) ? min(1, max(0, (float) $slide['overlay'])) : $defaults['overlay'];

    return $output;
}

/**
 * Render hero background markup.
 *
 * @param array $background
 * @return string
 */

function cltd_theme_output_global_hero_background() {
    static $printed = false;
    if ($printed) {
        return;
    }

    $hero_background = cltd_theme_get_hero_background();
    $hero_has_slider = cltd_theme_has_hero_background($hero_background);
    $hero_background_markup = $hero_has_slider ? cltd_theme_get_hero_background_markup($hero_background) : '';

    if (!$hero_has_slider && empty($hero_background_markup)) {
        $content_map = cltd_theme_get_content();
        $hero = isset($content_map['hero']) ? $content_map['hero'] : [];
        if (!empty($hero['background_image'])) {
            $hero_background_markup = sprintf(
                '<div class="hero-background hero-background--image"><div class="hero-background__image" style="background-image: url(%s);"></div></div>',
                esc_url($hero['background_image'])
            );
        }
    }

    if (!empty($hero_background_markup)) {
        $printed = true;
        echo $hero_background_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
add_action('wp_body_open', 'cltd_theme_output_global_hero_background', 5);

function cltd_theme_get_hero_background_markup(array $background) {
    if (empty($background['slides']) || !is_array($background['slides'])) {
        return '';
    }

    $slides = [];
    foreach ($background['slides'] as $slide) {
        if (!empty($slide['src'])) {
            $slides[] = $slide;
        }
    }

    if (empty($slides)) {
        return '';
    }

    $interval = max(2000, (int) ($background['interval'] ?? 7000));

    ob_start();
    ?>
    <div class="hero-background hero-background--slider" data-hero-slider data-interval="<?php echo esc_attr($interval); ?>">
        <?php foreach ($slides as $index => $slide) :
            $type = $slide['type'];
            $classes = ['hero-background__slide', 'hero-background__slide--' . $type];
            if (0 === $index) {
                $classes[] = 'is-active';
            }
            $overlay = min(1, max(0, (float) $slide['overlay']));
            ?>
            <div
                class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                data-hero-slide
                data-slide-index="<?php echo esc_attr($index); ?>"
                data-slide-type="<?php echo esc_attr($type); ?>"
                data-autoplay="<?php echo $slide['autoplay'] ? '1' : '0'; ?>"
                data-loop="<?php echo $slide['loop'] ? '1' : '0'; ?>"
                data-mute="<?php echo $slide['mute'] ? '1' : '0'; ?>"
                data-speed="<?php echo esc_attr($slide['speed']); ?>"
            >
                <?php
                switch ($type) {
                    case 'video':
                        ?>
                        <video
                            class="hero-background__video"
                            playsinline
                            preload="auto"
                            <?php if (!empty($slide['poster_src'])) : ?>
                                poster="<?php echo esc_url($slide['poster_src']); ?>"
                            <?php endif; ?>
                        >
                            <source src="<?php echo esc_url($slide['src']); ?>" type="<?php echo esc_attr(wp_check_filetype($slide['src'], null)['type'] ?? 'video/mp4'); ?>">
                        </video>
                        <?php
                        break;
                    case 'lottie':
                        ?>
                        <div
                            class="hero-background__lottie"
                            data-lottie-container
                            data-lottie-src="<?php echo esc_url($slide['src']); ?>"
                        ></div>
                        <?php
                        break;
                    default:
                        ?>
                        <div
                            class="hero-background__image"
                            style="background-image: url(<?php echo esc_url($slide['src']); ?>);"
                        ></div>
                        <?php
                        break;
                }
                ?>
                <?php if ($overlay > 0) : ?>
                    <div class="hero-background__overlay" style="opacity: <?php echo esc_attr($overlay); ?>;"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Add contextual class when a hero background is active.
 *
 * @param array $classes
 * @return array
 */
function cltd_theme_body_class($classes) {
    if (cltd_theme_has_hero_background()) {
        $classes[] = 'has-hero-background';
    } else {
        $content = cltd_theme_get_content();
        if (!empty($content['hero']['background_image'])) {
            $classes[] = 'has-hero-background';
        }
    }

    return $classes;
}
add_filter('body_class', 'cltd_theme_body_class');

/**
 * Allow JSON uploads for Lottie animations.
 *
 * @param array $mimes
 * @return array
 */
function cltd_theme_allow_json_upload($mimes) {
    $mimes['json'] = 'application/json';
    $mimes['jsonl'] = 'application/json';
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'cltd_theme_allow_json_upload');

/**
 * Register admin menu for hero background panel.
 */
function cltd_theme_register_admin_menu() {
    add_menu_page(
        __('Hero Background', 'cltd-theme-oct-2025'),
        __('Hero Background', 'cltd-theme-oct-2025'),
        'manage_options',
        'cltd-hero-background',
        'cltd_theme_render_hero_background_page',
        'dashicons-images-alt2',
        58
    );
}
add_action('admin_menu', 'cltd_theme_register_admin_menu');

/**
 * Enqueue admin assets for hero panel.
 *
 * @param string $hook
 */
function cltd_theme_admin_enqueue($hook) {
    if ($hook !== 'toplevel_page_cltd-hero-background') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style(
        'cltd-admin-hero',
        get_template_directory_uri() . '/css/admin-hero.css',
        [],
        file_exists(get_template_directory() . '/css/admin-hero.css') ? filemtime(get_template_directory() . '/css/admin-hero.css') : null
    );
    wp_enqueue_script(
        'cltd-admin-hero',
        get_template_directory_uri() . '/js/admin-hero.js',
        [],
        file_exists(get_template_directory() . '/js/admin-hero.js') ? filemtime(get_template_directory() . '/js/admin-hero.js') : null,
        true
    );

    wp_localize_script(
        'cltd-admin-hero',
        'CLTDHeroAdmin',
        [
            'chooseMedia'  => __('Choose Media', 'cltd-theme-oct-2025'),
            'choosePoster' => __('Choose Poster Image', 'cltd-theme-oct-2025'),
            'chooseLottie' => __('Choose Lottie JSON', 'cltd-theme-oct-2025'),
            'strings'      => [
                'addSlide'        => __('Add Slide', 'cltd-theme-oct-2025'),
                'removeSlide'     => __('Remove slide', 'cltd-theme-oct-2025'),
                'minimumSlides'   => __('At least one slide is required.', 'cltd-theme-oct-2025'),
                'slideLabel'      => __('Slide %s', 'cltd-theme-oct-2025'),
            ],
        ]
    );
}
add_action('admin_enqueue_scripts', 'cltd_theme_admin_enqueue');

/**
 * Render Hero Background admin panel.
 */

/**
 * Render fields for a single hero background slide.
 *
 * @param int|string $index
 * @param array      $slide
 * @param bool       $is_template
 * @return string
 */
function cltd_theme_render_hero_slide_fields($index, $slide = [], $is_template = false) {
    $defaults = cltd_theme_get_hero_background_slide_defaults();
    $slide = wp_parse_args($slide, $defaults);

    $name_key = $is_template ? '__INDEX__' : $index;
    $id_prefix = 'cltd-hero-slide-' . ($is_template ? '__INDEX__' : $index);
    $label_placeholder = $is_template ? '{{number}}' : (string) ($index + 1);

    ob_start();
    ?>
    <tbody class="cltd-hero-slide" data-slide data-slide-index="<?php echo esc_attr($name_key); ?>">
        <tr class="cltd-hero-slide__header">
            <th colspan="2">
                <div class="cltd-hero-slide__header-inner">
                    <span class="cltd-hero-slide__title" data-slide-label><?php printf(__('Slide %s', 'cltd-theme-oct-2025'), esc_html($label_placeholder)); ?></span>
                    <button type="button" class="button-link-delete" data-remove-slide><?php esc_html_e('Remove slide', 'cltd-theme-oct-2025'); ?></button>
                </div>
            </th>
        </tr>

        <tr data-background-groups="image,gif,video,lottie">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-type'); ?>"><?php esc_html_e('Background Type', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <select id="<?php echo esc_attr($id_prefix . '-type'); ?>" class="cltd-hero-type" name="background[slides][<?php echo esc_attr($name_key); ?>][type]" data-field="type">
                    <?php
                    $types = [
                        'image'  => __('Image', 'cltd-theme-oct-2025'),
                        'gif'    => __('GIF', 'cltd-theme-oct-2025'),
                        'video'  => __('Video', 'cltd-theme-oct-2025'),
                        'lottie' => __('Lottie JSON', 'cltd-theme-oct-2025'),
                    ];
                    foreach ($types as $value => $label) :
                        ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($slide['type'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Choose the media type displayed for this slide.', 'cltd-theme-oct-2025'); ?></p>
            </td>
        </tr>

        <tr data-background-groups="image,gif">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-media'); ?>"><?php esc_html_e('Media URL', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <input type="text" id="<?php echo esc_attr($id_prefix . '-media'); ?>" class="regular-text" name="background[slides][<?php echo esc_attr($name_key); ?>][src]" value="<?php echo esc_attr($slide['src']); ?>" placeholder="https://">
                <input type="hidden" id="<?php echo esc_attr($id_prefix . '-media-id'); ?>" name="background[slides][<?php echo esc_attr($name_key); ?>][media_id]" value="<?php echo esc_attr($slide['media_id']); ?>">
                <button type="button" class="button button-secondary" data-media-button data-library="image" data-target-input="<?php echo esc_attr($id_prefix . '-media'); ?>" data-target-id="<?php echo esc_attr($id_prefix . '-media-id'); ?>" data-title="<?php esc_attr_e('Select hero image', 'cltd-theme-oct-2025'); ?>" data-button="<?php esc_attr_e('Use this image', 'cltd-theme-oct-2025'); ?>">
                    <?php esc_html_e('Select Media', 'cltd-theme-oct-2025'); ?>
                </button>
            </td>
        </tr>

        <tr data-background-groups="video">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-video'); ?>"><?php esc_html_e('Video URL', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <input type="text" id="<?php echo esc_attr($id_prefix . '-video'); ?>" class="regular-text" name="background[slides][<?php echo esc_attr($name_key); ?>][src]" value="<?php echo esc_attr($slide['src']); ?>" placeholder="https://">
                <input type="hidden" id="<?php echo esc_attr($id_prefix . '-video-id'); ?>" name="background[slides][<?php echo esc_attr($name_key); ?>][media_id]" value="<?php echo esc_attr($slide['media_id']); ?>">
                <button type="button" class="button button-secondary" data-media-button data-library="video" data-target-input="<?php echo esc_attr($id_prefix . '-video'); ?>" data-target-id="<?php echo esc_attr($id_prefix . '-video-id'); ?>" data-title="<?php esc_attr_e('Select hero video', 'cltd-theme-oct-2025'); ?>" data-button="<?php esc_attr_e('Use this video', 'cltd-theme-oct-2025'); ?>">
                    <?php esc_html_e('Select Video', 'cltd-theme-oct-2025'); ?>
                </button>
            </td>
        </tr>

        <tr data-background-groups="video">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-poster'); ?>"><?php esc_html_e('Poster Image', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <input type="text" id="<?php echo esc_attr($id_prefix . '-poster'); ?>" class="regular-text" name="background[slides][<?php echo esc_attr($name_key); ?>][poster_src]" value="<?php echo esc_attr($slide['poster_src']); ?>" placeholder="https://">
                <input type="hidden" id="<?php echo esc_attr($id_prefix . '-poster-id'); ?>" name="background[slides][<?php echo esc_attr($name_key); ?>][poster_id]" value="<?php echo esc_attr($slide['poster_id']); ?>">
                <button type="button" class="button button-secondary" data-media-button data-library="image" data-target-input="<?php echo esc_attr($id_prefix . '-poster'); ?>" data-target-id="<?php echo esc_attr($id_prefix . '-poster-id'); ?>" data-title="<?php esc_attr_e('Select poster image', 'cltd-theme-oct-2025'); ?>" data-button="<?php esc_attr_e('Use this poster', 'cltd-theme-oct-2025'); ?>">
                    <?php esc_html_e('Select Poster', 'cltd-theme-oct-2025'); ?>
                </button>
            </td>
        </tr>

        <tr data-background-groups="lottie">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-lottie'); ?>"><?php esc_html_e('Lottie JSON URL', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <input type="text" id="<?php echo esc_attr($id_prefix . '-lottie'); ?>" class="regular-text" name="background[slides][<?php echo esc_attr($name_key); ?>][src]" value="<?php echo esc_attr($slide['src']); ?>" placeholder="https://">
                <input type="hidden" id="<?php echo esc_attr($id_prefix . '-lottie-id'); ?>" name="background[slides][<?php echo esc_attr($name_key); ?>][media_id]" value="<?php echo esc_attr($slide['media_id']); ?>">
                <button type="button" class="button button-secondary" data-media-button data-library="application/json" data-target-input="<?php echo esc_attr($id_prefix . '-lottie'); ?>" data-target-id="<?php echo esc_attr($id_prefix . '-lottie-id'); ?>" data-title="<?php esc_attr_e('Select Lottie animation', 'cltd-theme-oct-2025'); ?>" data-button="<?php esc_attr_e('Use this animation', 'cltd-theme-oct-2025'); ?>">
                    <?php esc_html_e('Select JSON', 'cltd-theme-oct-2025'); ?>
                </button>
            </td>
        </tr>

        <tr data-background-groups="video,lottie">
            <th scope="row"><?php esc_html_e('Playback', 'cltd-theme-oct-2025'); ?></th>
            <td class="cltd-hero-playback">
                <label>
                    <input type="checkbox" name="background[slides][<?php echo esc_attr($name_key); ?>][autoplay]" value="1" <?php checked($slide['autoplay']); ?>>
                    <?php esc_html_e('Autoplay', 'cltd-theme-oct-2025'); ?>
                </label>
                <label>
                    <input type="checkbox" name="background[slides][<?php echo esc_attr($name_key); ?>][loop]" value="1" <?php checked($slide['loop']); ?>>
                    <?php esc_html_e('Loop', 'cltd-theme-oct-2025'); ?>
                </label>
                <label data-background-only="video">
                    <input type="checkbox" name="background[slides][<?php echo esc_attr($name_key); ?>][mute]" value="1" <?php checked($slide['mute']); ?>>
                    <?php esc_html_e('Mute (recommended for autoplay)', 'cltd-theme-oct-2025'); ?>
                </label>
                <label data-background-only="lottie">
                    <?php esc_html_e('Speed', 'cltd-theme-oct-2025'); ?>
                    <input type="number" step="0.1" min="0.1" class="small-text" name="background[slides][<?php echo esc_attr($name_key); ?>][speed]" value="<?php echo esc_attr($slide['speed']); ?>">
                </label>
            </td>
        </tr>

        <tr data-background-groups="image,gif,video,lottie">
            <th scope="row">
                <label for="<?php echo esc_attr($id_prefix . '-overlay'); ?>"><?php esc_html_e('Overlay Opacity', 'cltd-theme-oct-2025'); ?></label>
            </th>
            <td>
                <input type="number" id="<?php echo esc_attr($id_prefix . '-overlay'); ?>" name="background[slides][<?php echo esc_attr($name_key); ?>][overlay]" value="<?php echo esc_attr($slide['overlay']); ?>" min="0" max="1" step="0.05">
            </td>
        </tr>
    </tbody>
    <?php

    return ob_get_clean();
}

/**
 * Register icon meta box for popup posts.
 */
function cltd_theme_register_popup_meta_boxes() {
    add_meta_box(
        'cltd-popup-icon',
        __('Popup Icon', 'cltd-theme-oct-2025'),
        'cltd_theme_render_popup_icon_meta_box',
        'cltd_popup',
        'side'
    );
}
add_action('add_meta_boxes', 'cltd_theme_register_popup_meta_boxes');

/**
 * Render popup icon meta box fields.
 *
 * @param WP_Post $post
 */
function cltd_theme_render_popup_icon_meta_box($post) {
    wp_nonce_field('cltd_popup_icon_meta', 'cltd_popup_icon_meta');
    $icon = get_post_meta($post->ID, 'cltd_popup_icon', true);
    ?>
    <div class="cltd-popup-icon">
        <p>
            <input type="text" class="widefat" id="cltd-popup-icon-input" name="cltd_popup_icon" value="<?php echo esc_attr($icon); ?>" placeholder="https://">
        </p>
        <p>
            <button type="button" class="button button-secondary" data-popup-icon-upload>
                <?php esc_html_e('Select Icon', 'cltd-theme-oct-2025'); ?>
            </button>
            <button type="button" class="button button-link-delete" data-popup-icon-clear>
                <?php esc_html_e('Remove', 'cltd-theme-oct-2025'); ?>
            </button>
        </p>
        <p class="description"><?php esc_html_e('Use an SVG, PNG, JPG, WebP, GIF, or hosted Lottie JSON animation. It will appear centered inside the popup button.', 'cltd-theme-oct-2025'); ?></p>
    </div>
    <?php
}

/**
 * Save popup icon meta.
 *
 * @param int $post_id
 */
function cltd_theme_save_popup_icon_meta($post_id) {
    if (!isset($_POST['cltd_popup_icon_meta']) || !wp_verify_nonce($_POST['cltd_popup_icon_meta'], 'cltd_popup_icon_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $icon = isset($_POST['cltd_popup_icon']) ? wp_unslash($_POST['cltd_popup_icon']) : '';
    if ($icon) {
        $icon_url = esc_url_raw($icon);
        update_post_meta($post_id, 'cltd_popup_icon', $icon_url);
        $icon_type = cltd_theme_detect_icon_type($icon_url);
        if (!$icon_type) {
            $icon_type = 'image';
        }
        update_post_meta($post_id, 'cltd_popup_icon_type', $icon_type);
    } else {
        delete_post_meta($post_id, 'cltd_popup_icon');
        delete_post_meta($post_id, 'cltd_popup_icon_type');
    }
}
add_action('save_post_cltd_popup', 'cltd_theme_save_popup_icon_meta');

/**
 * Normalize a stored popup icon type value.
 *
 * @param string $type Raw type.
 * @return string
 */
function cltd_theme_normalize_icon_type($type) {
    $type = sanitize_key($type);
    return in_array($type, ['svg', 'image', 'lottie'], true) ? $type : '';
}

/**
 * Infer the icon type from a URL.
 *
 * @param string $icon_url Icon URL.
 * @return string
 */
function cltd_theme_detect_icon_type($icon_url) {
    if (!$icon_url) {
        return '';
    }

    $path = (string) parse_url($icon_url, PHP_URL_PATH);
    $extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

    if ('json' === $extension) {
        return 'lottie';
    }

    if (in_array($extension, ['svg', 'svgz'], true)) {
        return 'svg';
    }

    if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'], true)) {
        return 'image';
    }

    return '';
}

/**
 * Resolve the icon type for a popup.
 *
 * @param int         $post_id  Popup post ID.
 * @param string|null $icon_url Optional URL for faster lookup.
 * @return string
 */
function cltd_theme_get_popup_icon_type($post_id, $icon_url = null) {
    $stored = get_post_meta($post_id, 'cltd_popup_icon_type', true);
    $normalized = cltd_theme_normalize_icon_type($stored);
    if ($normalized) {
        return $normalized;
    }

    $source = $icon_url;
    if (!$source) {
        $source = get_post_meta($post_id, 'cltd_popup_icon', true);
    }
    if ($source) {
        return cltd_theme_detect_icon_type($source);
    }

    return '';
}

/**
 * Enqueue popup admin assets.
 *
 * @param string $hook
 */
function cltd_theme_popup_admin_assets($hook) {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'cltd_popup') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'cltd-popup-admin',
        get_template_directory_uri() . '/js/popup-admin.js',
        [],
        file_exists(get_template_directory() . '/js/popup-admin.js') ? filemtime(get_template_directory() . '/js/popup-admin.js') : null,
        true
    );

    wp_localize_script(
        'cltd-popup-admin',
        'CLTDPopupAdmin',
        [
            'chooseIcon' => __('Choose Icon', 'cltd-theme-oct-2025'),
            'useIcon'    => __('Use this asset', 'cltd-theme-oct-2025'),
            'errorIcon'  => __('Please select an SVG, image (PNG/JPG/WebP/GIF), or Lottie JSON file.', 'cltd-theme-oct-2025'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'cltd_theme_popup_admin_assets');

/**
 * Register meta for page popup flag.
 */
function cltd_theme_register_page_popup_meta() {
    register_post_meta(
        'page',
        'cltd_open_in_popup',
        [
            'type'         => 'boolean',
            'single'       => true,
            'default'      => false,
            'show_in_rest' => true,
            'auth_callback' => function() {
                return current_user_can('edit_pages');
            },
        ]
    );
}
add_action('init', 'cltd_theme_register_page_popup_meta');

/**
 * Enqueue block editor panel for popup settings.
 */
function cltd_theme_enqueue_editor_popup_panel() {
    $script_path = get_template_directory() . '/js/editor-popup.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'cltd-editor-popup-panel',
        get_template_directory_uri() . '/js/editor-popup.js',
        ['wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-data'],
        filemtime($script_path),
        true
    );
}
add_action('enqueue_block_editor_assets', 'cltd_theme_enqueue_editor_popup_panel');

/**
 * Normalize a URL path for popup comparisons.
 *
 * @param string $url URL to normalize.
 * @return string
 */
function cltd_theme_normalize_popup_path($url) {
    if (empty($url)) {
        return '';
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts)) {
        return '';
    }

    $path = isset($parts['path']) ? (string) $parts['path'] : '';
    if ($path === '' || $path === false) {
        $path = '/';
    }

    $path = '/' . ltrim($path, '/');
    $path = rtrim($path, '/');
    if ($path === '') {
        $path = '/';
    }

    return strtolower($path);
}

/**
 * Retrieve published pages configured to open inside the popup.
 *
 * @return array
 */
function cltd_theme_get_popup_page_map() {
    static $cache = null;

    if (null !== $cache) {
        return $cache;
    }

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'meta_key'       => 'cltd_open_in_popup',
        'meta_value'     => 1,
        'suppress_filters' => false,
    ]);

    $map = [];
    if (!empty($pages)) {
        foreach ($pages as $page) {
            $permalink = get_permalink($page);
            $map[(int) $page->ID] = [
                'id'        => (int) $page->ID,
                'title'     => get_the_title($page),
                'slug'      => $page->post_name,
                'permalink' => $permalink,
                'path'      => cltd_theme_normalize_popup_path($permalink),
            ];
        }
    }

    $default_slugs = apply_filters('cltd_theme_default_popup_pages', ['privacy-policy', 'terms-of-service', 'returns-policy', 'refund-returns']);
    if (!empty($default_slugs)) {
        foreach ($default_slugs as $slug) {
            $slug = sanitize_title($slug);
            if (!$slug) {
                continue;
            }

            $page_obj = get_page_by_path($slug, OBJECT, ['page']);
            if (!$page_obj || 'publish' !== $page_obj->post_status) {
                continue;
            }

            $permalink = get_permalink($page_obj);
            $normalized = cltd_theme_normalize_popup_path($permalink);
            if (!$normalized) {
                continue;
            }

            foreach ($map as $page) {
                if (!empty($page['path']) && $page['path'] === $normalized) {
                    continue 2;
                }
            }

            $map[(int) $page_obj->ID] = [
                'id'        => (int) $page_obj->ID,
                'title'     => get_the_title($page_obj),
                'slug'      => $page_obj->post_name,
                'permalink' => $permalink,
                'path'      => $normalized,
            ];
        }
    }

    $cache = $map;

    return $cache;
}

/**
 * Determine whether a given page ID is configured for popup display.
 *
 * @param int $page_id Page ID.
 * @return bool
 */
function cltd_theme_is_page_popup_enabled($page_id) {
    $page_id = (int) $page_id;
    if ($page_id <= 0) {
        return false;
    }

    $map = cltd_theme_get_popup_page_map();
    return isset($map[$page_id]);
}

/**
 * Attempt to match a URL against the popup-enabled pages.
 *
 * @param string $url URL to test.
 * @return array|null
 */
function cltd_theme_find_popup_page_by_url($url) {
    if (empty($url)) {
        return null;
    }

    $map = cltd_theme_get_popup_page_map();
    if (empty($map)) {
        return null;
    }

    $normalized = cltd_theme_normalize_popup_path($url);
    if (!$normalized) {
        return null;
    }

    foreach ($map as $page) {
        if (!empty($page['path']) && $normalized === $page['path']) {
            return $page;
        }
    }

    $maybe_id = url_to_postid($url);
    if ($maybe_id && isset($map[$maybe_id])) {
        return $map[$maybe_id];
    }

    return null;
}

/**
 * Inject popup data attributes into an anchor HTML string.
 *
 * @param string $html Markup that contains an anchor.
 * @param array  $page Popup page data.
 * @return string
 */
function cltd_theme_inject_popup_attrs_into_anchor($html, array $page) {
    if (!is_string($html) || '' === $html) {
        return $html;
    }

    if (stripos($html, 'data-popup') !== false || stripos($html, 'data-cltd-auth-link') !== false) {
        return $html;
    }

    if (!preg_match('/<a\b[^>]*>/i', $html)) {
        return $html;
    }

    $slug = '';
    if (!empty($page['slug'])) {
        $slug = sanitize_title($page['slug']);
    } elseif (!empty($page['title'])) {
        $slug = sanitize_title($page['title']);
    }

    $attributes = [
        'data-popup'         => 'true',
        'data-popup-page-id' => isset($page['id']) ? (string) (int) $page['id'] : '',
        'data-popup-url'     => isset($page['permalink']) ? esc_url($page['permalink']) : '',
        'data-popup-title'   => isset($page['title']) ? wp_strip_all_tags($page['title']) : '',
        'data-gtm-popup'     => $slug,
    ];

    $pairs = [];
    foreach ($attributes as $name => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $pairs[] = sprintf('%s="%s"', esc_attr($name), esc_attr($value));
    }

    if (empty($pairs)) {
        return $html;
    }

    $attr_string = ' ' . implode(' ', $pairs);

    $updated = preg_replace('/<a\b([^>]*)>/i', '<a$1' . $attr_string . '>', $html, 1);

    return is_string($updated) && $updated !== '' ? $updated : $html;
}

/**
 * REST callback for loading page content inside the popup.
 *
 * @param WP_REST_Request $request Request object.
 * @return array|WP_Error
 */
function cltd_theme_rest_get_page_popup(WP_REST_Request $request) {
    $page_id = isset($request['id']) ? (int) $request['id'] : 0;

    if ($page_id <= 0) {
        return new WP_Error(
            'invalid_page',
            __('Invalid page.', 'cltd-theme-oct-2025'),
            ['status' => 400]
        );
    }

    if (!cltd_theme_is_page_popup_enabled($page_id)) {
        return new WP_Error(
            'not_found',
            __('Requested page is not available as a popup.', 'cltd-theme-oct-2025'),
            ['status' => 404]
        );
    }

    $page = get_post($page_id);
    if (!$page || 'page' !== $page->post_type || 'publish' !== $page->post_status) {
        return new WP_Error(
            'not_found',
            __('Requested page is not available as a popup.', 'cltd-theme-oct-2025'),
            ['status' => 404]
        );
    }

    $content = apply_filters('the_content', $page->post_content);

    return [
        'title'   => get_the_title($page),
        'content' => $content,
    ];
}

/**
 * Add popup attributes to navigation menu links targeting popup-enabled pages.
 *
 * @param array    $atts  Link attributes.
 * @param WP_Post  $item  Menu item.
 * @param stdClass $args  Menu args.
 * @param int      $depth Depth.
 * @return array
 */
function cltd_theme_add_popup_link_attributes($atts, $item, $args, $depth) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    if (!empty($atts['data-popup-slug'])) {
        return $atts;
    }

    if (!empty($atts['data-popup']) && 'true' === $atts['data-popup']) {
        return $atts;
    }

    $map = cltd_theme_get_popup_page_map();
    if (empty($map)) {
        return $atts;
    }

    $page = null;

    if (isset($item->object, $item->object_id) && 'page' === $item->object) {
        $object_id = (int) $item->object_id;
        if (isset($map[$object_id])) {
            $page = $map[$object_id];
        }
    }

    if (null === $page && !empty($item->url)) {
        $page = cltd_theme_find_popup_page_by_url($item->url);
    }

    if (empty($page)) {
        return $atts;
    }

    $atts['data-popup'] = 'true';
    $atts['data-popup-page-id'] = (string) $page['id'];
    $atts['data-popup-url'] = $page['permalink'];

    if (empty($atts['data-popup-title']) && !empty($item->title)) {
        $atts['data-popup-title'] = wp_strip_all_tags($item->title);
    }

    if (!empty($page['slug'])) {
        $atts['data-gtm-popup'] = sanitize_title($page['slug']);
    } elseif (!empty($page['title'])) {
        $atts['data-gtm-popup'] = sanitize_title($page['title']);
    }

    return $atts;
}
add_filter('nav_menu_link_attributes', 'cltd_theme_add_popup_link_attributes', 10, 4);

/**
 * Ensure cached nav menu markup still receives popup attributes.
 *
 * @param string $nav_menu Rendered menu HTML.
 * @param stdClass $args Menu args.
 * @return string
 */
function cltd_theme_filter_nav_menu_output($nav_menu, $args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    if (!is_string($nav_menu) || false === stripos($nav_menu, '<a ')) {
        return $nav_menu;
    }

    $nav_menu = preg_replace_callback(
        '/<a\b[^>]*>.*?<\/a>/is',
        function($matches) {
            return cltd_theme_adjust_nav_link_for_auth_state($matches[0]);
        },
        $nav_menu
    );

    $map = cltd_theme_get_popup_page_map();
    if (empty($map)) {
        return $nav_menu;
    }

    return preg_replace_callback(
        '/<a\b[^>]*>/i',
        function($matches) {
            $anchor = $matches[0];
            if (stripos($anchor, 'data-popup') !== false) {
                return $anchor;
            }

            if (!preg_match('/href="([^"]+)"/i', $anchor, $href_match)) {
                return $anchor;
            }

            $page = cltd_theme_find_popup_page_by_url($href_match[1]);
            if (!$page) {
                return $anchor;
            }

            return cltd_theme_inject_popup_attrs_into_anchor($anchor, $page);
        },
        $nav_menu
    );
}
add_filter('wp_nav_menu', 'cltd_theme_filter_nav_menu_output', 10, 2);

/**
 * Add popup attributes to Navigation block links when targeting popup-enabled pages.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Block data.
 * @return string
 */
function cltd_theme_render_navigation_link_block($block_content, $block) {
    if ('' === $block_content || !is_string($block_content)) {
        return $block_content;
    }

    $block_content = cltd_theme_adjust_nav_link_for_auth_state($block_content);

    if (empty($block['attrs']) || !is_array($block['attrs'])) {
        return $block_content;
    }

    $url = isset($block['attrs']['url']) ? $block['attrs']['url'] : '';
    if (!$url) {
        return $block_content;
    }

    $page = cltd_theme_find_popup_page_by_url($url);
    if (empty($page)) {
        return $block_content;
    }

    return cltd_theme_inject_popup_attrs_into_anchor($block_content, $page);
}
add_filter('render_block_core/navigation-link', 'cltd_theme_render_navigation_link_block', 10, 2);

/**
 * Conditionally hide blocks for logged out visitors when requested.
 *
 * @param string $block_content Rendered block markup.
 * @param array  $block         Block data/attributes.
 * @return string
 */
function cltd_theme_enforce_block_logged_in_visibility($block_content, $block) {
    if (is_admin()) {
        return $block_content;
    }

    if (empty($block['attrs']['cltdShowWhenLoggedIn'])) {
        return $block_content;
    }

    if (is_user_logged_in()) {
        return $block_content;
    }

    $login_notice = sprintf(
        '<h3><a href="%s">log-in</a> to see more info.</h3>',
        esc_url(home_url('/log-in'))
    );

    $message = apply_filters(
        'cltd_visibility_login_message',
        $login_notice,
        $block,
        $block_content
    );

    return is_string($message) ? wp_kses_post($message) : '';
}
add_filter('render_block', 'cltd_theme_enforce_block_logged_in_visibility', 10, 2);

/**
 * Update navigation link label/URL to reflect authentication state.
 *
 * @param string $block_content
 * @return string
 */
function cltd_theme_adjust_nav_link_for_auth_state($block_content) {
    if (!is_string($block_content) || '' === $block_content) {
        return $block_content;
    }

    $label = cltd_theme_extract_nav_label($block_content);
    if ('' === $label) {
        return $block_content;
    }

    $normalized = strtolower(trim($label));
    $normalized = preg_replace('/\s+/u', ' ', $normalized);

    $logged_in = is_user_logged_in();
    $new_label = '';
    $new_url   = '';

    if ($logged_in) {
        if (in_array($normalized, ['log in', 'login'], true)) {
            $new_label = __('Account', 'cltd-theme-oct-2025');
            $new_url   = home_url('/account');
        } elseif (in_array($normalized, ['sign up', 'signup', 'sign-up', 'register'], true)) {
            $new_label = __('Log Out', 'cltd-theme-oct-2025');
            $new_url   = wp_logout_url(home_url('/'));
        }
    } else {
        if (in_array($normalized, ['account', 'my account', 'profile'], true)) {
            $new_label = __('Log In', 'cltd-theme-oct-2025');
            $new_url   = home_url('/log-in');
        } elseif (in_array($normalized, ['log out', 'logout'], true)) {
            $new_label = __('Sign Up', 'cltd-theme-oct-2025');
            $new_url   = home_url('/sign-up');
        }
    }

    if ('' === $new_label) {
        return $block_content;
    }

    $updated = cltd_theme_replace_nav_label($block_content, $new_label);
    $updated = cltd_theme_replace_nav_href($updated, $new_url);
    $updated = cltd_theme_strip_popup_attrs($updated);
    $updated = cltd_theme_mark_auth_nav_link($updated);

    return $updated;
}

/**
 * Extract the navigation label from rendered HTML.
 *
 * @param string $html
 * @return string
 */
function cltd_theme_extract_nav_label($html) {
    if (preg_match('/<span[^>]*wp-block-navigation-item__label[^>]*>(.*?)<\/span>/is', $html, $match)) {
        return trim(wp_strip_all_tags($match[1]));
    }

    if (preg_match('/<a\b[^>]*>(.*?)<\/a>/is', $html, $match)) {
        return trim(wp_strip_all_tags($match[1]));
    }

    return '';
}

/**
 * Replace the navigation label text inside HTML.
 *
 * @param string $html
 * @param string $new_label
 * @return string
 */
function cltd_theme_replace_nav_label($html, $new_label) {
    if (preg_match('/(<span[^>]*wp-block-navigation-item__label[^>]*>)(.*?)(<\/span>)/is', $html, $match)) {
        $replacement = $match[1] . esc_html($new_label) . $match[3];
        return str_replace($match[0], $replacement, $html);
    }

    if (preg_match('/(<a\b[^>]*>)(.*?)(<\/a>)/is', $html, $match)) {
        $replacement = $match[1] . esc_html($new_label) . $match[3];
        return str_replace($match[0], $replacement, $html);
    }

    return $html;
}

/**
 * Update the href attribute for navigation links.
 *
 * @param string $html
 * @param string $url
 * @return string
 */
function cltd_theme_replace_nav_href($html, $url) {
    if (!$url) {
        return $html;
    }

    if (preg_match('/href="([^"]*)"/i', $html, $match)) {
        $replacement = sprintf('href="%s"', esc_url($url));
        return str_replace($match[0], $replacement, $html);
    }

    return $html;
}

/**
 * Remove popup data attributes from a navigation link.
 *
 * @param string $html
 * @return string
 */
function cltd_theme_strip_popup_attrs($html) {
    if (!is_string($html) || '' === $html) {
        return $html;
    }

    $attributes = [
        'data-popup',
        'data-popup-slug',
        'data-popup-page-id',
        'data-popup-url',
        'data-popup-title',
        'data-gtm-popup',
    ];

    foreach ($attributes as $attribute) {
        $html = preg_replace('/\s+' . preg_quote($attribute, '/') . '="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+' . preg_quote($attribute, '/') . "='[^']*'/i", '', $html);
    }

    return $html;
}

/**
 * Flag navigation links that were rewritten for auth state.
 *
 * @param string $html
 * @return string
 */
function cltd_theme_mark_auth_nav_link($html) {
    if (!is_string($html) || '' === $html) {
        return $html;
    }

    if (stripos($html, 'data-cltd-auth-link') !== false) {
        return $html;
    }

    return preg_replace('/<a\b([^>]*)>/i', '<a$1 data-cltd-auth-link="1">', $html, 1);
}

/**
 * Display popup group filter dropdown on Popup Items list table.
 *
 * @param string $post_type Current post type.
 */
function cltd_theme_popup_group_admin_filter($post_type) {
    if ('cltd_popup' !== $post_type) {
        return;
    }

    $groups = get_posts([
        'post_type'      => 'popup_group',
        'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    if (empty($groups)) {
        return;
    }

    $selected_group = isset($_GET['cltd_popup_group_filter']) ? (int) $_GET['cltd_popup_group_filter'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    echo '<label for="cltd-popup-group-filter" class="screen-reader-text">' . esc_html__('Filter by popup group', 'cltd-theme-oct-2025') . '</label>';
    echo '<select name="cltd_popup_group_filter" id="cltd-popup-group-filter">';
    echo '<option value="">' . esc_html__('All popup groups', 'cltd-theme-oct-2025') . '</option>';

    foreach ($groups as $group_id) {
        $title = get_the_title($group_id);
        printf(
            '<option value="%1$d"%2$s>%3$s</option>',
            (int) $group_id,
            selected($selected_group, $group_id, false),
            esc_html($title)
        );
    }

    echo '</select>';
}
add_action('restrict_manage_posts', 'cltd_theme_popup_group_admin_filter');

/**
 * Modify Popup Items admin query when filtering by popup group.
 *
 * @param WP_Query $query Query instance.
 */
function cltd_theme_filter_popups_by_group($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');
    if ('cltd_popup' !== $post_type) {
        if (!is_array($post_type) || !in_array('cltd_popup', $post_type, true)) {
            return;
        }
    }

    $selected_group = isset($_GET['cltd_popup_group_filter']) ? (int) $_GET['cltd_popup_group_filter'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!$selected_group) {
        return;
    }

    $raw_items = get_post_meta($selected_group, 'cltd_popup_items', true);
    $prepared_items = cltd_theme_prepare_popup_items($raw_items);

    $popup_ids = [];
    foreach ($prepared_items as $item) {
        if (!empty($item['popup_id'])) {
            $popup_ids[] = (int) $item['popup_id'];
            continue;
        }

        if (!empty($item['slug'])) {
            $popup_from_slug = get_page_by_path(sanitize_title($item['slug']), OBJECT, 'cltd_popup');
            if ($popup_from_slug) {
                $popup_ids[] = (int) $popup_from_slug->ID;
            }
        }
    }

    $popup_ids = array_values(array_unique(array_filter($popup_ids)));
    if (empty($popup_ids)) {
        $popup_ids = [0];
    }

    $query->set('post__in', $popup_ids);
    $query->set('orderby', 'post__in');
}
add_action('pre_get_posts', 'cltd_theme_filter_popups_by_group');

function cltd_theme_render_hero_background_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $background = cltd_theme_get_hero_background();
    $slides = !empty($background['slides']) ? $background['slides'] : [cltd_theme_get_hero_background_slide_defaults()];
    $next_index = count($slides);
    $notice = isset($_GET['updated']) ? sanitize_text_field(wp_unslash($_GET['updated'])) : '';
    ?>
    <div class="wrap cltd-hero-admin">
        <h1><?php esc_html_e('Hero Background', 'cltd-theme-oct-2025'); ?></h1>

        <?php if ('true' === $notice) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Hero background updated.', 'cltd-theme-oct-2025'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('cltd_save_hero_background'); ?>
            <input type="hidden" name="action" value="cltd_save_hero_background">

            <table class="form-table" role="presentation" id="cltd-hero-slides" data-next-index="<?php echo esc_attr($next_index); ?>">
                <?php foreach ($slides as $i => $slide) : ?>
                    <?php echo cltd_theme_render_hero_slide_fields($i, $slide); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </table>

            <p>
                <button type="button" class="button" data-add-slide>
                    <?php esc_html_e('Add Slide', 'cltd-theme-oct-2025'); ?>
                </button>
            </p>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="cltd-hero-interval"><?php esc_html_e('Slide Interval (ms)', 'cltd-theme-oct-2025'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cltd-hero-interval" name="background[interval]" value="<?php echo esc_attr($background['interval']); ?>" min="2000" step="500">
                            <p class="description"><?php esc_html_e('Time each slide remains visible before advancing.', 'cltd-theme-oct-2025'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template id="cltd-hero-slide-template">
                <?php echo cltd_theme_render_hero_slide_fields('__INDEX__', cltd_theme_get_hero_background_slide_defaults(), true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </template>

            <?php submit_button(__('Save Hero Background', 'cltd-theme-oct-2025')); ?>
        </form>
    </div>
    <?php
}
function cltd_theme_save_hero_background() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to update the hero background.', 'cltd-theme-oct-2025'));
    }

    check_admin_referer('cltd_save_hero_background');

    $payload = isset($_POST['background']) ? wp_unslash($_POST['background']) : [];
    $sanitized = cltd_theme_sanitize_hero_background($payload);
    update_option('cltd_hero_background', $sanitized);

    $redirect = add_query_arg(
        'updated',
        'true',
        wp_get_referer() ?: admin_url('admin.php?page=cltd-hero-background')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_cltd_save_hero_background', 'cltd_theme_save_hero_background');

/**
 * Default content map used when the Options API has not been populated yet.
 *
 * @return array
 */
function cltd_theme_get_content_defaults() {
    return [
        'sidebar_socials' => [
            [
                'label'  => __('Github', 'cltd-theme-oct-2025'),
                'url'    => 'https://github.com/crystalthedeveloper',
                'target' => '_blank',
            ],
            [
                'label'  => __('Instagram', 'cltd-theme-oct-2025'),
                'url'    => 'https://www.instagram.com/crystalthedeveloper',
                'target' => '_blank',
            ],
            [
                'label'  => __('Facebook', 'cltd-theme-oct-2025'),
                'url'    => 'https://www.facebook.com/Crystalthedeveloper',
                'target' => '_blank',
            ],
            [
                'label'  => __('YouTube', 'cltd-theme-oct-2025'),
                'url'    => 'https://www.youtube.com/channel/UCeUkpwkof62DlSAU9C2uLtA',
                'target' => '_blank',
            ],
            [
                'label'  => __('LinkedIn', 'cltd-theme-oct-2025'),
                'url'    => 'https://www.linkedin.com/in/crystal-lewis-b14b7386',
                'target' => '_blank',
            ],
        ],
        'auth_links'      => [
            [
                'label' => __('Logout', 'cltd-theme-oct-2025'),
                'url'   => wp_logout_url(),
            ],
            [
                'label' => __('Contact', 'cltd-theme-oct-2025'),
                'url'   => 'mailto:contact@crystalthedeveloper.ca',
            ],
            [
                'label' => __('Sign Up', 'cltd-theme-oct-2025'),
                'url'   => wp_registration_url(),
            ],
            [
                'label' => __('Account', 'cltd-theme-oct-2025'),
                'url'   => admin_url('profile.php'),
            ],
        ],
        'hero'            => [
            'title'            => __('Your CMS, My code.', 'cltd-theme-oct-2025'),
            'subtitle'         => __('Scalable, accessible websites built for performance and growth.', 'cltd-theme-oct-2025'),
            'tagline'          => '',
            'background_image' => '',
            'buttons'          => [],
        ],
        'sections'        => [],
        'footer'          => [
            'text'  => __('2025 © Crystal The Developer Inc. All rights reserved', 'cltd-theme-oct-2025'),
            'links' => [
                ['label' => __('Terms of Service', 'cltd-theme-oct-2025'), 'url' => '/terms-of-service'],
                ['label' => __('Privacy Policy', 'cltd-theme-oct-2025'), 'url' => '/privacy-policy'],
                ['label' => __('Refund & Returns', 'cltd-theme-oct-2025'), 'url' => '/refund-returns'],
            ],
        ],
    ];
}

/**
 * Retrieve theme content structure and merge with stored options.
 *
 * @return array
 */
function cltd_theme_get_content() {
    $defaults = cltd_theme_get_content_defaults();
    $settings = get_option('cltd_theme_content', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    $content = array_replace_recursive($defaults, $settings);

    $auto_sections = cltd_theme_build_popup_sections();
    if (!empty($auto_sections)) {
        $manual_sections = isset($content['sections']) && is_array($content['sections'])
            ? $content['sections']
            : [];
        $content['sections'] = cltd_theme_merge_sections($auto_sections, $manual_sections);
    }

    /**
     * Allow the admin plugin or other integrations to filter the content payload.
     *
     * @param array $content
     */
    return apply_filters('cltd_theme_content', $content);
}

add_filter('cltd_theme_content', 'cltd_theme_normalize_footer_links', 25);

function cltd_theme_normalize_footer_links($content) {
    if (empty($content['footer']['links']) || !is_array($content['footer']['links'])) {
        return $content;
    }

    $targets = [
        'terms of service' => '/terms-of-service',
        'terms & conditions' => '/terms-of-service',
        'privacy policy' => '/privacy-policy',
        'privacy & cookies' => '/privacy-policy',
        'returns policy' => '/returns-policy',
        'return policy' => '/returns-policy',
        'refund & returns' => '/refund-returns',
        'refunds & returns' => '/refund-returns',
        'refund policy' => '/refund-returns',
    ];

    foreach ($content['footer']['links'] as &$link) {
        if (!is_array($link)) {
            continue;
        }
        $label = isset($link['label']) ? strtolower(trim(wp_strip_all_tags($link['label']))) : '';
        if (isset($targets[$label])) {
            $link['url'] = $targets[$label];
        }
    }
    unset($link);

    return $content;
}

/**
 * Check if any popup icons require the Lottie library.
 *
 * @return bool
 */
function cltd_theme_site_has_lottie_icons() {
    static $has_lottie_icons = null;

    if (null !== $has_lottie_icons) {
        return $has_lottie_icons;
    }

    $query = new WP_Query([
        'post_type'      => 'cltd_popup',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'cltd_popup_icon_type',
                'value'   => 'lottie',
                'compare' => '=',
            ],
            [
                'key'     => 'cltd_popup_icon',
                'value'   => '.json',
                'compare' => 'LIKE',
            ],
        ],
    ]);

    $has_lottie_icons = $query->have_posts();
    wp_reset_postdata();

    return $has_lottie_icons;
}

/**
 * Register scripts and expose runtime config to the frontend.
 */
function cltd_theme_scripts() {
    $style_path = get_stylesheet_directory() . '/style.css';
    $script_path = get_template_directory() . '/js/main.js';
    $hero_background = cltd_theme_get_hero_background();
    $script_dependencies = [];

    wp_enqueue_style(
        'cltd-style',
        get_stylesheet_uri(),
        [
            'wp-block-library',
            'wp-block-library-theme',
            'global-styles',
        ],
        file_exists($style_path) ? filemtime($style_path) : null
    );

    $needs_lottie = false;
    if (!empty($hero_background['slides']) && is_array($hero_background['slides'])) {
        foreach ($hero_background['slides'] as $slide) {
            if (!empty($slide['src']) && isset($slide['type']) && $slide['type'] === 'lottie') {
                $needs_lottie = true;
                break;
            }
        }
    }

    if (!$needs_lottie && cltd_theme_site_has_lottie_icons()) {
        $needs_lottie = true;
    }

    if ($needs_lottie) {
        wp_enqueue_script(
            'cltd-lottie',
            'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js',
            [],
            '5.12.2',
            true
        );
        $script_dependencies[] = 'cltd-lottie';
    }

    wp_enqueue_script(
        'cltd-main',
        get_template_directory_uri() . '/js/main.js',
        $script_dependencies,
        file_exists($script_path) ? filemtime($script_path) : null,
        true
    );

    if (class_exists('WooCommerce')) {
        if (wp_script_is('wc-add-to-cart', 'registered')) {
            wp_enqueue_script('wc-add-to-cart');
        }
        if (wp_script_is('wc-cart-fragments', 'registered')) {
            wp_enqueue_script('wc-cart-fragments');
        }
    }

    $popup_page_map = cltd_theme_get_popup_page_map();
    $popup_pages = array_values(
        array_map(
            static function($page) {
                return [
                    'id'        => isset($page['id']) ? (int) $page['id'] : 0,
                    'title'     => isset($page['title']) ? wp_strip_all_tags($page['title']) : '',
                    'permalink' => isset($page['permalink']) ? esc_url_raw($page['permalink']) : '',
                    'slug'      => isset($page['slug']) ? sanitize_title($page['slug']) : '',
                ];
            },
            $popup_page_map
        )
    );

    wp_localize_script(
        'cltd-main',
        'CLTDTheme',
        [
            'restUrl' => esc_url_raw(rest_url('cltd/v1/popup/')),
            'pagePopupRestUrl' => esc_url_raw(rest_url('cltd/v1/page-popup/')),
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'popupPages' => $popup_pages,
            'heroBackground' => cltd_theme_format_hero_background_for_js($hero_background),
            'homeUrl' => esc_url_raw(home_url('/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'loading' => __('Loading popup…', 'cltd-theme-oct-2025'),
                'error'   => __('We could not load that content right now. Please try again.', 'cltd-theme-oct-2025'),
                'close'   => __('Close popup', 'cltd-theme-oct-2025'),
                'loginProcessing' => __('Logging you in…', 'cltd-theme-oct-2025'),
                'loginError' => __('We couldn’t log you in. Please try again.', 'cltd-theme-oct-2025'),
            ],
        ]
    );
}
add_action('wp_enqueue_scripts', 'cltd_theme_scripts');

/**
 * Build a section array from popup taxonomy assignments.
 *
 * @return array
 */
function cltd_theme_build_popup_sections() {
    $groups = cltd_theme_get_popup_groups_data();
    if (empty($groups)) {
        return [];
    }

    $sections = [];
    foreach ($groups as $group) {
        $sections[] = [
            'title'       => $group['title'],
            'description' => $group['description'],
            'items'       => $group['items'],
        ];
    }

    /**
     * Allow customization of the generated sections array.
     *
     * @param array $sections
     */
    return apply_filters('cltd_theme_popup_sections', $sections);
}

/**
 * Merge taxonomy-driven sections with manually configured sections.
 *
 * @param array $auto_sections
 * @param array $manual_sections
 * @return array
 */
function cltd_theme_merge_sections(array $auto_sections, array $manual_sections) {
    if (empty($manual_sections)) {
        return $auto_sections;
    }

    $normalized_manual = [];
    $manual_without_title = [];

    foreach ($manual_sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $title = isset($section['title']) ? wp_strip_all_tags($section['title']) : '';
        if ($title !== '') {
            $normalized_manual[strtolower($title)] = $section;
        } else {
            $manual_without_title[] = $section;
        }
    }

    $merged = [];

    foreach ($auto_sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $title = isset($section['title']) ? wp_strip_all_tags($section['title']) : '';
        if ($title === '') {
            continue;
        }

        $key = strtolower($title);
        if (isset($normalized_manual[$key])) {
            $manual = $normalized_manual[$key];

            if (!empty($manual['description'])) {
                $section['description'] = $manual['description'];
            }

            $auto_items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
            $manual_items = isset($manual['items']) && is_array($manual['items']) ? $manual['items'] : [];

            $existing_keys = [];
            foreach ($auto_items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $identifier = isset($item['type']) && $item['type'] === 'link'
                    ? ('link::' . ($item['url'] ?? ''))
                    : ('popup::' . ($item['slug'] ?? ($item['label'] ?? '')));
                $existing_keys[$identifier] = true;
            }

            foreach ($manual_items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $identifier = isset($item['type']) && $item['type'] === 'link'
                    ? ('link::' . ($item['url'] ?? ''))
                    : ('popup::' . ($item['slug'] ?? ($item['label'] ?? '')));

                if ($identifier && isset($existing_keys[$identifier])) {
                    continue;
                }

                $auto_items[] = $item;
                if ($identifier) {
                    $existing_keys[$identifier] = true;
                }
            }

            $section['items'] = $auto_items;
            unset($normalized_manual[$key]);
        }

        $merged[] = $section;
    }

    if (!empty($normalized_manual)) {
        foreach ($normalized_manual as $section) {
            $merged[] = $section;
        }
    }

    if (!empty($manual_without_title)) {
        foreach ($manual_without_title as $section) {
            $merged[] = $section;
        }
    }

    return $merged;
}

/**
 * REST endpoint for popup content.
 */
add_action('rest_api_init', function() {
    register_rest_route(
        'cltd/v1',
        '/popup/(?P<slug>[a-zA-Z0-9-]+)',
        [
            'methods'             => 'GET',
            'callback'            => function($data) {
                $slug = sanitize_title($data['slug']);
                $page = get_page_by_path($slug, OBJECT, ['page', 'cltd_popup']);

                if (!$page) {
                    return new WP_Error(
                        'not_found',
                        __('Popup not found', 'cltd-theme-oct-2025'),
                        ['status' => 404]
                    );
                }

                $content = apply_filters('the_content', $page->post_content);

                $tag_posts_markup = '';
                $tag = get_term_by('slug', $slug, 'post_tag');
                if ($tag && !is_wp_error($tag)) {
                    $posts = get_posts([
                        'post_type'      => 'post',
                        'post_status'    => 'publish',
                        'posts_per_page' => apply_filters('cltd_theme_popup_tag_posts_per_page', 6, $tag),
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'tax_query'      => [
                            [
                                'taxonomy' => 'post_tag',
                                'field'    => 'term_id',
                                'terms'    => (int) $tag->term_id,
                            ],
                        ],
                    ]);

                    if (!empty($posts)) {
                        $label = sprintf(
                            /* translators: %s: tag name */
                            __('Latest %s Posts', 'cltd-theme-oct-2025'),
                            $tag->name
                        );

                        ob_start();
                        ?>
                        <section class="popup-posts" aria-labelledby="popup-tag-<?php echo esc_attr($tag->slug); ?>-title">
                            <h4 id="popup-tag-<?php echo esc_attr($tag->slug); ?>-title" class="popup-posts__title">
                                <?php echo esc_html($label); ?>
                            </h4>
                            <ul class="popup-posts__list">
                                <?php foreach ($posts as $post) :
                                    $title = get_the_title($post);
                                    $content_html = apply_filters('the_content', get_the_content(null, false, $post));
                                    ?>
                                    <li class="popup-posts__item">
                                        <h3 class="popup-posts__link">
                                            <?php echo esc_html($title); ?>
                                        </h3>
                                        <?php if (!empty($content_html)) : ?>
                                            <div class="popup-posts__excerpt"><?php echo wp_kses_post($content_html); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                        <?php
                        $tag_posts_markup = ob_get_clean();
                    }
                }

                if ($tag_posts_markup) {
                    $content .= $tag_posts_markup;
                }

                return [
                    'title'   => get_the_title($page),
                    'content' => $content,
                ];
            },
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'cltd/v1',
        '/page-popup/(?P<id>\d+)',
        [
            'methods'             => 'GET',
            'callback'            => 'cltd_theme_rest_get_page_popup',
            'permission_callback' => '__return_true',
        ]
    );
});

/**
 * Add duplicate action for popup posts.
 *
 * @param array   $actions
 * @param WP_Post $post
 * @return array
 */
function cltd_theme_popup_row_actions($actions, $post) {
    if ($post->post_type !== 'cltd_popup') {
        return $actions;
    }

    if (!current_user_can('edit_post', $post->ID)) {
        return $actions;
    }

    $url = wp_nonce_url(
        admin_url('admin-post.php?action=cltd_duplicate_popup&post=' . $post->ID),
        'cltd_duplicate_popup_' . $post->ID
    );

    $actions['cltd_duplicate_popup'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Duplicate', 'cltd-theme-oct-2025')
    );

    return $actions;
}
add_filter('post_row_actions', 'cltd_theme_popup_row_actions', 10, 2);

/**
 * Handle popup duplication requests.
 */
function cltd_theme_handle_popup_duplicate() {
    if (!isset($_GET['post'])) {
        wp_die(__('Missing popup to duplicate.', 'cltd-theme-oct-2025'));
    }

    $post_id = absint($_GET['post']);
    $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!$post_id || !wp_verify_nonce($nonce, 'cltd_duplicate_popup_' . $post_id)) {
        wp_die(__('You are not allowed to duplicate this popup.', 'cltd-theme-oct-2025'));
    }

    $original = get_post($post_id);
    if (!$original || $original->post_type !== 'cltd_popup') {
        wp_die(__('Popup not found.', 'cltd-theme-oct-2025'));
    }

    if (!current_user_can('edit_post', $post_id)) {
        wp_die(__('You are not allowed to duplicate this popup.', 'cltd-theme-oct-2025'));
    }

    $current_user = get_current_user_id();

    $new_post_id = wp_insert_post([
        'post_title'   => $original->post_title ? sprintf(__('%s (Copy)', 'cltd-theme-oct-2025'), $original->post_title) : __('Popup (Copy)', 'cltd-theme-oct-2025'),
        'post_content' => $original->post_content,
        'post_excerpt' => $original->post_excerpt,
        'post_status'  => 'draft',
        'post_type'    => 'cltd_popup',
        'post_author'  => $current_user ? $current_user : $original->post_author,
    ]);

    if (!$new_post_id || is_wp_error($new_post_id)) {
        wp_die(__('Could not create popup copy.', 'cltd-theme-oct-2025'));
    }

    $taxonomies = get_object_taxonomies('cltd_popup');
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (!empty($terms) && !is_wp_error($terms)) {
            wp_set_object_terms($new_post_id, $terms, $taxonomy);
        }
    }

    $meta = get_post_meta($post_id);
    foreach ($meta as $key => $values) {
        if ($key === '_edit_lock' || $key === '_edit_last') {
            continue;
        }
        foreach ($values as $value) {
            add_post_meta($new_post_id, $key, maybe_unserialize($value));
        }
    }

    $redirect = admin_url('post.php?action=edit&post=' . $new_post_id);
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_cltd_duplicate_popup', 'cltd_theme_handle_popup_duplicate');

/**
 * Render the legacy one-page layout region (hero, sections, modal).
 *
 * @return string
 */
function cltd_theme_get_legacy_layout_markup() {
    $content_map = cltd_theme_get_content();

    $hero     = $content_map['hero'] ?? [];
    $sections = isset($content_map['sections']) && is_array($content_map['sections'])
        ? $content_map['sections']
        : [];


    ob_start();
    ?>
    <div class="page-wrapper">
        <main class="content">
            <?php
    $popup_groups = cltd_theme_get_popup_groups_data();
    if (!empty($popup_groups)) :
        ?>
        <section class="grid">
            <?php
            foreach ($popup_groups as $group) {
                $section_html = cltd_theme_render_popup_group_section($group);
                if ($section_html) {
                    echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            ?>
        </section>
    <?php elseif (!empty($sections)) : ?>
        <section class="grid">
            <?php
            foreach ($sections as $group) {
                if (!is_array($group)) {
                    continue;
                }

                $fallback_group = [
                    'id'          => 0,
                    'title'       => isset($group['title']) ? $group['title'] : '',
                    'description' => isset($group['description']) ? $group['description'] : '',
                    'items'       => cltd_theme_prepare_popup_items(isset($group['items']) ? $group['items'] : []),
                    'grid_span'   => isset($group['grid_span']) ? (int) $group['grid_span'] : 1,
                    'grid_order'  => isset($group['grid_order']) ? (int) $group['grid_order'] : null,
                    'menu_order'  => isset($group['menu_order']) ? (int) $group['menu_order'] : 0,
                ];

                $section_html = cltd_theme_render_popup_group_section($fallback_group);
                if ($section_html) {
                    echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            ?>
        </section>
    <?php endif; ?>
        </main>
    </div>

    <?php cltd_theme_render_popup_modal(); ?>
    <?php

    return (string) ob_get_clean();
}

function cltd_theme_legacy_layout_shortcode() {
    return cltd_theme_get_legacy_layout_markup();
}
add_shortcode('cltd_legacy_layout', 'cltd_theme_legacy_layout_shortcode');

/**
 * Return popup modal markup.
 *
 * @return string
 */
function cltd_theme_get_popup_modal_markup() {
    ob_start();
    ?>
    <div class="cltd-modal" data-popup-modal hidden>
        <div class="cltd-modal__backdrop" data-popup-close></div>
        <div class="cltd-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cltd-modal-title" aria-describedby="cltd-modal-content" tabindex="-1">
            <button type="button" class="cltd-modal__close" data-popup-close aria-label="<?php esc_attr_e('Close popup', 'cltd-theme-oct-2025'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="cltd-modal__body">
                <h2 id="cltd-modal-title" class="cltd-modal__title"></h2>
                <div id="cltd-modal-content" class="cltd-modal__content">
                    <p class="cltd-modal__status"><?php esc_html_e('Select a module to view details.', 'cltd-theme-oct-2025'); ?></p>
                </div>
                <div class="cltd-modal__scroll-indicator" data-popup-scroll-indicator>
                    <span class="cltd-modal__scroll-indicator-icon" aria-hidden="true">↓</span>
                    <span class="sr-only"><?php esc_html_e('Scroll to see more content.', 'cltd-theme-oct-2025'); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Output popup modal markup once per request.
 *
 * @return void
 */
function cltd_theme_render_popup_modal() {
    static $printed = false;
    if ($printed) {
        return;
    }

    $markup = cltd_theme_get_popup_modal_markup();
    if ($markup) {
        $printed = true;
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
add_action('wp_footer', 'cltd_theme_render_popup_modal', 1);

// === [ CLTD: WooCommerce Product Shortcodes for Popups — Full Detail Version ] === //

/**
 * Format multi-sentence text into paragraphs or list items for shortcode output.
 *
 * @param string $text Raw text from meta.
 * @return string HTML string.
 */
function cltd_theme_format_product_text_block($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    if (strpos($text, "
") !== false) {
        return wpautop($text);
    }

    $parts = array_filter(array_map('trim', explode(' - ', $text)));
    if (count($parts) > 1) {
        $items = array_map(function($part) {
            return '<li>' . esc_html($part) . '</li>';
        }, $parts);
        return '<ul class="cltd-text-list">' . implode('', $items) . '</ul>';
    }

    return wpautop($text);
}

/**
 * Sort WP_Post objects by a numeric meta key (high to low).
 *
 * Posts without the meta key are placed last while preserving a deterministic
 * fallback order using menu order and title.
 *
 * @param WP_Post[] $posts
 * @param string    $meta_key
 * @return WP_Post[]
 */
function cltd_theme_sort_posts_by_numeric_meta_desc(array $posts, $meta_key) {
    if (!$meta_key || empty($posts)) {
        return $posts;
    }

    $enriched = array_map(function($post) use ($meta_key) {
        $raw = get_post_meta($post->ID, $meta_key, true);
        $value = ($raw === '' || $raw === null) ? null : (float) $raw;

        return [
            'post'  => $post,
            'value' => $value,
        ];
    }, $posts);

    usort($enriched, function($a, $b) {
        $a_value = $a['value'];
        $b_value = $b['value'];

        if ($a_value === $b_value) {
            $a_menu = (int) $a['post']->menu_order;
            $b_menu = (int) $b['post']->menu_order;

            if ($a_menu === $b_menu) {
                return strcasecmp($a['post']->post_title, $b['post']->post_title);
            }

            return $a_menu <=> $b_menu;
        }

        if ($a_value === null) {
            return 1;
        }

        if ($b_value === null) {
            return -1;
        }

        return $b_value <=> $a_value;
    });

    return array_map(function($item) {
        return $item['post'];
    }, $enriched);
}
