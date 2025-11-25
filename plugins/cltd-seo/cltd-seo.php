<?php
/**
 * Plugin Name:       CLTD SEO Toolkit
 * Description:       Lightweight SEO helper that adds custom title/meta-description fields plus an optional AI-powered generator.
 * Version:           1.0.0
 * Author:            Crystal The Developer
 * Author URI:        https://www.crystalthedeveloper.ca
 * Text Domain:       cltd-seo
 *
 * @package CLTD_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CLTD_SEO_Plugin')) {
    class CLTD_SEO_Plugin {
        private const META_TITLE = '_cltd_seo_meta_title';
        private const META_DESCRIPTION = '_cltd_seo_meta_description';
        private const OPTION_API_KEY = 'cltd_seo_openai_api_key';
        private const OPTION_ENABLE_OG_FALLBACK = 'cltd_seo_enable_og_fallback';
        private const OPTION_OG_FALLBACK_IMAGE = 'cltd_seo_fallback_og_image';
        private const META_OG_TITLE = '_cltd_seo_og_title';
        private const META_OG_DESCRIPTION = '_cltd_seo_og_description';
        private const META_OG_IMAGE = '_cltd_seo_og_image';

        public function __construct() {
            add_action('init', [$this, 'register_meta_fields']);
            add_action('wp_head', [$this, 'output_meta_tags'], 1);
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
            add_action('admin_menu', [$this, 'register_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_cltd_seo_generate_description', [$this, 'handle_generate_description']);
            add_filter('pre_get_document_title', [$this, 'filter_document_title']);
            add_action('init', [self::class, 'register_rewrite_rules']);
            add_filter('query_vars', [self::class, 'register_query_vars']);
            add_action('template_redirect', [$this, 'maybe_render_sitemap']);
            add_filter('robots_txt', [$this, 'filter_robots_txt'], 10, 2);
        }

        public function register_meta_fields() {
            $post_types = apply_filters('cltd_seo_supported_post_types', ['post', 'page']);
            foreach ($post_types as $type) {
                register_post_meta(
                    $type,
                    self::META_TITLE,
                    [
                        'single'            => true,
                        'type'              => 'string',
                        'show_in_rest'      => true,
                        'sanitize_callback' => [$this, 'sanitize_meta_text'],
                        'auth_callback'     => [$this, 'meta_auth_callback'],
                    ]
                );

                register_post_meta($type, self::META_DESCRIPTION, [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => [$this, 'sanitize_meta_textarea'],
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, self::META_OG_TITLE, [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => [$this, 'sanitize_meta_text'],
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, self::META_OG_DESCRIPTION, [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => [$this, 'sanitize_meta_textarea'],
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, self::META_OG_IMAGE, [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, '_cltd_seo_og_title_same', [
                    'single'            => true,
                    'type'              => 'boolean',
                    'show_in_rest'      => true,
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, '_cltd_seo_og_description_same', [
                    'single'            => true,
                    'type'              => 'boolean',
                    'show_in_rest'      => true,
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);

                register_post_meta($type, '_cltd_seo_og_image_same', [
                    'single'            => true,
                    'type'              => 'boolean',
                    'show_in_rest'      => true,
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'auth_callback'     => [$this, 'meta_auth_callback'],
                ]);
            }
        }

        public function meta_auth_callback($allowed, $meta_key, $post_id) {
            return current_user_can('edit_post', $post_id);
        }

        public function sanitize_meta_text($value) {
            $value = sanitize_text_field($value);
            return wp_trim_words($value, 24, '');
        }

        public function sanitize_meta_textarea($value) {
            $value = sanitize_textarea_field($value);
            return wp_trim_words($value, 60, '...');
        }

        public function output_meta_tags() {
            $meta = $this->build_meta_payload();
            if (empty($meta)) {
                return;
            }

            printf('<meta name="description" content="%s" />' . "\n", esc_attr($meta['description']));
            printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($meta['og_title']));
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($meta['og_description']));
            printf('<meta property="og:url" content="%s" />' . "\n", esc_url($meta['url']));
            printf('<meta property="og:type" content="%s" />' . "\n", esc_attr($meta['type']));
            printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr(get_bloginfo('name')));

            if (!empty($meta['og_image'])) {
                printf('<meta property="og:image" content="%s" />' . "\n", esc_url($meta['og_image']));
            }

            printf('<meta name="twitter:card" content="%s" />' . "\n", esc_attr($meta['twitter_card']));
            printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($meta['og_title']));
            printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($meta['og_description']));

            if (!empty($meta['og_image'])) {
                printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($meta['og_image']));
            }
        }

        private function get_meta_title($post_id) {
            $title = get_post_meta($post_id, self::META_TITLE, true);
            if (!$title) {
                $title = get_the_title($post_id);
            }
            return $title;
        }

        private function get_meta_description($post_id) {
            $description = get_post_meta($post_id, self::META_DESCRIPTION, true);
            if (!$description) {
                $post = get_post($post_id);
                $description = $post ? wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...') : '';
            }
            return $description;
        }

        private function get_synced_meta($post_id, $meta_key, $flag_key, $fallback) {
            $use_same = (bool) get_post_meta($post_id, $flag_key, true);
            if ($use_same) {
                return $fallback;
            }

            $value = get_post_meta($post_id, $meta_key, true);
            return $value ? $value : $fallback;
        }

        private function build_meta_payload() {
            $post_id = is_singular() ? get_queried_object_id() : 0;

            $title = '';
            $description = '';
            $og_title = '';
            $og_description = '';
            $og_image = '';
            $url = $post_id ? get_permalink($post_id) : $this->get_current_url();
            $type = $post_id ? 'article' : 'website';

            if ($post_id) {
                $title       = $this->get_meta_title($post_id);
                $description = $this->get_meta_description($post_id);
                $og_title    = $this->get_synced_meta($post_id, self::META_OG_TITLE, '_cltd_seo_og_title_same', $title);
                $og_description = $this->get_synced_meta($post_id, self::META_OG_DESCRIPTION, '_cltd_seo_og_description_same', $description);
                $og_image    = $this->get_synced_meta($post_id, self::META_OG_IMAGE, '_cltd_seo_og_image_same', '');
            } else {
                $title          = get_bloginfo('name');
                $description    = $this->get_site_description();
                $og_title       = $title;
                $og_description = $description;
                $og_image       = $this->is_og_fallback_enabled() ? $this->get_fallback_og_image() : '';
            }

            if (!$title) {
                $title = get_bloginfo('name');
            }

            if (!$description) {
                $description = $this->get_site_description();
            }

            if (!$og_title) {
                $og_title = $title;
            }

            if (!$og_description) {
                $og_description = $description;
            }

            $og_image = $this->resolve_og_image($post_id, $og_image);

            return [
                'title'          => $title,
                'description'    => $description,
                'og_title'       => $og_title,
                'og_description' => $og_description,
                'og_image'       => $og_image,
                'url'            => $url,
                'type'           => $type,
                'twitter_card'   => $og_image ? 'summary_large_image' : 'summary',
            ];
        }

        private function resolve_og_image($post_id, $current) {
            if ($current) {
                return $current;
            }

            if (!$this->is_og_fallback_enabled()) {
                return '';
            }

            if ($post_id) {
                $featured = get_the_post_thumbnail_url($post_id, 'full');
                if ($featured) {
                    return $featured;
                }
            }

            return $this->get_fallback_og_image();
        }

        private function get_fallback_og_image() {
            $fallback = trim((string) get_option(self::OPTION_OG_FALLBACK_IMAGE, ''));
            if ($fallback) {
                return $fallback;
            }

            $site_icon_id = (int) get_option('site_icon', 0);
            if ($site_icon_id) {
                $icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
                if ($icon_url) {
                    return $icon_url;
                }
            }

            return '';
        }

        private function is_og_fallback_enabled() {
            return (bool) get_option(self::OPTION_ENABLE_OG_FALLBACK, true);
        }

        private function get_site_description() {
            $tagline = get_bloginfo('description');
            if ($tagline) {
                return $tagline;
            }

            return __('Crystal The Developer builds delightful, high-performance websites.', 'cltd-seo');
        }

        private function get_current_url() {
            $url = add_query_arg(null, null);
            if ($url) {
                return esc_url_raw($url);
            }

            $path = isset($GLOBALS['wp']->request) ? '/' . ltrim((string) $GLOBALS['wp']->request, '/') : '/';
            return home_url($path);
        }

        public function filter_document_title($title) {
            if (!is_singular()) {
                return $title;
            }

            $post_id = get_queried_object_id();
            if (!$post_id) {
                return $title;
            }

            $meta_title = get_post_meta($post_id, self::META_TITLE, true);
            return $meta_title ? $meta_title : $title;
        }

        public function enqueue_block_editor_assets() {
            wp_enqueue_script(
                'cltd-seo-editor',
                plugin_dir_url(__FILE__) . 'assets/editor.js',
                ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-editor', 'wp-api-fetch', 'wp-media-utils', 'wp-block-editor'],
                filemtime(plugin_dir_path(__FILE__) . 'assets/editor.js'),
                true
            );

            wp_enqueue_style(
                'cltd-seo-editor',
                plugin_dir_url(__FILE__) . 'assets/editor.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . 'assets/editor.css')
            );

            wp_localize_script(
                'cltd-seo-editor',
                'CLTDSEO',
                [
                    'nonce'    => wp_create_nonce('cltd_seo_generate'),
                    'ajaxUrl'  => admin_url('admin-ajax.php'),
                    'messages' => [
                        'generating' => __('Generating…', 'cltd-seo'),
                        'error'      => __('Unable to generate description. Please try again.', 'cltd-seo'),
                    ],
                    'metaKeys' => [
                        'title'           => self::META_TITLE,
                        'description'     => self::META_DESCRIPTION,
                        'ogTitle'         => self::META_OG_TITLE,
                        'ogDescription'   => self::META_OG_DESCRIPTION,
                        'image'           => self::META_OG_IMAGE,
                        'ogTitleSame'     => '_cltd_seo_og_title_same',
                        'ogDescriptionSame' => '_cltd_seo_og_description_same',
                        'ogImageSame'     => '_cltd_seo_og_image_same',
                    ],
                ]
            );
        }

        public function register_settings_page() {
            add_options_page(
                __('CLTD SEO', 'cltd-seo'),
                __('CLTD SEO', 'cltd-seo'),
                'manage_options',
                'cltd-seo',
                [$this, 'render_settings_page']
            );
        }

        public function register_settings() {
            register_setting(
                'cltd_seo_settings',
                self::OPTION_API_KEY,
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            );

            register_setting(
                'cltd_seo_settings',
                self::OPTION_ENABLE_OG_FALLBACK,
                [
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'default'           => true,
                ]
            );

            register_setting(
                'cltd_seo_settings',
                self::OPTION_OG_FALLBACK_IMAGE,
                [
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'default'           => '',
                ]
            );
        }

        public function render_settings_page() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('CLTD SEO Settings', 'cltd-seo'); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('cltd_seo_settings');
                    $api_key = get_option(self::OPTION_API_KEY, '');
                    $fallback_enabled = (bool) get_option(self::OPTION_ENABLE_OG_FALLBACK, true);
                    $fallback_image = get_option(self::OPTION_OG_FALLBACK_IMAGE, '');
                    ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="cltd-seo-api-key"><?php esc_html_e('OpenAI API Key', 'cltd-seo'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="cltd-seo-api-key" name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                                <p class="description">
                                    <?php esc_html_e('Optional. Used to generate descriptions via OpenAI (free tier supported). Leave empty to use the fallback summary generator.', 'cltd-seo'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cltd-seo-og-fallback"><?php esc_html_e('Open Graph Fallback', 'cltd-seo'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="cltd-seo-og-fallback" name="<?php echo esc_attr(self::OPTION_ENABLE_OG_FALLBACK); ?>" value="1" <?php checked($fallback_enabled); ?>>
                                    <?php esc_html_e('Automatically fill OG tags for pages without manual settings.', 'cltd-seo'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, CLTD SEO will use featured images, site icon, or the fallback image below to guarantee social previews.', 'cltd-seo'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="cltd-seo-og-image"><?php esc_html_e('Fallback OG Image URL', 'cltd-seo'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="cltd-seo-og-image" name="<?php echo esc_attr(self::OPTION_OG_FALLBACK_IMAGE); ?>" value="<?php echo esc_attr($fallback_image); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Optional. Used when a post lacks its own social image and no featured image is set. Square or 1200×630px images work best.', 'cltd-seo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }

        public static function register_rewrite_rules() {
            add_rewrite_rule('cltd-sitemap\\.xml$', 'index.php?cltd_seo_sitemap=1', 'top');
        }

        public static function register_query_vars($vars) {
            $vars[] = 'cltd_seo_sitemap';
            return $vars;
        }

        public function maybe_render_sitemap() {
            if (get_query_var('cltd_seo_sitemap')) {
                $this->render_sitemap();
                exit;
            }
        }

        private function render_sitemap() {
            $entries = $this->build_sitemap_entries();
            if (empty($entries)) {
                status_header(404);
                exit;
            }

            status_header(200);
            header('Content-Type: application/xml; charset=utf-8');

            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($entries as $entry) {
                $loc        = isset($entry['loc']) ? esc_url($entry['loc']) : '';
                $lastmod    = isset($entry['lastmod']) ? esc_html($entry['lastmod']) : '';
                $changefreq = isset($entry['changefreq']) ? esc_html($entry['changefreq']) : 'weekly';
                $priority   = isset($entry['priority']) ? esc_html($entry['priority']) : '0.5';

                if (!$loc) {
                    continue;
                }

                echo "  <url>\n";
                echo '    <loc>' . $loc . "</loc>\n";
                if ($lastmod) {
                    echo '    <lastmod>' . $lastmod . "</lastmod>\n";
                }
                echo '    <changefreq>' . $changefreq . "</changefreq>\n";
                echo '    <priority>' . $priority . "</priority>\n";
                echo "  </url>\n";
            }

            echo '</urlset>';
        }

        private function build_sitemap_entries() {
            $entries = [
                [
                    'loc'        => home_url('/'),
                    'lastmod'    => gmdate('c'),
                    'changefreq' => 'daily',
                    'priority'   => '1.0',
                ],
            ];

            $post_types = apply_filters('cltd_seo_sitemap_post_types', ['page', 'post']);
            if (!empty($post_types)) {
                $posts = get_posts([
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                    'no_found_rows'  => true,
                ]);

                foreach ($posts as $entry_post) {
                    $timestamp = $entry_post->post_modified_gmt ?: $entry_post->post_date_gmt;
                    $lastmod = $timestamp ? gmdate('c', strtotime($timestamp)) : gmdate('c');
                    $is_page = 'page' === $entry_post->post_type;

                    $entries[] = [
                        'loc'        => get_permalink($entry_post),
                        'lastmod'    => $lastmod,
                        'changefreq' => $is_page ? 'monthly' : 'weekly',
                        'priority'   => $is_page ? '0.8' : '0.6',
                    ];
                }
            }

            return apply_filters('cltd_seo_sitemap_entries', $entries);
        }

        public static function activate() {
            self::register_rewrite_rules();
            flush_rewrite_rules();
        }

        public function filter_robots_txt($output, $public) {
            $lines = [];
            $lines[] = 'User-agent: *';

            if ('0' === (string) $public) {
                $lines[] = 'Disallow: /';
                $lines[] = 'Crawl-delay: 10';
            } else {
                $lines[] = 'Allow: /';
                $lines[] = 'Disallow: /wp-admin/';
                $lines[] = 'Allow: /wp-admin/admin-ajax.php';
                $lines[] = 'Sitemap: ' . esc_url(home_url('/cltd-sitemap.xml'));
            }

            $lines = apply_filters('cltd_seo_robots_lines', $lines, $public);

            $lines = array_filter(array_map('trim', $lines));
            return implode("\n", $lines) . "\n";
        }

        public function handle_generate_description() {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(__('Permission denied.', 'cltd-seo'));
            }

            check_ajax_referer('cltd_seo_generate', 'nonce');

            $post_id = isset($_POST['postId']) ? (int) $_POST['postId'] : 0;
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                wp_send_json_error(__('Invalid post.', 'cltd-seo'));
            }

            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(__('Post not found.', 'cltd-seo'));
            }

            $content = wp_strip_all_tags($post->post_content);
            $default_summary = wp_trim_words($content, 30, '...');

            $api_key = trim((string) get_option(self::OPTION_API_KEY, ''));
            if (!$api_key) {
                wp_send_json_success($default_summary);
            }

            $body = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You generate concise SEO descriptions (max 155 characters).',
                    ],
                    [
                        'role' => 'user',
                        'content' => sprintf('Create an SEO meta description for: %s', wp_trim_words($content, 120, '...')),
                    ],
                ],
                'max_tokens' => 120,
                'temperature' => 0.4,
            ];

            $response = wp_remote_post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                    ],
                    'body'    => wp_json_encode($body),
                    'timeout' => 20,
                ]
            );

            if (is_wp_error($response)) {
                wp_send_json_success($default_summary);
            }

            $payload = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($payload['choices'][0]['message']['content'])) {
                wp_send_json_success($default_summary);
            }

            $text = sanitize_text_field($payload['choices'][0]['message']['content']);
            wp_send_json_success(wp_trim_words($text, 40, ''));
        }
    }

    register_activation_hook(__FILE__, ['CLTD_SEO_Plugin', 'activate']);
    new CLTD_SEO_Plugin();
}
