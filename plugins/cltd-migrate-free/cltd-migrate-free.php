<?php
/**
 * Plugin Name:       CLTD Migrate Free
 * Plugin URI:        https://crystalthedeveloper.com/
 * Description:       100% free, unlimited-size WordPress migration plugin by Crystal The Developer Inc. Export/import the entire site (database, themes, plugins, media, and core files) via a single ZipArchive package—no upload caps, subscriptions, or cloud services.
 * Version:           1.0.2
 * Author:            Crystal The Developer Inc.
 * Author URI:        https://crystalthedeveloper.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cltd-migrate-free
 */

defined('ABSPATH') || exit;

if (!class_exists('CLTD_Migrate_Free', false)) {

    final class CLTD_Migrate_Free {
        const VERSION       = '1.0.2';
        const MENU_SLUG            = 'cltd-migrate-free';
        const EXPORT_ACTION        = 'cltd_migrate_free_export';
        const IMPORT_FILES_ACTION  = 'cltd_migrate_free_import_files';
        const IMPORT_DB_ACTION     = 'cltd_migrate_free_import_db';
        const RESTORED_IMPORT_ACTION = 'cltd_migrate_free_restored_import';
        const DOWNLOAD_ACTION      = 'cltd_migrate_free_download';

        /**
         * Singleton instance.
         *
         * @var self|null
         */
        private static $instance = null;

        /**
         * Tracks the operation currently running (export/import) for logging.
         *
         * @var string|null
         */
        private $current_operation = null;

        /**
         * Whether streaming debug output should be sent to the browser.
         *
         * @var bool
         */
        private $debug_enabled = false;

        /**
         * Indicates whether the debug HTML wrapper has already been printed.
         *
         * @var bool
         */
        private $debug_started = false;

        /**
         * Stores the active site's URL before imports replace the database.
         *
         * @var string
         */
        private $current_site_url = '';

        /**
         * Boot the plugin.
         *
         * @return self
         */
        public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Handle database import submissions (Step 2).
         */
        public function handle_import_database() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to import a database.', 'cltd-migrate-free'));
            }

            check_admin_referer(self::IMPORT_DB_ACTION);

            $this->current_operation = 'import_database';
            $this->maybe_begin_debug_stream();

            if (empty($_POST['cltd_migrate_free_confirm'])) {
                $this->log_event('Import DB failed: confirmation checkbox missing');
                $this->respond_with_error(__('Please confirm that you understand the import will overwrite this site.', 'cltd-migrate-free'));
            }

            if (empty($_FILES['cltd_migrate_free_database_package']) || empty($_FILES['cltd_migrate_free_database_package']['tmp_name'])) {
                $this->log_event('Import DB failed: no file uploaded');
                $this->respond_with_error(__('No database file was uploaded.', 'cltd-migrate-free'));
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';

            $overrides = [
                'test_form' => false,
                'mimes'     => [
                    'sql' => 'application/octet-stream',
                    'txt' => 'text/plain',
                ],
            ];

            $uploaded = wp_handle_upload($_FILES['cltd_migrate_free_database_package'], $overrides);
            if (isset($uploaded['error'])) {
                $message = sprintf(__('Upload failed: %s', 'cltd-migrate-free'), $uploaded['error']);
                $this->log_event('Import DB failed during upload', ['error' => $uploaded['error']]);
                $this->respond_with_error($message);
            }

            $sql_path = $uploaded['file'];
            $this->log_event('Database upload received', ['path' => $sql_path]);
            $this->debug_echo(sprintf(__('Database file stored at %s', 'cltd-migrate-free'), $sql_path));

            $result = $this->import_database($sql_path);
            $this->remove_path($sql_path);

            if (is_wp_error($result)) {
                $this->log_event('Database import failed', ['error' => $result->get_error_message()]);
                $this->respond_with_error($result->get_error_message());
            } else {
                $old_url = $this->detect_imported_site_url();
                $new_url = $this->current_site_url;
                if ($old_url && $old_url !== $new_url) {
                    $rewrite = $this->rewrite_urls_after_import($old_url, $new_url);
                    if (is_wp_error($rewrite)) {
                        $this->log_event('URL rewrite failed after database import', ['error' => $rewrite->get_error_message()]);
                        $this->respond_with_error($rewrite->get_error_message());
                    }
                }
                $normalize_blocks = $this->normalize_block_markup_after_import();
                if (is_wp_error($normalize_blocks)) {
                    $this->log_event('Database import block normalization failed', ['error' => $normalize_blocks->get_error_message()]);
                    $this->respond_with_error($normalize_blocks->get_error_message());
                }
                $this->refresh_theme_assets();

                $this->store_notice('success', __('Database import completed. Migration finished.', 'cltd-migrate-free'));
                $this->log_event('Database import completed');
                $this->debug_echo(__('Database imported successfully.', 'cltd-migrate-free'));
            }

            $this->current_operation = null;
            if ($this->debug_enabled) {
                $this->finalize_debug_stream();
                exit;
            }
            wp_safe_redirect($this->get_return_url());
            exit;
        }

        /**
         * Handle restored import triggered after SFTP upload.
         */
        public function handle_restored_import() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to run a restored import.', 'cltd-migrate-free'));
            }

            check_admin_referer(self::RESTORED_IMPORT_ACTION);

            $this->current_operation = 'restored_import';
            $this->maybe_begin_debug_stream();

            $paths = $this->get_restoration_paths();
            if (is_wp_error($paths)) {
                $this->log_event('Restored import failed: files missing', ['error' => $paths->get_error_message()]);
                $this->respond_with_error($paths->get_error_message());
            }

            $site_path = $paths['site'];
            $db_path   = $paths['database'];

            $this->debug_echo(sprintf(__('Found site archive: %s', 'cltd-migrate-free'), $site_path));
            $site_result = $this->process_site_archive($site_path);
            if (is_wp_error($site_result)) {
                $this->log_event('Restored import failed during site extraction', ['error' => $site_result->get_error_message()]);
                $this->respond_with_error($site_result->get_error_message());
            }

            $this->debug_echo(sprintf(__('Site files restored. Importing database from %s ...', 'cltd-migrate-free'), $db_path));
            $db_result = $this->import_database($db_path, [
                'drop_existing'   => true,
                'force_overwrite' => true,
            ]);
            if (is_wp_error($db_result)) {
                $this->log_event('Restored import failed during DB import', ['error' => $db_result->get_error_message()]);
                $this->respond_with_error($db_result->get_error_message());
            }

            $old_url = $this->detect_imported_site_url();
            $new_url = $this->current_site_url;
            if ($old_url && $old_url !== $new_url) {
                $rewrite = $this->rewrite_urls_after_import($old_url, $new_url);
                if (is_wp_error($rewrite)) {
                    $this->log_event('Restored import URL rewrite failed', ['error' => $rewrite->get_error_message()]);
                    $this->respond_with_error($rewrite->get_error_message());
                }
            }
            $normalize_blocks = $this->normalize_block_markup_after_import();
            if (is_wp_error($normalize_blocks)) {
                $this->log_event('Restored import block normalization failed', ['error' => $normalize_blocks->get_error_message()]);
                $this->respond_with_error($normalize_blocks->get_error_message());
            }
            $this->refresh_theme_assets();
            $this->maybe_fix_wp_content_permissions();

            $this->store_notice('success', __('Full restore completed. URLs, blocks, and assets were normalized automatically.', 'cltd-migrate-free'));
            $this->log_event('Restored import completed');
            $this->debug_echo(__('Restored import completed successfully.', 'cltd-migrate-free'));

            $this->current_operation = null;
            if ($this->debug_enabled) {
                $this->finalize_debug_stream();
                exit;
            }
            wp_safe_redirect(admin_url());
            exit;
        }

        private function __construct() {
            $this->debug_enabled = (bool) apply_filters(
                'cltd_migrate_free_debug_output',
                defined('CLTD_MIGRATE_FREE_DEBUG') ? CLTD_MIGRATE_FREE_DEBUG : false
            );
            $this->current_site_url = untrailingslashit(home_url());

            $this->apply_resource_overrides();
            register_shutdown_function([$this, 'handle_shutdown_error']);

            add_action('admin_menu', [$this, 'register_admin_page']);
            add_action('admin_post_' . self::EXPORT_ACTION, [$this, 'handle_export']);
            add_action('admin_post_' . self::IMPORT_FILES_ACTION, [$this, 'handle_import_files']);
add_action('admin_post_' . self::IMPORT_DB_ACTION, [$this, 'handle_import_database']);
add_action('admin_post_' . self::RESTORED_IMPORT_ACTION, [$this, 'handle_restored_import']);
add_action('admin_post_' . self::DOWNLOAD_ACTION, [$this, 'handle_download']);
add_action('admin_notices', [$this, 'maybe_render_notice']);
add_action('send_headers', [$this, 'maybe_add_cors_headers']);
        }

        /**
         * Register the Tools → CLTD Migrate Free page.
         */
        public function register_admin_page() {
            add_management_page(
                __('CLTD Migrate Free', 'cltd-migrate-free'),
                __('CLTD Migrate Free', 'cltd-migrate-free'),
                'manage_options',
                self::MENU_SLUG,
                [$this, 'render_page']
            );
        }

        /**
         * Render the plugin admin UI.
         */
        public function render_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to access this page.', 'cltd-migrate-free'));
            }

            $zip_support       = class_exists('ZipArchive');
            $exec_support      = function_exists('exec') || function_exists('shell_exec');
            $upload_root       = $this->get_upload_root();
            $upload_msg        = is_wp_error($upload_root) ? $upload_root->get_error_message() : '';
            $upload_root_path  = !is_wp_error($upload_root) ? $upload_root : '';
            $restored_snapshot = !is_wp_error($upload_root) ? $this->get_restoration_snapshot() : ['upload_error' => $upload_root];
            $restored_ready    = $restored_snapshot && empty($restored_snapshot['upload_error']) && !is_wp_error($restored_snapshot['site']) && !is_wp_error($restored_snapshot['database']);
            $upload_dir_display = $upload_root_path
                ? '<code>' . esc_html($upload_root_path) . '</code>'
                : esc_html__('(uploads directory unavailable)', 'cltd-migrate-free');
            ?>
            <div class="wrap cltd-migrate-free">
                <h1><?php esc_html_e('CLTD Migrate Free', 'cltd-migrate-free'); ?></h1>
                <p><?php esc_html_e('Export or import a complete WordPress site (database, themes, plugins, media, and core files) in a single .zip archive.', 'cltd-migrate-free'); ?></p>

                <div class="cltd-migrate-free__system-check">
                    <h2><?php esc_html_e('System Status', 'cltd-migrate-free'); ?></h2>
                    <ul>
                        <li><?php echo $zip_support ? '✅' : '⚠️'; ?> <?php esc_html_e('PHP ZipArchive extension', 'cltd-migrate-free'); ?> — <?php echo $zip_support ? esc_html__('available', 'cltd-migrate-free') : esc_html__('missing', 'cltd-migrate-free'); ?></li>
                        <li><?php echo $exec_support ? '✅' : '⚠️'; ?> <?php esc_html_e('exec()/shell_exec()', 'cltd-migrate-free'); ?> — <?php echo $exec_support ? esc_html__('enabled (CLI mode available)', 'cltd-migrate-free') : esc_html__('disabled (using PHP fallback)', 'cltd-migrate-free'); ?></li>
                        <li><?php echo empty($upload_msg) ? '✅' : '⚠️'; ?> <?php esc_html_e('Writable uploads directory', 'cltd-migrate-free'); ?> — <?php echo empty($upload_msg) ? esc_html__('ready', 'cltd-migrate-free') : esc_html($upload_msg); ?></li>
                    </ul>
                </div>

                <hr>

                <h2 id="cltd-export-ready"><?php esc_html_e('Create Export Package', 'cltd-migrate-free'); ?></h2>
                <p><?php esc_html_e('Generate two separate files — one for site files and one for the database — for more reliable migrations.', 'cltd-migrate-free'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::EXPORT_ACTION); ?>">
                    <?php wp_nonce_field(self::EXPORT_ACTION); ?>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Generate Export Files', 'cltd-migrate-free'); ?></button></p>
                </form>
                <?php
                $latest_export = $this->get_cached_export_context();
                if ($latest_export && !empty($latest_export['files'])) :
                    ?>
                    <div class="cltd-migrate-free__export-links">
                        <p>
                            <?php
                            printf(
                                /* translators: %s: human time diff */
                                esc_html__('Latest export created %s ago. Download each file below:', 'cltd-migrate-free'),
                                esc_html(human_time_diff($latest_export['created'], current_time('timestamp')))
                            );
                            ?>
                        </p>
                        <p>
                            <a class="button button-secondary" href="<?php echo esc_url($this->get_download_url('site')); ?>">
                                <?php esc_html_e('Download Site Files (.zip)', 'cltd-migrate-free'); ?>
                            </a>
                            <a class="button button-secondary" href="<?php echo esc_url($this->get_download_url('database')); ?>">
                                <?php esc_html_e('Download Database (.sql)', 'cltd-migrate-free'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <hr>

                <h2><?php esc_html_e('Restored Import (SFTP)', 'cltd-migrate-free'); ?></h2>
                <p>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: 1: site zip filename, 2: database sql filename, 3: directory path */
                            __('Upload your exported files (%1$s and %2$s) via SFTP or FileZilla to %3$s, then run the automated restore below.', 'cltd-migrate-free'),
                            '<code>cltd-site*.zip</code>',
                            '<code>cltd-database*.sql</code>',
                            $upload_dir_display
                        )
                    );
                    ?>
                </p>
                <?php if ($restored_snapshot && isset($restored_snapshot['upload_error'])) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html($restored_snapshot['upload_error']->get_error_message()); ?></p></div>
                <?php else : ?>
                    <ul class="cltd-migrate-free__status-list">
                        <li>
                            <?php if ($restored_snapshot && !is_wp_error($restored_snapshot['site'])) : ?>
                                ✅ <?php esc_html_e('Site archive detected:', 'cltd-migrate-free'); ?>
                                <?php echo esc_html($restored_snapshot['site']['filename']); ?>
                                (<?php echo esc_html(size_format($restored_snapshot['site']['size'])); ?>)
                            <?php else : ?>
                                ⚠️ <?php esc_html_e('Site archive not found. Upload cltd-site*.zip.', 'cltd-migrate-free'); ?>
                            <?php endif; ?>
                        </li>
                        <li>
                            <?php if ($restored_snapshot && !is_wp_error($restored_snapshot['database'])) : ?>
                                ✅ <?php esc_html_e('Database dump detected:', 'cltd-migrate-free'); ?>
                                <?php echo esc_html($restored_snapshot['database']['filename']); ?>
                                (<?php echo esc_html(size_format($restored_snapshot['database']['size'])); ?>)
                            <?php else : ?>
                                ⚠️ <?php esc_html_e('Database dump not found. Upload cltd-database*.sql.', 'cltd-migrate-free'); ?>
                            <?php endif; ?>
                        </li>
                    </ul>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::RESTORED_IMPORT_ACTION); ?>">
                        <?php wp_nonce_field(self::RESTORED_IMPORT_ACTION); ?>
                        <p>
                            <button type="submit" class="button button-primary" <?php disabled(!$restored_ready); ?>>
                                <?php esc_html_e('Run Restored Import', 'cltd-migrate-free'); ?>
                            </button>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Restored import now replaces the entire site automatically: all WordPress tables are dropped, files are overwritten, URLs are rewritten, and permissions are reset for Bitnami stacks.', 'cltd-migrate-free'); ?>
                        </p>
                        <?php if (!$restored_ready) : ?>
                            <p class="description">
                                <?php esc_html_e('Both files must be present in the uploads directory before running the restored import.', 'cltd-migrate-free'); ?>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Handle export submissions.
         */
        public function handle_export() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to export this site.', 'cltd-migrate-free'));
            }

            check_admin_referer(self::EXPORT_ACTION);

            $this->current_operation = 'export';

            $timestamp    = gmdate('Ymd-His');
            $site_result  = $this->create_site_archive_file($timestamp);
            if (is_wp_error($site_result)) {
                $this->store_notice('error', $site_result->get_error_message());
                $this->log_event('Export failed (site archive)', ['error' => $site_result->get_error_message()]);
                $this->current_operation = null;
                wp_safe_redirect($this->get_return_url());
                exit;
            }

            $database_result = $this->create_database_dump_file($timestamp);
            if (is_wp_error($database_result)) {
                $this->remove_path($site_result['path']);
                $this->store_notice('error', $database_result->get_error_message());
                $this->log_event('Export failed (database dump)', ['error' => $database_result->get_error_message()]);
                $this->current_operation = null;
                wp_safe_redirect($this->get_return_url());
                exit;
            }

            $this->cache_export_context([
                'created' => current_time('timestamp'),
                'files'   => [
                    'site'     => $site_result,
                    'database' => $database_result,
                ],
            ]);

            $this->store_notice('success', __('Export files generated. Download them below.', 'cltd-migrate-free'));
            $this->current_operation = null;
            wp_safe_redirect($this->page_url() . '#cltd-export-ready');
            exit;
        }

        /**
         * Serve generated export files upon request.
         */
        public function handle_download() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to download exports.', 'cltd-migrate-free'));
            }

            $file_key = isset($_GET['export_file']) ? sanitize_key($_GET['export_file']) : '';
            if (!in_array($file_key, ['site', 'database'], true)) {
                wp_die(__('Invalid export file requested.', 'cltd-migrate-free'));
            }

            check_admin_referer('cltd_migrate_free_download_' . $file_key);

            $context = $this->get_cached_export_context();
            if (!$context || empty($context['files'][$file_key])) {
                $this->store_notice('error', __('That export file is no longer available. Please generate a new export.', 'cltd-migrate-free'));
                wp_safe_redirect($this->page_url());
                exit;
            }

            $file = $context['files'][$file_key];
            if (!file_exists($file['path'])) {
                $this->store_notice('error', __('The requested export file was missing. Generate a new export to continue.', 'cltd-migrate-free'));
                wp_safe_redirect($this->page_url());
                exit;
            }

            $this->stream_download($file['path'], $file['filename'], false);
        }

        /**
         * Handle site file import submissions (Step 1).
         */
        public function handle_import_files() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to import a site.', 'cltd-migrate-free'));
            }

            check_admin_referer(self::IMPORT_FILES_ACTION);

            $this->current_operation = 'import';
            $this->maybe_begin_debug_stream();

            if (empty($_POST['cltd_migrate_free_confirm'])) {
                $this->log_event('Import failed: confirmation checkbox missing');
                $this->respond_with_error(__('Please confirm that you understand the import will overwrite this site.', 'cltd-migrate-free'));
            }

            if (empty($_FILES['cltd_migrate_free_files_package']) || empty($_FILES['cltd_migrate_free_files_package']['tmp_name'])) {
                $this->log_event('Import failed: no file uploaded');
                $this->respond_with_error(__('No site archive was uploaded.', 'cltd-migrate-free'));
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';

            $overrides = [
                'test_form' => false,
                'mimes'     => [
                    'zip' => 'application/zip',
                ],
            ];

            $uploaded = wp_handle_upload($_FILES['cltd_migrate_free_files_package'], $overrides);
            if (isset($uploaded['error'])) {
                $message = sprintf(__('Upload failed: %s', 'cltd-migrate-free'), $uploaded['error']);
                $this->log_event('Import failed during upload', ['error' => $uploaded['error']]);
                $this->respond_with_error($message);
            }

            $package_path = $uploaded['file'];
            $this->log_event('Import uploaded site archive', ['path' => $package_path]);
            $this->debug_echo(sprintf(__('Uploaded archive stored at %s', 'cltd-migrate-free'), $package_path));
            $result       = $this->process_site_archive($package_path);

            $this->remove_path($package_path);

            if (is_wp_error($result)) {
                $this->log_event('Import failed during processing', ['error' => $result->get_error_message()]);
                $this->respond_with_error($result->get_error_message());
            } else {
                $this->store_notice('success', __('Site files imported. Proceed to Step 2 to restore the database.', 'cltd-migrate-free'));
                $this->log_event('File import completed');
                $this->debug_echo(__('Site files imported successfully.', 'cltd-migrate-free'));
            }

            $this->current_operation = null;
            if ($this->debug_enabled) {
                $this->finalize_debug_stream();
                exit;
            }
            wp_safe_redirect($this->get_return_url());
            exit;
        }

        /**
         * Create the site archive (.zip) without the database file.
         *
         * @param string $timestamp
         * @return array|WP_Error
         */
        private function create_site_archive_file($timestamp) {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('zip_missing', __('ZipArchive PHP extension is required.', 'cltd-migrate-free'));
            }

            $uploads = $this->get_upload_root();
            if (is_wp_error($uploads)) {
                return $uploads;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $zip_path = trailingslashit($uploads) . 'cltd-site-' . $timestamp . '.zip';
            $zip      = new ZipArchive();
            if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                return new WP_Error('zip_open_failed', __('Unable to create site archive.', 'cltd-migrate-free'));
            }

            $root     = untrailingslashit(ABSPATH);
            $excluded = trailingslashit($uploads);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $path => $info) {
                $normalized = str_replace('\\', '/', $path);
                if (strpos($normalized, $excluded) === 0) {
                    continue;
                }

                $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
                if ($relative === '') {
                    continue;
                }

                $zip_pathname = str_replace('\\', '/', $relative);

                if ($info->isDir()) {
                    $zip->addEmptyDir(rtrim($zip_pathname, '/') . '/');
                } else {
                    $zip->addFile($path, $zip_pathname);
                }
            }

            $zip->close();

            return [
                'path'     => $zip_path,
                'filename' => basename($zip_path),
            ];
        }

        /**
         * Create a standalone database dump (.sql).
         *
         * @param string $timestamp
         * @return array|WP_Error
         */
        private function create_database_dump_file($timestamp) {
            $uploads = $this->get_upload_root();
            if (is_wp_error($uploads)) {
                return $uploads;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $sql_path = trailingslashit($uploads) . 'cltd-database-' . $timestamp . '.sql';
            $result   = $this->dump_database($sql_path);
            if (is_wp_error($result)) {
                return $result;
            }

            return [
                'path'     => $sql_path,
                'filename' => basename($sql_path),
            ];
        }

        /**
         * Stream a generated file to the browser.
         *
         * @param string  $path
         * @param string  $filename
         * @param boolean $delete_after
         */
        private function stream_download($path, $filename, $delete_after = true) {
            if (!file_exists($path) || !is_readable($path)) {
                $this->store_notice('error', __('Export file no longer exists or is not readable.', 'cltd-migrate-free'));
                wp_safe_redirect($this->get_return_url());
                exit;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            @ini_set('zlib.output_compression', 'Off');
            while (ob_get_level()) {
                @ob_end_clean();
            }

            $filesize = filesize($path);
            $safe     = sanitize_file_name($filename);

            nocache_headers();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $safe . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . $filesize);
            header('Connection: close');

            $handle = fopen($path, 'rb');
            if ($handle) {
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
            } else {
                readfile($path);
            }

            if ($delete_after) {
                $this->remove_path($path);
            }
            exit;
        }

        /**
         * Process an imported site archive (.zip).
         *
         * @param string $package_path
         * @return true|WP_Error
         */
        private function process_site_archive($package_path) {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('zip_missing', __('ZipArchive PHP extension is required to import.', 'cltd-migrate-free'));
            }

            $uploads = $this->get_upload_root();
            if (is_wp_error($uploads)) {
                return $uploads;
            }

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $this->log_event('Processing site archive', [
                'memory_limit'      => ini_get('memory_limit'),
                'max_execution_time'=> ini_get('max_execution_time'),
            ]);

            $zip = new ZipArchive();
            $this->debug_echo(__('Opening archive...', 'cltd-migrate-free'));
            if (true !== $zip->open($package_path)) {
                $this->log_event('Import failed: unable to open archive', ['package' => $package_path]);
                return new WP_Error('zip_open_failed', __('Unable to open the uploaded archive.', 'cltd-migrate-free'));
            }

            $extract_dir = trailingslashit($uploads) . 'import-' . uniqid('', true);
            if (!wp_mkdir_p($extract_dir)) {
                $zip->close();
                $this->log_event('Import failed: cannot create extraction directory', ['dir' => $extract_dir]);
                return new WP_Error('extract_failed', __('Failed to create extraction directory.', 'cltd-migrate-free'));
            }

            $this->debug_echo(sprintf(__('Extracting archive to %s ...', 'cltd-migrate-free'), $extract_dir));
            if (!$zip->extractTo($extract_dir)) {
                $zip->close();
                $this->remove_path($extract_dir);
                $status = method_exists($zip, 'getStatusString') ? $zip->getStatusString() : 'unknown';
                $this->log_event('Import failed: extraction failed', ['dir' => $extract_dir, 'status' => $status]);
                return new WP_Error('extract_failed', __('Archive extraction failed.', 'cltd-migrate-free'));
            }

            $zip->close();

            if (!wp_is_writable(ABSPATH)) {
                $this->remove_path($extract_dir);
                return new WP_Error('target_not_writable', sprintf(__('Destination directory %s is not writable. Update file permissions and try again.', 'cltd-migrate-free'), ABSPATH));
            }

            $this->debug_echo(__('Synchronizing site files...', 'cltd-migrate-free'));
            $sync_result = $this->synchronize_files($extract_dir, ABSPATH);
            $this->remove_path($extract_dir);
            if (is_wp_error($sync_result)) {
                $this->log_event('Import failed: file synchronization error', ['error' => $sync_result->get_error_message()]);
                return $sync_result;
            }

            $this->debug_echo(__('Cleanup complete.', 'cltd-migrate-free'));

            return true;
        }

        /**
         * Dump the database via CLI or PHP fallback.
         *
         * @param string $destination
         * @return true|WP_Error
         */
        private function dump_database($destination) {
            global $wpdb;

            if ($this->command_available('mysqldump')) {
                $command = $this->build_mysqldump_command($destination);
                $result  = $this->run_shell_command($command);
                if ($result['code'] === 0 && file_exists($destination)) {
                    return true;
                }
            }

            $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
            if (empty($tables)) {
                $this->log_event('Database dump failed: could not read tables');
                return new WP_Error('db_dump_failed', __('Unable to read database tables.', 'cltd-migrate-free'));
            }

            $dump = '';
            foreach ($tables as $row) {
                $table = $row[0];
                $create = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
                if (!$create) {
                    continue;
                }

                $dump .= "\nDROP TABLE IF EXISTS `$table`;\n";
                $dump .= $create[1] . ";\n\n";

                $data_rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
                foreach ($data_rows as $data) {
                    $values = array_map(function($value) use ($wpdb) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        return "'" . esc_sql((string) $value) . "'";
                    }, array_values($data));

                    $dump .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                }
            }

            if (false === file_put_contents($destination, $dump)) {
                $this->log_event('Database dump failed: cannot write file', ['destination' => $destination]);
                return new WP_Error('db_dump_failed', __('Failed to write database dump.', 'cltd-migrate-free'));
            }

            return true;
        }

        /**
         * Import the database via CLI or PHP fallback.
         *
         * @param string $path
         * @param array  $args {
         *     @type bool $drop_existing   Whether to drop key content tables before import.
         *     @type bool $force_overwrite Whether to drop every table matching the WordPress prefix before import.
         * }
         * @return true|WP_Error
         */
        private function import_database($path, array $args = []) {
            global $wpdb;

            $args = wp_parse_args(
                $args,
                [
                    'drop_existing'   => false,
                    'force_overwrite' => false,
                ]
            );

            if (!file_exists($path) || !is_readable($path)) {
                $this->log_event('Database import failed: file missing', ['path' => $path]);
                return new WP_Error('db_import_missing', __('Database import file could not be read.', 'cltd-migrate-free'));
            }

            $drop_mode = 'none';
            if (!empty($args['force_overwrite'])) {
                $drop_mode = 'all';
            } elseif (!empty($args['drop_existing'])) {
                $drop_mode = 'content';
            }

            if ('none' !== $drop_mode) {
                $prepare = $this->prepare_database_for_import($drop_mode);
                if (is_wp_error($prepare)) {
                    return $prepare;
                }
            }

            $term_defer_original    = null;
            $comment_defer_original = null;

            if (function_exists('wp_defer_term_counting')) {
                $term_defer_original = wp_defer_term_counting();
                wp_defer_term_counting(true);
            }

            if (function_exists('wp_defer_comment_counting')) {
                $comment_defer_original = wp_defer_comment_counting();
                wp_defer_comment_counting(true);
            }

            $import_success = false;
            $fallback_error = null;

            if ($this->command_available('mysql')) {
                $command = $this->build_mysql_import_command($path);
                $result  = $this->run_shell_command($command);
                if ($result['code'] === 0) {
                    $import_success = true;
                } else {
                    $this->log_event('MySQL CLI import failed', ['output' => $result['output']]);
                }
            }

            if (!$import_success) {
                $php_result = $this->run_sql_import_via_php($path);
                if (is_wp_error($php_result)) {
                    $fallback_error = $php_result;
                } else {
                    $import_success = true;
                }
            }

            if (function_exists('wp_defer_term_counting') && null !== $term_defer_original) {
                wp_defer_term_counting($term_defer_original);
            }

            if (function_exists('wp_defer_comment_counting') && null !== $comment_defer_original) {
                wp_defer_comment_counting($comment_defer_original);
            }

            if (!$import_success) {
                return $fallback_error instanceof WP_Error
                    ? $fallback_error
                    : new WP_Error('db_import_failed', __('Database import failed.', 'cltd-migrate-free'));
            }

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules(true);
            }

            return true;
        }

        /**
         * Execute SQL statements from a dump file using PHP.
         *
         * @param string $path
         * @return true|WP_Error
         */
        private function run_sql_import_via_php($path) {
            global $wpdb;

            $sql = file_get_contents($path);
            if ($sql === false) {
                $this->log_event('Database import failed: database.sql unreadable', ['path' => $path]);
                return new WP_Error('db_import_failed', __('Unable to read database.sql during import.', 'cltd-migrate-free'));
            }

            $statements = preg_split('/;\s*(?:\r?\n|\r|$)/', $sql);
            foreach ($statements as $statement) {
                $statement = trim((string) $statement);
                if ($statement === '') {
                    continue;
                }

                $wpdb->query($statement); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                if ($wpdb->last_error) {
                    $this->log_event('Database import failed (PHP fallback)', ['error' => $wpdb->last_error]);
                    return new WP_Error('db_import_failed', sprintf(__('Database import error: %s', 'cltd-migrate-free'), $wpdb->last_error));
                }
            }

            return true;
        }

        /**
         * Drop tables ahead of an import to guarantee a clean slate.
         *
         * @param string $mode Accepts 'content' (default) or 'all'.
         * @return true|WP_Error
         */
        private function prepare_database_for_import($mode = 'content') {
            global $wpdb;

            $mode   = ($mode === 'all') ? 'all' : 'content';
            $tables = ('all' === $mode) ? $this->get_tables_with_prefix($wpdb->prefix) : $this->get_content_tables();

            if (empty($tables)) {
                return true;
            }

            $this->debug_echo(
                'all' === $mode
                    ? __('Force overwrite enabled. Dropping all prefixed tables before import...', 'cltd-migrate-free')
                    : __('Dropping key content tables before import...', 'cltd-migrate-free')
            );
            $this->log_event('Preparing database for import', ['mode' => $mode, 'tables' => $tables]);

            $foreign_keys_disabled = false;
            if ($wpdb->query('SET FOREIGN_KEY_CHECKS = 0') !== false) {
                $foreign_keys_disabled = true;
            }

            foreach ($tables as $table) {
                $formatted = $this->format_db_identifier($table, true);
                if (!$formatted) {
                    continue;
                }

                $result = $wpdb->query("DROP TABLE IF EXISTS {$formatted}");
                if ($result === false) {
                    if ($foreign_keys_disabled) {
                        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
                    }
                    return new WP_Error('db_drop_failed', sprintf(__('Unable to drop table %s prior to import.', 'cltd-migrate-free'), $table));
                }
            }

            if ($foreign_keys_disabled) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }

            return true;
        }

        /**
         * Retrieve every table that matches the provided prefix.
         *
         * @param string $prefix
         * @return array
         */
        private function get_tables_with_prefix($prefix) {
            global $wpdb;

            if (!$prefix) {
                return [];
            }

            $pattern = str_replace(['_', '%'], ['\_', '\%'], $prefix) . '%';
            $tables  = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));

            return array_values(array_filter((array) $tables));
        }

        /**
         * Key content tables that should be refreshed for restored imports.
         *
         * @return array
         */
        private function get_content_tables() {
            global $wpdb;

            $tables = [
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->comments,
                $wpdb->commentmeta,
                $wpdb->terms,
                $wpdb->term_taxonomy,
                $wpdb->term_relationships,
                $wpdb->termmeta,
                $wpdb->prefix . 'block',
            ];

            return array_values(array_unique(array_filter($tables)));
        }

        /**
         * Copy files from extracted archive into WordPress root.
         *
         * @param string $source
         * @param string $destination
         * @return true|WP_Error
         */
        private function synchronize_files($source, $destination) {
            $source      = untrailingslashit($source);
            $destination = untrailingslashit($destination);

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $path => $info) {
                $relative = ltrim(str_replace($source, '', $path), DIRECTORY_SEPARATOR);
                if ($relative === '') {
                    continue;
                }

                $normalized_relative = ltrim(str_replace('\\', '/', $relative), '/');

                if (strpos($normalized_relative, 'uploads/cltd-migrate-free') === 0
                    || strpos($normalized_relative, 'wp-content/uploads/cltd-migrate-free') === 0
                    || strpos($normalized_relative, 'wp-content/ai1wm-backups') === 0) {
                    continue;
                }

                if (strpos($normalized_relative, 'wp-content/plugins/cltd-migrate-free') === 0) {
                    continue;
                }

                if ('wp-config.php' === $normalized_relative) {
                    continue;
                }

                $target_path = $destination . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);

                if ($info->isDir()) {
                    if (!is_dir($target_path) && !wp_mkdir_p($target_path)) {
                        $this->log_event('File sync failed: cannot create directory', ['path' => $target_path]);
                        return new WP_Error('file_sync_failed', sprintf(__('Failed to create directory: %s', 'cltd-migrate-free'), $target_path));
                    }
                } else {
                    $parent = dirname($target_path);
                    if (!is_dir($parent) && !wp_mkdir_p($parent)) {
                        $this->log_event('File sync failed: cannot prepare parent directory', ['path' => $parent]);
                        return new WP_Error('file_sync_failed', sprintf(__('Failed to prepare directory for file: %s', 'cltd-migrate-free'), $parent));
                    }

                    if (!copy($path, $target_path)) {
                        $error = error_get_last();
                        $this->log_event('File sync failed: copy failed', ['file' => $relative, 'error' => $error]);
                        $details = $error && isset($error['message']) ? ' (' . $error['message'] . ')' : '';
                        return new WP_Error('file_sync_failed', sprintf(__('Failed to copy file: %s%s', 'cltd-migrate-free'), $relative, $details));
                    }
                }
            }

            return true;
        }

        /**
         * Determine if a shell command is available.
         *
         * @param string $command
         * @return bool
         */
        private function command_available($command) {
            if (!function_exists('exec')) {
                return false;
            }

            $probe = stripos(PHP_OS_FAMILY, 'Windows') !== false
                ? 'where ' . escapeshellarg($command)
                : 'command -v ' . escapeshellarg($command) . ' 2>/dev/null';

            exec($probe, $output, $code);
            return $code === 0;
        }

        /**
         * Run a shell command and capture output.
         *
         * @param string $command
         * @return array{code:int,output:string}
         */
        private function run_shell_command($command) {
            $code   = 1;
            $output = '';

            if (function_exists('exec')) {
                $lines = [];
                exec($command, $lines, $code);
                $output = implode("\n", $lines);
            } elseif (function_exists('shell_exec')) {
                $output = shell_exec($command);
                $code   = 0 === stripos((string) $output, 'error') ? 1 : 0;
            }

            return [
                'code'   => $code,
                'output' => $output,
            ];
        }

        /**
         * Build mysqldump command string.
         *
         * @param string $destination
         * @return string
         */
        private function build_mysqldump_command($destination) {
            $parts = [
                'mysqldump',
                '--single-transaction',
                '--quick',
                '--lock-tables=false',
                '--user=' . escapeshellarg(DB_USER),
                '--password=' . escapeshellarg(DB_PASSWORD),
            ];

            $host_parts = explode(':', DB_HOST);
            $parts[]    = '--host=' . escapeshellarg($host_parts[0]);
            if (isset($host_parts[1])) {
                $parts[] = '--port=' . (int) $host_parts[1];
            }

            $parts[] = escapeshellarg(DB_NAME);

            return implode(' ', $parts) . ' > ' . escapeshellarg($destination) . ' 2>&1';
        }

        /**
         * Build mysql import command string.
         *
         * @param string $source
         * @return string
         */
        private function build_mysql_import_command($source) {
            $parts = [
                'mysql',
                '--user=' . escapeshellarg(DB_USER),
                '--password=' . escapeshellarg(DB_PASSWORD),
            ];

            $host_parts = explode(':', DB_HOST);
            $parts[]    = '--host=' . escapeshellarg($host_parts[0]);
            if (isset($host_parts[1])) {
                $parts[] = '--port=' . (int) $host_parts[1];
            }

            $parts[] = escapeshellarg(DB_NAME);

            return implode(' ', $parts) . ' < ' . escapeshellarg($source) . ' 2>&1';
        }

        /**
         * Delete files or directories recursively.
         *
         * @param string $path
         */
        private function remove_path($path) {
            if (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    $target = $fileinfo->getRealPath();
                    if ($fileinfo->isDir()) {
                        @rmdir($target);
                    } else {
                        @unlink($target);
                    }
                }

                @rmdir($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }

        /**
         * Locate (and ensure) the plugin working directory inside uploads.
         *
         * @return string|WP_Error
         */
        private function get_upload_root() {
            $uploads = wp_upload_dir();
            if (!empty($uploads['error'])) {
                return new WP_Error('upload_dir_error', $uploads['error']);
            }

            $path = trailingslashit($uploads['basedir']) . 'cltd-migrate-free';
            if (!wp_mkdir_p($path)) {
                return new WP_Error('upload_dir_error', __('Unable to create uploads directory for CLTD Migrate Free.', 'cltd-migrate-free'));
            }

            return $path;
        }

        /**
         * Store an admin notice for the current user.
         *
         * @param string $type
         * @param string $message
         */
        private function store_notice($type, $message) {
            if (!is_user_logged_in()) {
                return;
            }

            set_transient($this->get_notice_key(), [
                'type'    => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
                'message' => $message,
            ], MINUTE_IN_SECONDS);
        }

        /**
         * Render a stored admin notice when on the plugin screen.
         */
        public function maybe_render_notice() {
            if (!is_user_logged_in() || !function_exists('get_current_screen')) {
                return;
            }

            $screen = get_current_screen();
            if (!$screen || 'tools_page_' . self::MENU_SLUG !== $screen->id) {
                return;
            }

            $notice = get_transient($this->get_notice_key());
            if (!$notice) {
                return;
            }

            delete_transient($this->get_notice_key());

            printf(
                '<div class="notice notice-%1$s"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        /**
         * Build the admin page URL.
         *
         * @return string
         */
        private function page_url() {
            return admin_url('tools.php?page=' . self::MENU_SLUG);
        }

        /**
         * Determine where to redirect after processing (prefers referer).
         *
         * @return string
         */
        private function get_return_url() {
            $referer_field = isset($_REQUEST['_wp_http_referer']) ? wp_unslash($_REQUEST['_wp_http_referer']) : '';
            if ($referer_field && wp_http_validate_url($referer_field)) {
                return $referer_field;
            }

            $http_referer = wp_get_referer();
            if ($http_referer && wp_http_validate_url($http_referer)) {
                return $http_referer;
            }

            return $this->page_url();
        }

        /**
         * Persist export file metadata for the current user.
         *
         * @param array $context
         */
        private function cache_export_context(array $context) {
            set_transient($this->get_export_transient_key(), $context, HOUR_IN_SECONDS);
        }

        /**
         * Retrieve cached export metadata for the current user.
         *
         * @return array|null
         */
        private function get_cached_export_context() {
            $context = get_transient($this->get_export_transient_key());
            if (!is_array($context) || empty($context['files'])) {
                return null;
            }

            foreach ($context['files'] as $type => $file) {
                if (empty($file['path']) || !file_exists($file['path'])) {
                    unset($context['files'][$type]);
                }
            }

            if (empty($context['files'])) {
                delete_transient($this->get_export_transient_key());
                return null;
            }

            return $context;
        }

        /**
         * Build a secure download URL for the requested export file.
         *
         * @param string $type
         * @return string
         */
        private function get_download_url($type) {
            $context = $this->get_cached_export_context();
            if (!$context || empty($context['files'][$type])) {
                return '#';
            }

            return wp_nonce_url(
                add_query_arg(
                    [
                        'action'      => self::DOWNLOAD_ACTION,
                        'export_file' => $type,
                    ],
                    admin_url('admin-post.php')
                ),
                'cltd_migrate_free_download_' . $type
            );
        }

        /**
         * Build storage info for restored import files.
         *
         * @return array
         */
        private function get_restoration_snapshot() {
            $uploads = $this->get_upload_root();
            if (is_wp_error($uploads)) {
                return ['upload_error' => $uploads];
            }

            $site     = $this->find_latest_matching_file($uploads, 'cltd-site*.zip');
            $database = $this->find_latest_matching_file($uploads, 'cltd-database*.sql');

            return [
                'upload_dir' => $uploads,
                'site'       => $site,
                'database'   => $database,
                'ready'      => !is_wp_error($site) && !is_wp_error($database),
            ];
        }

        /**
         * Locate required files for restored import.
         *
         * @return array|WP_Error
         */
        private function get_restoration_paths() {
            $snapshot = $this->get_restoration_snapshot();
            if (isset($snapshot['upload_error'])) {
                return $snapshot['upload_error'];
            }

            if (is_wp_error($snapshot['site'])) {
                return $snapshot['site'];
            }

            if (is_wp_error($snapshot['database'])) {
                return $snapshot['database'];
            }

            return [
                'site'     => $snapshot['site']['path'],
                'database' => $snapshot['database']['path'],
            ];
        }

        /**
         * Find the newest file matching a pattern within a directory.
         *
         * @param string $directory
         * @param string $pattern
         * @return array|WP_Error
         */
        private function find_latest_matching_file($directory, $pattern) {
            $glob_pattern = trailingslashit($directory) . $pattern;
            $files        = glob($glob_pattern);

            if (!$files) {
                return new WP_Error(
                    'file_missing',
                    sprintf(__('No file matching %s was found in %s.', 'cltd-migrate-free'), $pattern, $directory)
                );
            }

            usort($files, function($a, $b) {
                return filemtime($b) <=> filemtime($a);
            });

            $path = $files[0];
            return [
                'path'     => $path,
                'filename' => basename($path),
                'size'     => filesize($path),
                'modified' => filemtime($path),
            ];
        }

        /**
         * Helper to identify the transient key used for export metadata.
         *
         * @return string
         */
        private function get_export_transient_key() {
            return 'cltd_migrate_free_export_' . get_current_user_id();
        }

        /**
         * Notice key scoped to current user.
         *
         * @return string
         */
        private function get_notice_key() {
            return 'cltd_migrate_notice_' . get_current_user_id();
        }

        /**
         * Respond to fatal import errors with an immediate message.
         *
         * @param string $message
         */
        private function respond_with_error($message) {
            $this->store_notice('error', $message);
            $this->current_operation = null;

            if ($this->debug_enabled || apply_filters('cltd_migrate_free_import_die_on_error', true)) {
                $this->debug_echo($message, true);
                $this->finalize_debug_stream();
                wp_die(esc_html($message));
            }

            wp_safe_redirect($this->get_return_url());
            exit;
        }

        /**
         * Detect the imported site URL from the database.
         *
         * @return string
         */
        private function detect_imported_site_url() {
            global $wpdb;

            // Bypass option cache to ensure we read the freshly imported value.
            $option_value = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl' LIMIT 1");
            if (!$option_value) {
                $option_value = get_option('siteurl');
            }

            if (!$option_value || !is_string($option_value)) {
                return '';
            }

            return untrailingslashit($option_value);
        }

        /**
         * Run a search/replace across key tables after import.
         *
         * @param string $old_url
         * @param string $new_url
         * @return true|WP_Error
         */
        private function rewrite_urls_after_import($old_url, $new_url) {
            global $wpdb;

            $this->debug_echo(sprintf(__('Replacing URLs: %1$s → %2$s', 'cltd-migrate-free'), $old_url, $new_url));
            $this->log_event('Starting URL rewrite', ['old' => $old_url, 'new' => $new_url]);

            update_option('siteurl', $new_url);
            update_option('home', $new_url);

            $tables = $wpdb->get_col('SHOW TABLES');
            if (empty($tables)) {
                return true;
            }

            foreach ($tables as $table) {
                $columns = $this->get_text_columns_for_table($table);
                if (empty($columns)) {
                    continue;
                }

                foreach ($columns as $column) {
                    $maybe_error = $this->replace_urls_in_column($table, $column, $old_url, $new_url);
                    if (is_wp_error($maybe_error)) {
                        return $maybe_error;
                    }
                }
            }

            $this->rewrite_uploaded_files($old_url, $new_url);

            $this->log_event('URL rewrite completed');
            return true;
        }

        /**
         * Normalize block markup after an import so custom blocks don't require manual recovery.
         *
         * @return true|WP_Error
         */
        private function normalize_block_markup_after_import() {
            global $wpdb;

            if (!function_exists('parse_blocks') || !function_exists('serialize_blocks')) {
                return true;
            }

            $this->debug_echo(__('Normalizing block markup in imported posts...', 'cltd-migrate-free'));
            $this->log_event('Beginning block normalization after import');

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $like_pattern = '%' . $wpdb->esc_like('<!-- wp:') . '%';
            $batch_size   = 250;
            $offset       = 0;

            do {
                $ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type <> %s AND post_content LIKE %s LIMIT %d OFFSET %d",
                        'revision',
                        $like_pattern,
                        $batch_size,
                        $offset
                    )
                );

                if (empty($ids)) {
                    break;
                }

                foreach ($ids as $post_id) {
                    $post = get_post($post_id);
                    if (!$post || !has_blocks($post->post_content)) {
                        continue;
                    }

                    $parsed = parse_blocks($post->post_content);
                    if (empty($parsed)) {
                        continue;
                    }

                    $serialized = serialize_blocks($parsed);
                    if (!$serialized || $serialized === $post->post_content) {
                        continue;
                    }

                    $updated = $wpdb->update(
                        $wpdb->posts,
                        ['post_content' => $serialized],
                        ['ID' => $post_id],
                        ['%s'],
                        ['%d']
                    );

                    if ($updated === false) {
                        return new WP_Error(
                            'block_normalization_failed',
                            sprintf(__('Failed to normalize block content for post ID %d.', 'cltd-migrate-free'), $post_id)
                        );
                    }

                    clean_post_cache($post_id);
                }

                $offset += $batch_size;
            } while (true);

            $this->log_event('Block normalization completed');
            return true;
        }

        /**
         * Reset wp-content ownership/permissions for Bitnami-style stacks.
         *
         * @return void
         */
        private function maybe_fix_wp_content_permissions() {
            $wp_content = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '';
            if (!$wp_content || !is_dir($wp_content)) {
                return;
            }

            $normalized = function_exists('wp_normalize_path')
                ? wp_normalize_path(untrailingslashit($wp_content))
                : str_replace('\\', '/', rtrim($wp_content, '/'));

            $bitnami_markers = [
                '/opt/bitnami/',
                '/bitnami/',
                '/home/bitnami/',
            ];

            $is_bitnami_path = false;
            foreach ($bitnami_markers as $marker) {
                if (strpos($normalized, $marker) === 0 || strpos($normalized, $marker . 'wordpress') !== false) {
                    $is_bitnami_path = true;
                    break;
                }
            }

            $should_fix = (bool) apply_filters('cltd_migrate_free_fix_permissions', $is_bitnami_path, $wp_content);
            if (!$should_fix) {
                return;
            }

            if (!function_exists('exec') && !function_exists('shell_exec')) {
                $this->log_event('Permission reset skipped: exec/shell_exec unavailable');
                return;
            }

            $owner = defined('CLTD_MIGRATE_FREE_PERMISSION_OWNER') ? CLTD_MIGRATE_FREE_PERMISSION_OWNER : 'bitnami';
            $group = defined('CLTD_MIGRATE_FREE_PERMISSION_GROUP') ? CLTD_MIGRATE_FREE_PERMISSION_GROUP : 'daemon';
            $mode  = defined('CLTD_MIGRATE_FREE_PERMISSION_MODE') ? CLTD_MIGRATE_FREE_PERMISSION_MODE : '775';

            $target_path = trailingslashit(realpath($wp_content) ?: $wp_content);

            $this->log_event('Attempting wp-content permission reset', [
                'path'  => $target_path,
                'owner' => $owner,
                'group' => $group,
                'mode'  => $mode,
            ]);
            $this->debug_echo(__('Ensuring wp-content ownership and permissions...', 'cltd-migrate-free'));

            $owner_arg  = escapeshellarg("{$owner}:{$group}");
            $target_arg = escapeshellarg(rtrim($target_path, '/'));
            $mode_arg   = escapeshellarg($mode);

            $this->run_permission_command_group([
                'sudo -n chown -R ' . $owner_arg . ' ' . $target_arg,
                'chown -R ' . $owner_arg . ' ' . $target_arg,
            ], 'chown');

            $this->run_permission_command_group([
                'sudo -n chmod -R ' . $mode_arg . ' ' . $target_arg,
                'chmod -R ' . $mode_arg . ' ' . $target_arg,
            ], 'chmod');
        }

        /**
         * Attempt a list of commands until one succeeds, logging the result.
         *
         * @param array  $commands
         * @param string $label
         * @return void
         */
        private function run_permission_command_group(array $commands, $label) {
            foreach ($commands as $command) {
                if (empty($command)) {
                    continue;
                }

                $result = $this->run_shell_command($command);
                if ($result['code'] === 0) {
                    $this->log_event("Permission command succeeded ({$label})");
                    return;
                }
            }

            $this->log_event("Permission command failed ({$label})");
        }

        /**
         * Retrieve text-based columns for a given table.
         *
         * @param string $table
         * @return array
         */
        private function get_text_columns_for_table($table) {
            global $wpdb;

            $formatted_table = $this->format_db_identifier($table, true);
            if (!$formatted_table) {
                return [];
            }

            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$formatted_table}");
            if ($wpdb->last_error || empty($columns)) {
                return [];
            }

            $text_columns = [];
            foreach ($columns as $column) {
                $type = strtolower($column->Type);
                if (preg_match('/char|text|blob/', $type)) {
                    $text_columns[] = $column->Field;
                }
            }

            return $text_columns;
        }

        /**
         * Replace URLs inside a specific column, safely handling serialized values.
         *
         * @param string $table
         * @param string $column
         * @param string $old_url
         * @param string $new_url
         * @return true|WP_Error
         */
        private function replace_urls_in_column($table, $column, $old_url, $new_url) {
            global $wpdb;

            $formatted_table  = $this->format_db_identifier($table, true);
            $formatted_column = $this->format_db_identifier($column, false);

            if (!$formatted_table || !$formatted_column) {
                return true;
            }

            $like  = '%' . $wpdb->esc_like($old_url) . '%';
            $query = $wpdb->prepare(
                "SELECT {$formatted_column} FROM {$formatted_table} WHERE {$formatted_column} LIKE %s",
                $like
            );
            $values = $wpdb->get_col($query);

            if ($wpdb->last_error) {
                return new WP_Error('url_replace_failed', sprintf(__('Failed to scan %1$s.%2$s: %3$s', 'cltd-migrate-free'), $table, $column, $wpdb->last_error));
            }

            foreach ($values as $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $updated = $this->replace_mixed_value($value, $old_url, $new_url);
                if ($updated === $value) {
                    continue;
                }

                $result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$formatted_table} SET {$formatted_column} = %s WHERE {$formatted_column} = %s",
                        $updated,
                        $value
                    )
                );

                if ($result === false && $wpdb->last_error) {
                    return new WP_Error('url_replace_failed', sprintf(__('Failed to update %1$s.%2$s: %3$s', 'cltd-migrate-free'), $table, $column, $wpdb->last_error));
                }
            }

            return true;
        }

        /**
         * Replace URLs within a string that may contain serialized data.
         *
         * @param string $value
         * @param string $old_url
         * @param string $new_url
         * @return string
         */
        private function replace_mixed_value($value, $old_url, $new_url) {
            if (!is_string($value)) {
                return $value;
            }

            if (is_serialized($value)) {
                $unserialized = maybe_unserialize($value);
                $updated      = $this->recursive_url_replace($old_url, $new_url, $unserialized);
                $serialized   = maybe_serialize($updated);
                return $this->normalize_upload_url_string($serialized);
            }

            $replaced = str_replace($old_url, $new_url, $value);
            return $this->normalize_upload_url_string($replaced);
        }

        /**
         * Sanitize table/column names for direct SQL usage.
         *
         * @param string $identifier
         * @param bool   $is_table
         * @return string
         */
        private function format_db_identifier($identifier, $is_table = false) {
            $pattern = $is_table ? '/^[A-Za-z0-9_]+$/' : '/^[A-Za-z0-9_]+$/';
            if (!preg_match($pattern, $identifier)) {
                return '';
            }

            return '`' . $identifier . '`';
        }

        /**
         * Clear transients and regenerate theme/block CSS assets.
         */
        private function refresh_theme_assets() {
            global $wpdb;

            $this->debug_echo(__('Clearing caches and regenerating block CSS...', 'cltd-migrate-free'));
            $this->log_event('Refreshing theme assets');

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules(true);
            }

            $this->regenerate_block_templates();

            if (function_exists('wp_clean_themes_cache')) {
                wp_clean_themes_cache();
            }

            if (function_exists('wp_clean_theme_json_cache')) {
                wp_clean_theme_json_cache();
            }

            $patterns = [
                '_transient_wp_global_styles_',
                '_transient_timeout_wp_global_styles_',
                '_site_transient_wp_global_styles_',
                '_site_transient_timeout_wp_global_styles_',
            ];

            foreach ($patterns as $pattern) {
                $like = $wpdb->esc_like($pattern) . '%';
                $wpdb->query(
                    $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like)
                );
            }

            if (function_exists('wp_get_global_stylesheet')) {
                wp_get_global_stylesheet([], true);
            }

            if (function_exists('wp_get_global_settings')) {
                wp_get_global_settings(null, ['merge' => true, 'skip_cache' => true]);
            }

            do_action('cltd_migrate_free_refreshed_assets');
        }

        /**
         * Ensure block templates and custom block assets are regenerated after imports.
         *
         * @return void
         */
        private function regenerate_block_templates() {
            if (function_exists('wp_generate_block_templates')) {
                wp_generate_block_templates();
                return;
            }

            /**
             * Allow external integrations to regenerate block templates when the core helper
             * is unavailable (older WordPress versions).
             */
            do_action('cltd_migrate_free_generate_block_templates');
        }

        /**
         * Recursively replace URLs in mixed data.
         *
         * @param string       $old
         * @param string       $new
         * @param mixed        $data
         * @return mixed
         */
        private function recursive_url_replace($old, $new, $data) {
            if (is_string($data)) {
                $replaced = str_replace($old, $new, $data);
                return $this->normalize_upload_url_string($replaced);
            }

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] = $this->recursive_url_replace($old, $new, $value);
                }
                return $data;
            }

            if (is_object($data)) {
                foreach ($data as $key => $value) {
                    $data->$key = $this->recursive_url_replace($old, $new, $value);
                }
                return $data;
            }

            return $data;
        }

        /**
         * Convert insecure upload URLs (any host) to the current site's HTTPS uploads URL.
         *
         * @param string $value
         * @return string
         */
        private function normalize_upload_url_string($value) {
            if (!is_string($value) || stripos($value, 'http://') === false) {
                return $value;
            }

            static $pattern = null;
            static $replacement = '';

            if ($pattern === null) {
                $uploads = wp_upload_dir();
                if (!empty($uploads['error'])) {
                    $pattern = false;
                    return $value;
                }

                $baseurl = $uploads['baseurl'];
                $base_path = wp_parse_url($baseurl, PHP_URL_PATH);
                $home_https = set_url_scheme(home_url(), 'https');
                $scheme = wp_parse_url($home_https, PHP_URL_SCHEME);

                if (!$base_path || 'https' !== strtolower((string) $scheme)) {
                    $pattern = false;
                    return $value;
                }

                $pattern = '#http://[^/]+(' . preg_quote($base_path, '#') . '[^"\'\s]*)#i';
                $replacement = untrailingslashit($home_https) . '$1';
            }

            if (!$pattern) {
                return $value;
            }

            $normalized = preg_replace($pattern, $replacement, $value);
            return is_string($normalized) ? $normalized : $value;
        }

        /**
         * Replace URLs inside uploaded asset files (CSS/JSON/etc).
         *
         * @param string $old_url
         * @param string $new_url
         */
        private function rewrite_uploaded_files($old_url, $new_url) {
            $uploads = wp_upload_dir();
            if (!empty($uploads['error'])) {
                $this->log_event('Skipping file-based URL rewrite due to upload dir error', ['error' => $uploads['error']]);
                return;
            }

            $directory = trailingslashit($uploads['basedir']);
            if (!is_dir($directory) || !wp_is_writable($directory)) {
                $this->log_event('Skipping file-based URL rewrite due to non-writable uploads', ['dir' => $directory]);
                return;
            }

            $allowed_extensions = (array) apply_filters('cltd_migrate_free_file_replace_extensions', ['css', 'json', 'html', 'htm', 'txt', 'xml', 'svg', 'js', 'ttf', 'otf', 'eot', 'woff', 'woff2', 'mp4', 'webm', 'ogg']);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file_info) {
                if (!$file_info->isFile()) {
                    continue;
                }

                $extension = strtolower($file_info->getExtension());
                if (!in_array($extension, $allowed_extensions, true)) {
                    continue;
                }

                $path    = $file_info->getRealPath();
                $content = @file_get_contents($path);
                if ($content === false || strpos($content, $old_url) === false) {
                    continue;
                }

                $updated = str_replace($old_url, $new_url, $content);
                $updated = $this->normalize_upload_url_string($updated);
                if ($updated === $content) {
                    continue;
                }

                $written = @file_put_contents($path, $updated);
                if (false === $written) {
                    $this->log_event('Failed to update uploaded file during URL rewrite', ['file' => $path]);
                }
            }
        }

        /**
         * Apply higher runtime limits to reduce timeouts.
         */
        private function apply_resource_overrides() {
            $limits = apply_filters('cltd_migrate_free_resource_limits', [
                'memory_limit'      => '512M',
                'max_execution_time'=> 0,
            ]);

            if (function_exists('ini_set')) {
                foreach ($limits as $setting => $value) {
                    if ($value === null) {
                        continue;
                    }
                    @ini_set($setting, (string) $value);
                }
            }

            if (function_exists('set_time_limit') && isset($limits['max_execution_time'])) {
                @set_time_limit((int) $limits['max_execution_time']);
            }
        }

        /**
         * Capture fatal shutdowns and log them for easier debugging.
         */
        public function handle_shutdown_error() {
            $error = error_get_last();
            if (!$error || null === $this->current_operation) {
                return;
            }

            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
            if (in_array($error['type'], $fatal_types, true)) {
                $message = sprintf(
                    'CLTD Migrate Free %s fatal error: %s in %s:%d',
                    $this->current_operation,
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
                $this->log_event($message);
                $this->store_notice('error', __('The migration process stopped unexpectedly. Check the PHP error log for details.', 'cltd-migrate-free'));
                $this->debug_echo(__('Fatal error detected. Check the PHP error log for details.', 'cltd-migrate-free'), true);
                $this->finalize_debug_stream();
            }
        }

        /**
         * Lightweight logger that funnels messages to PHP error_log.
         *
         * @param string $message
         * @param array  $context
         */
        private function log_event($message, array $context = []) {
            if (!apply_filters('cltd_migrate_free_enable_logging', true)) {
                return;
            }

            $entry = '[CLTD Migrate Free] ' . $message;
            if (!empty($context)) {
                $entry .= ' ' . wp_json_encode($context);
            }

            error_log($entry); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        /**
         * Ensure debug HTML wrapper exists if streaming is enabled.
         *
         * @return void
         */
        private function maybe_begin_debug_stream() {
            if (!$this->debug_enabled || $this->debug_started) {
                return;
            }

            while (ob_get_level()) {
                ob_end_flush();
            }

            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CLTD Migrate Free Debug</title>';
            echo '<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f6f7fb;color:#111;margin:0;padding:24px;}';
            echo '.cltd-debug{max-width:760px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.08);}';
            echo '.cltd-debug h1{margin-top:0;font-size:1.25rem;} .cltd-debug p{margin:0 0 .65rem;} .cltd-debug p span{font-weight:600;}';
            echo '</style></head><body><div class="cltd-debug"><h1>CLTD Migrate Free – Import Debug</h1>';
            $this->debug_started = true;
            flush();
        }

        /**
         * Emit a debug line to the browser when enabled.
         *
         * @param string $message
         * @param bool   $is_error
         * @return void
         */
        private function debug_echo($message, $is_error = false) {
            if (!$this->debug_enabled) {
                return;
            }

            $this->maybe_begin_debug_stream();

            $label = $is_error ? __('Error', 'cltd-migrate-free') : __('Step', 'cltd-migrate-free');
            printf('<p><span>%s:</span> %s</p>', esc_html($label), esc_html($message));
            flush();
        }

        /**
         * Close the debug HTML container.
         *
         * @return void
         */
        private function finalize_debug_stream() {
            if ($this->debug_enabled && $this->debug_started) {
                echo '</div></body></html>';
                $this->debug_started = false;
            }
        }

        /**
         * Add permissive CORS headers for migrated asset types.
         */
        public function maybe_add_cors_headers() {
            if (headers_sent()) {
                return;
            }

            $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) : '';
            if (!$path) {
                return;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!$extension) {
                return;
            }

            $cors_extensions = (array) apply_filters('cltd_migrate_free_cors_extensions', ['ttf', 'otf', 'eot', 'woff', 'woff2', 'svg', 'mp4', 'webm', 'ogg', 'css', 'js']);
            if (!in_array($extension, $cors_extensions, true)) {
                return;
            }

            header('Access-Control-Allow-Origin: *');
            header('Timing-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: *');
        }
    }

    CLTD_Migrate_Free::instance();
}

/**
 * Register CLTD block category in editor.
 *
 * @param array                 $categories Existing categories.
 * @param WP_Block_Editor_Context $editor_context Context.
 * @return array
 */
