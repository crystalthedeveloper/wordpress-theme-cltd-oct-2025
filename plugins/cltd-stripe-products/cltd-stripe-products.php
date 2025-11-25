<?php
/**
 * Plugin Name:       CLTD Stripe Products
 * Description:       Display Stripe products via shortcodes and redirect visitors to Stripe Checkout—without WooCommerce.
 * Version:           1.0.0
 * Author:            Crystal The Developer
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package CLTD_Stripe_Products
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CLTD_Stripe_Products_Plugin {
    public const VERSION = '1.0.0';
    public const OPTION_KEY = 'cltd_stripe_products_settings';

    private static $instance = null;
    private $api = null;
    private $assets_enqueued = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        new CLTD_Stripe_Admin_Settings($this);
        new CLTD_Stripe_Shortcodes($this);
        new CLTD_Stripe_Checkout($this);

        add_action('wp_enqueue_scripts', [$this, 'register_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'auto_enqueue_assets'], 20);
        add_action('admin_notices', [$this, 'maybe_render_error_notice']);
    }

    /**
     * Retrieve plugin settings array.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = [
            'publishable_key' => '',
            'secret_key'      => '',
        ];

        $options = get_option(self::OPTION_KEY, []);

        if (!is_array($options)) {
            $options = [];
        }

        return wp_parse_args($options, $defaults);
    }

    /**
     * Return the saved Stripe secret key.
     *
     * @return string
     */
    public function get_secret_key() {
        $settings = $this->get_settings();
        return isset($settings['secret_key']) ? trim($settings['secret_key']) : '';
    }

    /**
     * Return the saved Stripe publishable key.
     *
     * @return string
     */
    public function get_publishable_key() {
        $settings = $this->get_settings();
        return isset($settings['publishable_key']) ? trim($settings['publishable_key']) : '';
    }

    /**
     * Lazily instantiate the API helper.
     *
     * @return CLTD_Stripe_API
     */
    public function get_api() {
        if (null === $this->api) {
            $this->api = new CLTD_Stripe_API($this);
        }

        return $this->api;
    }

    /**
     * Register (but not enqueue) the frontend script.
     */
    public function register_scripts() {
        wp_register_style(
            'cltd-stripe-products-style',
            plugins_url('assets/css/cltd-stripe-products.css', __FILE__),
            [],
            self::VERSION
        );

        wp_register_script(
            'cltd-stripe-products',
            plugins_url('assets/js/cltd-stripe-products.js', __FILE__),
            [],
            self::VERSION,
            true
        );
    }

    /**
     * Enqueue the frontend script and pass runtime data.
     */
    public function enqueue_frontend_script() {
        if (!wp_script_is('cltd-stripe-products', 'registered')) {
            $this->register_scripts();
        }

        if (!wp_style_is('cltd-stripe-products-style', 'enqueued')) {
            wp_enqueue_style('cltd-stripe-products-style');
        }

        $needs_localize = !wp_script_is('cltd-stripe-products', 'enqueued');
        wp_enqueue_script('cltd-stripe-products');

        if ($needs_localize) {
            wp_localize_script(
                'cltd-stripe-products',
                'CLTDStripe',
                [
                    'restUrl' => esc_url_raw(rest_url('cltd/v1/create-checkout')),
                    'nonce'   => wp_create_nonce('wp_rest'),
                    'strings' => [
                        'processing' => __('Processing…', 'cltd-stripe-products'),
                        'error'      => __('Something went wrong. Please try again.', 'cltd-stripe-products'),
                    ],
                ]
            );
        }

        $this->assets_enqueued = true;
    }

    /**
     * Ensure base pages load the assets even when content is injected later.
     */
    public function auto_enqueue_assets() {
        if (!apply_filters('cltd_stripe_products_auto_enqueue_assets', true)) {
            return;
        }

        if ($this->assets_enqueued) {
            return;
        }

        $this->enqueue_frontend_script();
    }

    /**
     * Build a success URL for Stripe Checkout.
     *
     * @return string
     */
    public function get_success_url() {
        return esc_url_raw(add_query_arg('cltd_checkout', 'success', home_url('/')));
    }

    /**
     * Build a cancel URL for Stripe Checkout.
     *
     * @return string
     */
    public function get_cancel_url() {
        $cancel_base = apply_filters('cltd_stripe_products_cancel_url', home_url('/order-cancelled/'));
        return esc_url_raw($cancel_base);
    }

    /**
     * Persist the most recent API error for administrator visibility.
     *
     * @param string $message Error message, leave empty to clear.
     */
    public function record_error($message) {
        if (empty($message)) {
            delete_transient('cltd_stripe_products_last_error');
            return;
        }

        set_transient(
            'cltd_stripe_products_last_error',
            wp_strip_all_tags($message),
            MINUTE_IN_SECONDS * 5
        );
    }

    /**
     * Display an admin notice whenever a Stripe error was captured recently.
     */
    public function maybe_render_error_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $message = get_transient('cltd_stripe_products_last_error');
        if (!$message) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(__('CLTD Stripe Products: %s', 'cltd-stripe-products'), $message))
        );
    }
}

