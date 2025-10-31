<?php

if (!defined('ABSPATH')) {
    exit;
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
 * Register custom button style for Gutenberg button block.
 */
function cltd_register_brand_button_style() {
    if (!function_exists('register_block_style')) {
        return;
    }

    $style_path = get_template_directory() . '/css/cltd-brand.css';
    $style_uri = get_template_directory_uri() . '/css/cltd-brand.css';

    if (file_exists($style_path)) {
        wp_register_style(
            'cltd-brand-button',
            $style_uri,
            [],
            filemtime($style_path)
        );
    }

    register_block_style(
        'core/button',
        [
            'name'         => 'cltd-brand',
            'label'        => __('CLTD Brand', 'cltd-theme-oct-2025'),
            'style_handle' => 'cltd-brand-button',
        ]
    );
}
add_action('init', 'cltd_register_brand_button_style');

/**
 * Enqueue brand button styles on the frontend only.
 */
function cltd_enqueue_brand_button_style() {
    if (is_admin()) {
        return;
    }

    if (wp_style_is('cltd-brand-button', 'registered')) {
        wp_enqueue_style('cltd-brand-button');
    }
}
add_action('wp_enqueue_scripts', 'cltd_enqueue_brand_button_style');

/**
 * Load brand button styles inside the block editor.
 */
function cltd_enqueue_brand_button_editor_style() {
    if (wp_style_is('cltd-brand-button', 'registered')) {
        wp_enqueue_style('cltd-brand-button');
    }
}
add_action('enqueue_block_editor_assets', 'cltd_enqueue_brand_button_editor_style');

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
        $state = isset($item['state']) ? $item['state'] : '';
        $is_disabled = !empty($item['disabled']);

        $circle_classes = ['circle'];
        if ($state) {
            $circle_classes[] = 'circle--' . sanitize_html_class($state);
        }
        if ($is_disabled) {
            $circle_classes[] = 'circle--inactive';
        }
        ?>
        <li class="circle-item">
                    <?php if ('popup' === $type && !$is_disabled && $slug) : ?>
                        <button type="button" class="<?php echo esc_attr(implode(' ', $circle_classes)); ?>" data-popup-slug="<?php echo esc_attr($slug); ?>" <?php if ($popup_id) : ?>data-popup-id="<?php echo esc_attr($popup_id); ?>"<?php endif; ?> data-popup-title="<?php echo esc_attr($label); ?>" aria-label="<?php echo esc_attr(sprintf(__('Open "%s" details', 'cltd-theme-oct-2025'), $label ?: $slug)); ?>">
                            <span class="circle__core" aria-hidden="true"></span>
                            <?php if ($icon) : ?>
                                <span class="circle__icon" aria-hidden="true">
                                    <img src="<?php echo esc_url($icon); ?>" alt="" />
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
                <?php esc_html_e('Select SVG Icon', 'cltd-theme-oct-2025'); ?>
            </button>
            <button type="button" class="button button-link-delete" data-popup-icon-clear>
                <?php esc_html_e('Remove', 'cltd-theme-oct-2025'); ?>
            </button>
        </p>
        <p class="description"><?php esc_html_e('Upload or paste the URL to a 1-color SVG. It will appear centered inside the popup button.', 'cltd-theme-oct-2025'); ?></p>
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
        update_post_meta($post_id, 'cltd_popup_icon', esc_url_raw($icon));
    } else {
        delete_post_meta($post_id, 'cltd_popup_icon');
    }
}
add_action('save_post_cltd_popup', 'cltd_theme_save_popup_icon_meta');

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
            'chooseIcon' => __('Choose SVG Icon', 'cltd-theme-oct-2025'),
            'useIcon'    => __('Use this icon', 'cltd-theme-oct-2025'),
            'errorSvg'   => __('Please select an SVG file for the icon.', 'cltd-theme-oct-2025'),
        ]
    );
}
add_action('admin_enqueue_scripts', 'cltd_theme_popup_admin_assets');

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
                ['label' => __('Terms of Service', 'cltd-theme-oct-2025'), 'url' => '/privacy-policy'],
                ['label' => __('Privacy & Cookies', 'cltd-theme-oct-2025'), 'url' => '#'],
                ['label' => __('Return Policy', 'cltd-theme-oct-2025'), 'url' => '#'],
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
        [],
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

    wp_localize_script(
        'cltd-main',
        'CLTDTheme',
        [
            'restUrl' => esc_url_raw(rest_url('cltd/v1/popup/')),
            'heroBackground' => cltd_theme_format_hero_background_for_js($hero_background),
            'strings' => [
                'loading' => __('Loading popup…', 'cltd-theme-oct-2025'),
                'error'   => __('We could not load that content right now. Please try again.', 'cltd-theme-oct-2025'),
                'close'   => __('Close popup', 'cltd-theme-oct-2025'),
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
                                    $permalink = get_permalink($post);
                                    $title = get_the_title($post);
                                    $content_html = apply_filters('the_content', get_the_content(null, false, $post));
                                    ?>
                                    <li class="popup-posts__item">
                                        <a class="popup-posts__link" href="<?php echo esc_url($permalink); ?>">
                                            <?php echo esc_html($title); ?>
                                        </a>
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

    $hero_background = cltd_theme_get_hero_background();
    $hero_has_slider = cltd_theme_has_hero_background($hero_background);
    $hero_background_markup = $hero_has_slider ? cltd_theme_get_hero_background_markup($hero_background) : '';

    if (!$hero_has_slider && empty($hero_background_markup) && !empty($hero['background_image'])) {
        $hero_background_markup = sprintf(
            '<div class="hero-background hero-background--image"><div class="hero-background__image" style="background-image: url(%s);"></div></div>',
            esc_url($hero['background_image'])
        );
    }

    ob_start();
    if (!empty($hero_background_markup)) {
        echo $hero_background_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
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

    <div class="cltd-modal" data-popup-modal hidden>
        <div class="cltd-modal__backdrop" data-popup-close></div>
        <div class="cltd-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cltd-modal-title" aria-describedby="cltd-modal-content" tabindex="-1">
            <button type="button" class="cltd-modal__close" data-popup-close aria-label="<?php esc_attr_e('Close popup', 'cltd-theme-oct-2025'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="cltd-modal__body">
                <h3 id="cltd-modal-title" class="cltd-modal__title"></h3>
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

function cltd_theme_legacy_layout_shortcode() {
    return cltd_theme_get_legacy_layout_markup();
}
add_shortcode('cltd_legacy_layout', 'cltd_theme_legacy_layout_shortcode');

// === [ CLTD: WooCommerce Product Shortcodes for Popups — Full Detail Version ] === //
function cltd_theme_products_by_category_shortcode($atts, $content = null, $tag = '') {
    if (!class_exists('WooCommerce')) {
        return '<p>WooCommerce not active.</p>';
    }

    // Map shortcode name to WooCommerce category slug
    $map = [
        'cltd_maintenance_products' => 'maintenance',
        'cltd_webflow_products'     => 'webflow',
        'cltd_support_products'     => 'support',
    ];

    $category = isset($map[$tag]) ? $map[$tag] : '';

    if (!$category) {
        return '<p>Invalid shortcode or category missing.</p>';
    }

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'tax_query'      => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => sanitize_title($category),
            ],
        ],
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p>No products found in ' . esc_html(ucfirst($category)) . '.</p>';
    }

    ob_start();
    echo '<div class="cltd-products-grid">';
    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());
        if (!$product) continue;

        // Retrieve synced metadata (from Stripe or manual fields)
        $demo_url    = get_post_meta(get_the_ID(), 'demo_url', true);
        $website_url = get_post_meta(get_the_ID(), 'website_url', true);
        $features    = get_post_meta(get_the_ID(), 'features', true);
        $license     = get_post_meta(get_the_ID(), 'license', true);
        ?>
        <div class="cltd-product-card-full">
            <div class="cltd-product-media">
                <a href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>
                </a>
            </div>

            <div class="cltd-product-info">
                <h3 class="cltd-product-title"><?php the_title(); ?></h3>
                <p class="cltd-product-price"><?php echo wp_kses_post($product->get_price_html()); ?></p>

                <div class="cltd-product-desc">
                    <?php echo wpautop($product->get_description()); ?>
                </div>

                <?php if ($demo_url || $website_url): ?>
                    <div class="cltd-product-links">
                        <?php if ($demo_url): ?>
                            <a href="<?php echo esc_url($demo_url); ?>" class="button black" target="_blank">
                                <?php esc_html_e('View Live Demo', 'cltd-theme-oct-2025'); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($website_url): ?>
                            <a href="<?php echo esc_url($website_url); ?>" class="button yellow" target="_blank">
                                <?php esc_html_e('Visit Website', 'cltd-theme-oct-2025'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($features): ?>
                    <details class="cltd-features">
                        <summary><?php esc_html_e('Features', 'cltd-theme-oct-2025'); ?></summary>
                        <div class="inner"><?php echo wpautop($features); ?></div>
                    </details>
                <?php endif; ?>

                <?php if ($license): ?>
                    <details class="cltd-license">
                        <summary><?php esc_html_e('License', 'cltd-theme-oct-2025'); ?></summary>
                        <div class="inner"><?php echo wpautop($license); ?></div>
                    </details>
                <?php endif; ?>

                <form action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post" class="cltd-add-to-cart-form">
                    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">
                    <button type="submit" class="button alt">
                        <?php esc_html_e('Add to Cart', 'cltd-theme-oct-2025'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}

add_shortcode('cltd_maintenance_products', 'cltd_theme_products_by_category_shortcode');
add_shortcode('cltd_webflow_products', 'cltd_theme_products_by_category_shortcode');
add_shortcode('cltd_support_products', 'cltd_theme_products_by_category_shortcode');


/**
 * Ensure WooCommerce cart/session exists for front-end requests that rely on add-to-cart links.
 */
function cltd_theme_bootstrap_woocommerce_cart() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
        return;
    }

    $woocommerce = WC();

    if (!isset($woocommerce->cart) || !is_a($woocommerce->cart, 'WC_Cart')) {
        $woocommerce->initialize_session();
        $woocommerce->initialize_cart();
    }
}
add_action('woocommerce_init', 'cltd_theme_bootstrap_woocommerce_cart', 20);