/**
 * Handle admin settings for Stripe keys.
 */
class CLTD_Stripe_Admin_Settings {
    private $plugin;

    public function __construct(CLTD_Stripe_Products_Plugin $plugin) {
        $this->plugin = $plugin;
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_options_page(
            __('CLTD Stripe Products', 'cltd-stripe-products'),
            __('CLTD Stripe', 'cltd-stripe-products'),
            'manage_options',
            'cltd-stripe-products',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'cltd_stripe_products',
            CLTD_Stripe_Products_Plugin::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
            ]
        );

        add_settings_section(
            'cltd_stripe_products_keys',
            __('Stripe API Keys', 'cltd-stripe-products'),
            '__return_false',
            'cltd-stripe-products'
        );

        add_settings_field(
            'cltd_stripe_products_publishable_key',
            __('Publishable Key', 'cltd-stripe-products'),
            [$this, 'render_text_field'],
            'cltd-stripe-products',
            'cltd_stripe_products_keys',
            [
                'label_for' => 'cltd_stripe_products_publishable_key',
                'option'    => 'publishable_key',
            ]
        );

        add_settings_field(
            'cltd_stripe_products_secret_key',
            __('Secret Key', 'cltd-stripe-products'),
            [$this, 'render_text_field'],
            'cltd-stripe-products',
            'cltd_stripe_products_keys',
            [
                'label_for' => 'cltd_stripe_products_secret_key',
                'option'    => 'secret_key',
            ]
        );
    }

    public function sanitize_settings($input) {
        $output = [
            'publishable_key' => '',
            'secret_key'      => '',
        ];

        if (is_array($input)) {
            if (!empty($input['publishable_key'])) {
                $output['publishable_key'] = sanitize_text_field($input['publishable_key']);
            }

            if (!empty($input['secret_key'])) {
                $output['secret_key'] = sanitize_text_field($input['secret_key']);
            }
        }

        CLTD_Stripe_API::flush_cached_prices();
        $this->plugin->record_error('');

        return $output;
    }

    public function render_text_field($args) {
        $option_name = isset($args['option']) ? $args['option'] : '';
        $settings = $this->plugin->get_settings();
        $value = isset($settings[$option_name]) ? esc_attr($settings[$option_name]) : '';
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr(CLTD_Stripe_Products_Plugin::OPTION_KEY . '[' . $option_name . ']'); ?>" value="<?php echo $value; ?>" class="regular-text">
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CLTD Stripe Products', 'cltd-stripe-products'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cltd_stripe_products');
                do_settings_sections('cltd-stripe-products');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

/**
 * Simple Stripe API wrapper built on wp_remote_request.
 */
class CLTD_Stripe_API {
    private $plugin;
    private $api_base = 'https://api.stripe.com/v1/';
    private $cache_ttl = 600;

    public function __construct(CLTD_Stripe_Products_Plugin $plugin) {
        $this->plugin = $plugin;
        $this->cache_ttl = (int) apply_filters('cltd_stripe_products_cache_ttl', 60);
    }

    /**
     * Retrieve prices/products filtered by metadata category.
     *
     * @param string $category Category slug.
     * @return array|WP_Error
     */
    public function get_products_by_category($category) {
        $prices = $this->get_prices();
        if (is_wp_error($prices)) {
            return $prices;
        }

        $filtered = [];

        foreach ($prices as $price) {
            if (empty($price['product'])) {
                continue;
            }

            $product = $price['product'];

            $meta_sources = [
                isset($product['metadata']) ? (array) $product['metadata'] : [],
                isset($price['metadata']) ? (array) $price['metadata'] : [],
            ];

            if (!$this->metadata_matches_category($meta_sources, $category)) {
                continue;
            }

            $metadata = $this->merge_metadata($meta_sources);

            $filtered[] = [
                'id'          => $product['id'],
                'name'        => $product['name'],
                'description' => $product['description'],
                'image'       => !empty($product['images'][0]) ? $product['images'][0] : '',
                'price_id'    => $price['id'],
                'unit_amount' => isset($price['unit_amount']) ? (int) $price['unit_amount'] : 0,
                'currency'    => isset($price['currency']) ? strtoupper($price['currency']) : '',
                'interval'    => isset($price['recurring']['interval']) ? $price['recurring']['interval'] : '',
                'interval_count' => isset($price['recurring']['interval_count']) ? (int) $price['recurring']['interval_count'] : 1,
                'order'       => $this->extract_order_value($product, $price),
                'demo_url'    => isset($metadata['demo_url']) ? (string) $metadata['demo_url'] : '',
                'preview_url' => isset($metadata['preview_url']) ? (string) $metadata['preview_url'] : '',
                'website_url' => isset($metadata['website_url']) ? (string) $metadata['website_url'] : '',
                'github_url'  => isset($metadata['github_url']) ? (string) $metadata['github_url'] : '',
                'features'    => $this->parse_metadata_list($metadata['features'] ?? ''),
                'license'     => $this->parse_metadata_list($metadata['license'] ?? '', false),
            ];
        }

        usort(
            $filtered,
            static function($a, $b) {
                $order_a = isset($a['order']) ? (float) $a['order'] : 0;
                $order_b = isset($b['order']) ? (float) $b['order'] : 0;
                if ($order_a === $order_b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                }
                return $order_b <=> $order_a;
            }
        );

        return $filtered;
    }

    /**
     * Create a Stripe Checkout session.
     *
     * @param string $price_id Price identifier.
     * @param int    $quantity Quantity requested.
     * @return array|WP_Error
     */
    public function create_checkout_session($price_id, $quantity = 1) {
        $secret = $this->plugin->get_secret_key();
        if (!$secret) {
            return new WP_Error('cltd_missing_keys', __('Stripe secret key is missing.', 'cltd-stripe-products'), ['status' => 400]);
        }

        $price_details = $this->get_price_by_id($price_id);
        if (is_wp_error($price_details)) {
            return $price_details;
        }

        $mode = (isset($price_details['type']) && $price_details['type'] === 'recurring') ? 'subscription' : 'payment';

        $body = [
            'mode' => $mode,
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => max(1, (int) $quantity),
            'success_url' => $this->plugin->get_success_url(),
            'cancel_url'  => $this->plugin->get_cancel_url(),
            'billing_address_collection' => 'required',
            'phone_number_collection[enabled]' => 'true',
            'shipping_address_collection[allowed_countries][]' => 'CA',
            'shipping_address_collection[allowed_countries][]' => 'US',
        ];

        $response = wp_remote_post(
            $this->api_base . 'checkout/sessions',
            [
                'headers' => $this->build_auth_headers($secret),
                'body'    => $body,
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400 || empty($data['url'])) {
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('Unable to create checkout session.', 'cltd-stripe-products');
            return new WP_Error('cltd_checkout_error', $message, ['status' => $code]);
        }

        return $data;
    }

    /**
     * Get and cache Stripe prices with embedded products.
     *
     * @return array|WP_Error
     */
    private function get_prices() {
        $secret = $this->plugin->get_secret_key();
        if (!$secret) {
            return new WP_Error('cltd_missing_keys', __('Stripe secret key is missing.', 'cltd-stripe-products'));
        }

        $cache_key = 'cltd_stripe_prices_' . md5($secret);
        if ($this->cache_ttl > 0) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $query_args = [
            'limit' => 100,
            'expand[]' => 'data.product',
        ];

        $url = add_query_arg($query_args, $this->api_base . 'prices');

        $response = wp_remote_get(
            $url,
            [
                'headers' => $this->build_auth_headers($secret),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400 || empty($body['data'])) {
            $message = isset($body['error']['message']) ? $body['error']['message'] : __('Unable to load Stripe products.', 'cltd-stripe-products');
            return new WP_Error('cltd_api_error', $message, ['status' => $code]);
        }

        $prices = $body['data'];
        if ($this->cache_ttl > 0) {
            set_transient($cache_key, $prices, $this->cache_ttl);
        }

        return $prices;
    }

    /**
     * Retrieve a single price object from Stripe.
     *
     * @param string $price_id
     * @return array|WP_Error
     */
    private function get_price_by_id($price_id) {
        $prices = $this->get_prices();
        if (is_wp_error($prices)) {
            return $prices;
        }

        foreach ($prices as $price) {
            if (!empty($price['id']) && $price['id'] === $price_id) {
                return $price;
            }
        }

        return new WP_Error('cltd_price_not_found', __('Unable to locate the requested price in Stripe.', 'cltd-stripe-products'), ['status' => 404]);
    }

    /**
     * Attempt to read ordering metadata from product or price.
     *
     * @param array $product Stripe product.
     * @param array $price Stripe price.
     * @return float
     */
    private function extract_order_value($product, $price) {
        $candidates = [
            $product['metadata']['order'] ?? null,
            $product['metadata']['ordering'] ?? null,
            $product['metadata']['sort'] ?? null,
            $price['metadata']['order'] ?? null,
            $price['metadata']['ordering'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return 0;
    }

    /**
     * Determine if any metadata values match the requested category.
     *
     * @param array  $meta_sources Arrays of metadata to inspect.
     * @param string $category     Category slug.
     * @return bool
     */
    private function metadata_matches_category(array $meta_sources, $category) {
        $targets = $this->normalize_category_tokens($category);
        if (empty($targets)) {
            return false;
        }

        $keys = ['category', 'categories', 'cltd_category'];
        $tokens = [];

        foreach ($meta_sources as $meta) {
            if (empty($meta) || !is_array($meta)) {
                continue;
            }

            foreach ($meta as $key => $value) {
                $normalized_key = strtolower((string) $key);
                if (!in_array($normalized_key, $keys, true)) {
                    continue;
                }

                $tokens = array_merge($tokens, $this->normalize_category_tokens($value));
            }
        }

        if (empty($tokens)) {
            return false;
        }

        $tokens = array_unique($tokens);

        foreach ($tokens as $token) {
            foreach ($targets as $target) {
                if (
                    $token === $target ||
                    ('' !== $target && false !== strpos($token, $target)) ||
                    ('' !== $token && false !== strpos($target, $token))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Merge metadata arrays, letting later sources override earlier keys.
     *
     * @param array $meta_sources Array of metadata arrays.
     * @return array
     */
    private function merge_metadata(array $meta_sources) {
        $merged = [];

        foreach ($meta_sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Convert a metadata value into a clean list split on common separators.
     *
     * @param mixed $value Metadata value.
     * @param bool  $break_on_comma Whether commas should create a new item.
     * @return array
     */
    private function parse_metadata_list($value, $break_on_comma = true) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $value = (string) $value;
            // Allow single-line dash-delimited (" - ") plus common separators.
            $pattern = $break_on_comma
                ? '/(\r\n|\r|\n|,|\\||;|\\s-\\s)/'
                : '/(\r\n|\r|\n|\\||;|\\s-\\s)/';
            $items = preg_split($pattern, $value);
        }

        $clean = [];
        foreach ((array) $items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    /**
     * Normalize metadata values into comparable slug tokens.
     *
     * @param mixed $value Raw metadata value or shortcode target.
     * @return array
     */
    private function normalize_category_tokens($value) {
        if (is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $lower = strtolower($value);
        $normalized_input = preg_replace('/[,|;]/', ' ', $lower);
        $normalized_input = str_replace(['_', '-'], ' ', $normalized_input);

        $parts = preg_split('/\s+/', $normalized_input);
        if (!$parts || empty($parts)) {
            $parts = [$normalized_input];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $part = sanitize_title(trim($part));
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        $tokens[] = sanitize_title($value);

        return array_unique(array_filter($tokens));
    }

    private function build_auth_headers($secret_key) {
        return [
            'Authorization' => 'Bearer ' . $secret_key,
        ];
    }

    public static function flush_cached_prices() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cltd_stripe_prices_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cltd_stripe_prices_%'");
    }
}

/**
 * Shortcode registration and rendering.
 */
class CLTD_Stripe_Shortcodes {
    private $plugin;
    private $category_map = [
        'cltd_webflow_products'     => 'webflow',
        'cltd_maintenance_products' => 'maintenance',
        'cltd_support_products'     => 'support',
    ];

    public function __construct(CLTD_Stripe_Products_Plugin $plugin) {
        $this->plugin = $plugin;
        add_action('init', [$this, 'register_shortcodes']);
    }

    public function register_shortcodes() {
        foreach ($this->category_map as $shortcode => $category) {
            add_shortcode($shortcode, function($atts, $content = null) use ($category) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInClosure
                return $this->render_products($category);
            });
        }
    }

    private function render_products($category) {
        $secret = $this->plugin->get_secret_key();
        if (!$secret) {
            if (current_user_can('manage_options')) {
                return '<p>' . esc_html__('Stripe secret key is missing. Please configure it in Settings → CLTD Stripe.', 'cltd-stripe-products') . '</p>';
            }
            return '';
        }

        $products = $this->plugin->get_api()->get_products_by_category($category);

        if (is_wp_error($products)) {
            $this->plugin->record_error($products->get_error_message());
            $message = __('We could not load products right now. Please try again later.', 'cltd-stripe-products');
            if (current_user_can('manage_options')) {
                $message .= ' ' . $products->get_error_message();
            }
            return '<p class="cltd-stripe-products__notice">' . esc_html($message) . '</p>';
        }

        $this->plugin->record_error('');

        if (empty($products)) {
            return '<p>' . esc_html__('No products are available right now.', 'cltd-stripe-products') . '</p>';
        }

        $this->plugin->enqueue_frontend_script();

        ob_start();
        ?>
        <div class="cltd-stripe-products cltd-stripe-products--<?php echo esc_attr($category); ?>">
            <?php foreach ($products as $product) : ?>
                <article class="cltd-product-card">
                    <?php if (!empty($product['image'])) : ?>
                        <div class="cltd-product-card__media">
                            <img src="<?php echo esc_url($product['image']); ?>" alt="<?php echo esc_attr($product['name']); ?>" loading="lazy" decoding="async">
                        </div>
                    <?php endif; ?>
                    <div class="cltd-product-card__body">
                        <h3 class="cltd-product-card__title"><?php echo esc_html($product['name']); ?></h3>
                        <?php if (!empty($product['description'])) : ?>
                            <p class="cltd-product-card__description"><?php echo esc_html($product['description']); ?></p>
                        <?php endif; ?>
                        <?php
                        $has_links = !empty($product['demo_url']) || !empty($product['preview_url']) || !empty($product['website_url']) || !empty($product['github_url']);
                        ?>
                        <?php if ($has_links) : ?>
                            <details class="cltd-accordion">
                                <summary><?php esc_html_e('Links', 'cltd-stripe-products'); ?></summary>
                                <div class="cltd-accordion__content">
                                    <div class="cltd-product-card__links">
                                        <?php if (!empty($product['demo_url'])) : ?>
                                            <a class="cltd-chip" href="<?php echo esc_url($product['demo_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('View Live Demo', 'cltd-stripe-products'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($product['preview_url'])) : ?>
                                            <a class="cltd-chip cltd-chip--dark" href="<?php echo esc_url($product['preview_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('Preview in Webflow', 'cltd-stripe-products'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($product['website_url'])) : ?>
                                            <a class="cltd-chip cltd-chip--accent" href="<?php echo esc_url($product['website_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('Visit Website', 'cltd-stripe-products'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($product['github_url'])) : ?>
                                            <a class="cltd-chip cltd-chip--outline" href="<?php echo esc_url($product['github_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php esc_html_e('View on GitHub', 'cltd-stripe-products'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($product['features'])) : ?>
                            <details class="cltd-accordion">
                                <summary><?php esc_html_e('Features', 'cltd-stripe-products'); ?></summary>
                                <div class="cltd-accordion__content">
                                    <ul class="cltd-feature-list">
                                        <?php foreach ($product['features'] as $feature) : ?>
                                            <li><?php echo esc_html($feature); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($product['license'])) : ?>
                            <details class="cltd-accordion">
                                <summary><?php esc_html_e('License', 'cltd-stripe-products'); ?></summary>
                                <div class="cltd-accordion__content">
                                    <?php foreach ($product['license'] as $license_line) : ?>
                                        <p class="cltd-license-line"><?php echo esc_html($license_line); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php
                            $product_slug = isset($product['name']) ? sanitize_title($product['name']) : '';
                            $product_label = isset($product['name']) ? $product['name'] : '';
                        ?>
                        <p class="cltd-product-card__price">
                            <?php echo esc_html($this->format_price($product['unit_amount'], $product['currency'], $product['interval'], $product['interval_count'] ?? 1)); ?>
                        </p>
                        <button
                            type="button"
                            class="cltd-button cltd-buy-now <?php echo esc_attr('cltd-buy-now--' . sanitize_html_class($category)); ?><?php echo $product_slug ? ' ' . esc_attr('cltd-buy-now--product-' . $product_slug) : ''; ?>"
                            data-price-id="<?php echo esc_attr($product['price_id']); ?>"
                            data-cltd-product-category="<?php echo esc_attr($category); ?>"
                            <?php if ($product_label !== '') : ?>
                                data-cltd-product-title="<?php echo esc_attr($product_label); ?>"
                            <?php endif; ?>
                            aria-label="<?php echo esc_attr(sprintf(__('Buy %s now', 'cltd-stripe-products'), $product['name'])); ?>"
                        >
                            <?php esc_html_e('Buy Now', 'cltd-stripe-products'); ?>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    private function format_price($amount, $currency, $interval = '', $interval_count = 1) {
        if (!$amount) {
            return __('Free', 'cltd-stripe-products');
        }

        $formatted = number_format($amount / 100, 2);
        $text = sprintf('%s %s', strtoupper($currency), $formatted);

        if ($interval) {
            $interval_label = $this->get_interval_label($interval, $interval_count);
            if ($interval_count > 1) {
                $text .= sprintf(__(' every %1$d %2$s', 'cltd-stripe-products'), $interval_count, $interval_label);
            } else {
                $text .= sprintf(__(' / %s', 'cltd-stripe-products'), $interval_label);
            }
        }

        return $text;
    }

    private function get_interval_label($interval, $count) {
        $interval = strtolower((string) $interval);

        switch ($interval) {
            case 'day':
                return _n('day', 'days', $count, 'cltd-stripe-products');
            case 'week':
                return _n('week', 'weeks', $count, 'cltd-stripe-products');
            case 'month':
                return _n('month', 'months', $count, 'cltd-stripe-products');
            case 'year':
                return _n('year', 'years', $count, 'cltd-stripe-products');
            default:
                return $count === 1 ? $interval : $interval . 's';
        }
    }
}

/**
 * Handle REST endpoint for creating checkout sessions.
 */
class CLTD_Stripe_Checkout {
    private $plugin;

    public function __construct(CLTD_Stripe_Products_Plugin $plugin) {
        $this->plugin = $plugin;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route(
            'cltd/v1',
            '/create-checkout',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handle_create_checkout'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'price_id' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'quantity' => [
                        'type'     => 'integer',
                        'required' => false,
                        'default'  => 1,
                    ],
                ],
            ]
        );
    }

    public function handle_create_checkout(WP_REST_Request $request) {
        $price_id = sanitize_text_field($request->get_param('price_id'));
        $quantity = (int) $request->get_param('quantity');

        if (!$price_id) {
            return new WP_Error('cltd_missing_price', __('A price ID is required.', 'cltd-stripe-products'), ['status' => 400]);
        }

        $session = $this->plugin->get_api()->create_checkout_session($price_id, $quantity);

        if (is_wp_error($session)) {
            return $session;
        }

        return rest_ensure_response([
            'url' => esc_url_raw($session['url']),
        ]);
    }
}

CLTD_Stripe_Products_Plugin::instance();
