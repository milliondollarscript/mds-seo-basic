<?php
/**
 * Plugin Name: Million Dollar Script - SEO Basic
 * Plugin URI: https://milliondollarscript.com/extensions/seo-basic
 * Description: Search metadata, social cards, structured data, and scalable sitemaps for Million Dollar Script sites.
 * Version: 1.1.0
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * License: GPL v2 or later
 * Text Domain: mds-seo-basic
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Requires Plugins: million-dollar-script
 * Requires MDS: 3.0.0
 * Requires MDS API: 1
 * MDS Compatible: MDS 3.0
 */

namespace MillionDollarScript\Extensions\SeoBasic;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

define('MDS_SEO_BASIC_VERSION', '1.1.0');
define('MDS_SEO_BASIC_PLUGIN_FILE', __FILE__);
define('MDS_SEO_BASIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDS_SEO_BASIC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MDS_SEO_BASIC_PLUGIN_DIR . 'includes/class-mds-seo-basic-sitemap.php';

/**
 * Main extension controller.
 */
class MDS_SEO_Basic {
    private const SETTINGS_OPTION = 'mds_seo_basic_settings';

    /** @var MDS_SEO_Basic_Sitemap */
    private $sitemap;

    public function __construct() {
        $this->sitemap = new MDS_SEO_Basic_Sitemap([$this, 'settings'], [$this, 'output_allowed']);

        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_mds_seo_save_settings', [$this, 'save_settings']);
        add_action('wp_head', [$this, 'output_meta_tags'], 2);
        add_action('wp_head', [$this, 'output_social_cards'], 3);
        add_action('wp_head', [$this, 'output_structured_data'], 4);
        add_filter('pre_get_document_title', [$this, 'filter_document_title']);
        add_filter('get_canonical_url', [$this, 'filter_canonical_url'], 10, 2);
        add_filter('wp_robots', [$this, 'filter_robots']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_data']);
        add_action('wp_ajax_mds_seo_generate_sitemap', [$this, 'ajax_generate_sitemap']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);

        $this->sitemap->register_hooks();
    }

    public static function default_settings() {
        return [
            'metadata_enabled' => true,
            'social_enabled' => true,
            'schema_enabled' => true,
            'sitemap_enabled' => true,
            'sitemap_posts' => true,
            'sitemap_pages' => true,
            'sitemap_grids' => true,
            'sitemap_advertisers' => true,
            'keywords_enabled' => false,
            'allow_parallel_output' => false,
        ];
    }

    public function settings() {
        $saved = get_option(self::SETTINGS_OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $settings = self::default_settings();
        foreach (array_keys($settings) as $key) {
            if (array_key_exists($key, $saved)) {
                $settings[$key] = $this->normalize_boolean($saved[$key]);
            }
        }

        return $settings;
    }

    private function setting($key) {
        $settings = $this->settings();

        return !empty($settings[$key]);
    }

    public function init() {
        load_plugin_textdomain('mds-seo-basic', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function extension_onboarding_items($items) {
        $items['mds-seo-basic'] = [
            'name' => __('SEO Basic', 'mds-seo-basic'),
            'summary' => __('Review search metadata, social sharing, sitemap visibility, and public-content responsibilities before publishing.', 'mds-seo-basic'),
            'priority' => 70,
            'actions' => [
                [
                    'label' => __('Open SEO Basic settings', 'mds-seo-basic'),
                    'url' => admin_url('admin.php?page=mds-seo-basic'),
                    'primary' => true,
                ],
            ],
            'legal_documents' => [
                [
                    'slug' => 'search-social-metadata-terms',
                    'title' => __('Search and Social Metadata Terms', 'mds-seo-basic'),
                    'description' => __('Terms covering custom metadata, social cards, sitemap output, indexing expectations, and public-content responsibility.', 'mds-seo-basic'),
                    'page_slug' => 'search-social-metadata-terms',
                    'content' => $this->seo_legal_content(),
                ],
            ],
        ];

        return $items;
    }

    private function seo_legal_content() {
        return '<h2>' . esc_html__('Search and Social Metadata Terms', 'mds-seo-basic') . '</h2>'
            . '<p>' . esc_html__('These terms apply when {{site_name}} generates or customizes search metadata, social sharing cards, robots directives, structured data, and sitemap output for pages on {{site_url}}.', 'mds-seo-basic') . '</p>'
            . '<h3>' . esc_html__('Metadata and Public Content Responsibility', 'mds-seo-basic') . '</h3>'
            . '<p>' . esc_html__('Administrators and content submitters are responsible for SEO titles, descriptions, social images, public page copy, sponsor claims, regulated statements, trademarks, copyright permissions, accessibility text, and required disclosures. Metadata may appear in search engines, social platforms, browser tools, previews, and third-party caches.', 'mds-seo-basic') . '</p>'
            . '<h3>' . esc_html__('Indexing and Ranking Limits', 'mds-seo-basic') . '</h3>'
            . '<p>' . esc_html__('Metadata and sitemaps do not guarantee indexing, ranking, traffic, conversions, preview behavior, cache refresh timing, or removal from third-party indexes. Search engines and social platforms operate under their own crawling, ranking, caching, removal, and policy systems.', 'mds-seo-basic') . '</p>'
            . '<h3>' . esc_html__('Changes, Removals, and Requests', 'mds-seo-basic') . '</h3>'
            . '<p>' . esc_html__('{{site_name}} may update, remove, reclassify, or hide metadata and pages that are inaccurate, unlawful, misleading, infringing, unsafe, abusive, spammy, or inconsistent with site rules. Correction, removal, takedown, privacy, and support requests can be sent through {{contact_method}}.', 'mds-seo-basic') . '</p>';
    }

    public function add_admin_menu() {
        add_submenu_page(
            $this->admin_parent_slug(),
            __('SEO Basic', 'mds-seo-basic'),
            __('SEO Basic', 'mds-seo-basic'),
            'manage_options',
            'mds-seo-basic',
            [$this, 'admin_page']
        );
    }

    private function admin_parent_slug() {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_parent_slug('milliondollarscript');
        }

        return class_exists('\MillionDollarScript\Core\Runtime') || class_exists('\\MillionDollarScript\\Core\\Runtime') ? 'mds3' : 'milliondollarscript';
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_settings = false !== strpos((string) $hook, 'mds-seo-basic');
        $is_editor = $screen && in_array($screen->base, ['post', 'post-new'], true)
            && in_array($screen->post_type, $this->supported_post_types(), true);

        if (!$is_settings && !$is_editor) {
            return;
        }

        if (class_exists('\MillionDollarScript\Core\Runtime')) {
            wp_enqueue_style('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/') . 'css/admin.css', [], \MillionDollarScript\Core\Runtime::version());
        }

        wp_enqueue_style('mds-seo-basic-admin', MDS_SEO_BASIC_PLUGIN_URL . 'assets/css/admin.css', [], MDS_SEO_BASIC_VERSION);
        wp_enqueue_script('mds-seo-basic-admin', MDS_SEO_BASIC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MDS_SEO_BASIC_VERSION, true);
        wp_localize_script('mds-seo-basic-admin', 'mdsSeoBasic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mds_seo_nonce'),
            'settingsPage' => $is_settings,
            'strings' => [
                'checking' => __('Refreshing sitemap routes...', 'mds-seo-basic'),
                'failed' => __('Sitemap routes could not be refreshed.', 'mds-seo-basic'),
                'chooseImage' => __('Choose social sharing image', 'mds-seo-basic'),
                'useImage' => __('Use this image', 'mds-seo-basic'),
            ],
        ]);

        if ($is_editor) {
            wp_enqueue_media();
        }
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change SEO settings.', 'mds-seo-basic'), 403);
        }

        check_admin_referer('mds_seo_save_settings');

        $posted = isset($_POST['settings']) && is_array($_POST['settings'])
            ? wp_unslash($_POST['settings'])
            : [];
        $settings = $this->sanitize_settings($posted);

        update_option(self::SETTINGS_OPTION, $settings, false);
        MDS_SEO_Basic_Sitemap::add_rewrite_rules();
        flush_rewrite_rules(false);

        wp_safe_redirect(add_query_arg(['page' => 'mds-seo-basic', 'settings-updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : [];
        $settings = [];
        foreach (array_keys(self::default_settings()) as $key) {
            $settings[$key] = array_key_exists($key, $input) && $this->normalize_boolean($input[$key]);
        }

        return $settings;
    }

    private function normalize_boolean($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function supported_post_types() {
        $post_types = ['post', 'page'];
        if (post_type_exists('mds3_grid_page')) {
            $post_types[] = 'mds3_grid_page';
        }
        if (post_type_exists('mds_advertiser')) {
            $post_types[] = 'mds_advertiser';
        }

        return $post_types;
    }

    public function add_meta_boxes() {
        foreach ($this->supported_post_types() as $post_type) {
            add_meta_box(
                'mds_seo_meta',
                __('Search and Social Preview', 'mds-seo-basic'),
                [$this, 'meta_box_callback'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function meta_box_callback($post) {
        wp_nonce_field('mds_seo_meta_nonce', 'mds_seo_meta_nonce');

        $metadata = [
            'title' => (string) get_post_meta($post->ID, '_mds_seo_title', true),
            'description' => (string) get_post_meta($post->ID, '_mds_seo_description', true),
            'keywords' => (string) get_post_meta($post->ID, '_mds_seo_keywords', true),
            'image' => (string) get_post_meta($post->ID, '_mds_seo_og_image', true),
            'canonical' => (string) get_post_meta($post->ID, '_mds_seo_canonical_url', true),
            'noindex' => (bool) get_post_meta($post->ID, '_mds_seo_noindex', true),
        ];
        $keywords_enabled = $this->setting('keywords_enabled');

        require MDS_SEO_BASIC_PLUGIN_DIR . 'views/meta-box.php';
    }

    public function save_meta_data($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!isset($_POST['mds_seo_meta_nonce']) || !is_scalar($_POST['mds_seo_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['mds_seo_meta_nonce']));
        if (!wp_verify_nonce($nonce, 'mds_seo_meta_nonce') || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'mds_seo_title' => 'sanitize_text_field',
            'mds_seo_description' => 'sanitize_textarea_field',
            'mds_seo_keywords' => 'sanitize_text_field',
            'mds_seo_og_image' => 'esc_url_raw',
            'mds_seo_canonical_url' => 'esc_url_raw',
        ];

        foreach ($fields as $field => $sanitizer) {
            $value = isset($_POST[$field]) && is_scalar($_POST[$field])
                ? call_user_func($sanitizer, wp_unslash($_POST[$field]))
                : '';

            if ('' === $value) {
                delete_post_meta($post_id, '_' . $field);
            } else {
                update_post_meta($post_id, '_' . $field, $value);
            }
        }

        if (!empty($_POST['mds_seo_noindex'])) {
            update_post_meta($post_id, '_mds_seo_noindex', '1');
        } else {
            delete_post_meta($post_id, '_mds_seo_noindex');
        }
    }

    public function filter_document_title($title) {
        if (!$this->output_allowed('metadata') || !is_singular()) {
            return $title;
        }

        $post_id = get_queried_object_id();
        $meta_title = $post_id ? (string) get_post_meta($post_id, '_mds_seo_title', true) : '';

        return '' !== $meta_title ? wp_strip_all_tags($meta_title) : $title;
    }

    public function filter_canonical_url($canonical_url, $post) {
        if (!$this->output_allowed('metadata') || !($post instanceof WP_Post)) {
            return $canonical_url;
        }

        $custom_url = (string) get_post_meta($post->ID, '_mds_seo_canonical_url', true);

        return '' !== $custom_url ? esc_url_raw($custom_url) : $canonical_url;
    }

    public function filter_robots($robots) {
        if (!$this->output_allowed('metadata') || !is_singular()) {
            return $robots;
        }

        $post_id = get_queried_object_id();
        if ($post_id && get_post_meta($post_id, '_mds_seo_noindex', true)) {
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow'] = true;
        }

        return $robots;
    }

    public function output_meta_tags() {
        if (!$this->output_allowed('metadata') || !is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $post = get_post($post_id);
        $description = $post instanceof WP_Post ? $this->description_for_post($post) : '';
        if ('' !== $description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        if ($this->setting('keywords_enabled')) {
            $keywords = (string) get_post_meta($post_id, '_mds_seo_keywords', true);
            if ('' !== $keywords) {
                echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
            }
        }
    }

    public function output_social_cards() {
        if (!$this->output_allowed('social') || !is_singular()) {
            return;
        }

        $post = get_post(get_queried_object_id());
        if (!$post instanceof WP_Post) {
            return;
        }

        $metadata = $this->social_metadata($post);
        $type = 'post' === $post->post_type ? 'article' : 'website';

        echo '<meta property="og:title" content="' . esc_attr($metadata['title']) . '">' . "\n";
        if ('' !== $metadata['description']) {
            echo '<meta property="og:description" content="' . esc_attr($metadata['description']) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url($metadata['url']) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        if ('' !== $metadata['image']) {
            echo '<meta property="og:image" content="' . esc_url($metadata['image']) . '">' . "\n";
        }

        echo '<meta name="twitter:card" content="' . ('' !== $metadata['image'] ? 'summary_large_image' : 'summary') . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($metadata['title']) . '">' . "\n";
        if ('' !== $metadata['description']) {
            echo '<meta name="twitter:description" content="' . esc_attr($metadata['description']) . '">' . "\n";
        }
        if ('' !== $metadata['image']) {
            echo '<meta name="twitter:image" content="' . esc_url($metadata['image']) . '">' . "\n";
        }
    }

    public function output_structured_data() {
        if (!$this->output_allowed('schema')) {
            return;
        }

        $schema = null;
        if (is_front_page()) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'description' => get_bloginfo('description'),
            ];
        } elseif (is_singular()) {
            $post = get_post(get_queried_object_id());
            if ($post instanceof WP_Post) {
                $metadata = $this->social_metadata($post);
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => $this->sitemap->is_grid_post($post) ? 'CollectionPage' : ('post' === $post->post_type ? 'Article' : 'WebPage'),
                    'name' => $metadata['title'],
                    'url' => $metadata['url'],
                    'datePublished' => get_post_time(DATE_W3C, true, $post),
                    'dateModified' => get_post_modified_time(DATE_W3C, true, $post),
                ];
                if ('' !== $metadata['description']) {
                    $schema['description'] = $metadata['description'];
                }
                if ('' !== $metadata['image']) {
                    $schema['image'] = $metadata['image'];
                }
                if ('post' === $post->post_type) {
                    $schema['author'] = [
                        '@type' => 'Person',
                        'name' => get_the_author_meta('display_name', (int) $post->post_author),
                    ];
                }
            }
        }

        $schema = \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/seo-basic/schema', ['mds_seo_basic_schema'], $schema);
        if (is_array($schema) && !empty($schema['@type'])) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        }
    }

    private function social_metadata(WP_Post $post) {
        $title = (string) get_post_meta($post->ID, '_mds_seo_title', true);
        if ('' === $title) {
            $title = get_the_title($post);
        }

        $description = $this->description_for_post($post);

        $image = (string) get_post_meta($post->ID, '_mds_seo_og_image', true);
        if ('' === $image && has_post_thumbnail($post)) {
            $image = (string) get_the_post_thumbnail_url($post, 'large');
        }

        if ('mds_advertiser' === $post->post_type && class_exists('MillionDollarScript\\Media\\Placements')) {
            $placement_id = absint(get_post_meta($post->ID, '_million_dollar_script_placement_id', true));
            $view = \MillionDollarScript\Media\Placements::public_view($placement_id);
            if (is_array($view)) {
                if ('' === trim($description)) {
                    $description = wp_strip_all_tags((string) ($view['popup_text_html'] ?? ''));
                }
                if ('' === $image) {
                    $image = (string) ($view['image']['url'] ?? '');
                }
            }
        }

        $canonical = (string) get_post_meta($post->ID, '_mds_seo_canonical_url', true);

        return [
            'title' => wp_strip_all_tags($title),
            'description' => wp_strip_all_tags($description),
            'image' => esc_url_raw($image),
            'url' => '' !== $canonical ? esc_url_raw($canonical) : get_permalink($post),
        ];
    }

    private function description_for_post(WP_Post $post) {
        $description = (string) get_post_meta($post->ID, '_mds_seo_description', true);
        if ('' === trim($description)) {
            $description = (string) $post->post_excerpt;
        }
        if ('' === trim(wp_strip_all_tags($description))) {
            $description = strip_shortcodes((string) $post->post_content);
        }
        if ('' === trim(wp_strip_all_tags($description)) && $this->sitemap->is_grid_post($post)) {
            $description = $this->grid_description($post);
        }
        if ('' === trim(wp_strip_all_tags($description)) && 'mds_advertiser' === $post->post_type && class_exists('MillionDollarScript\\Media\\Placements')) {
            $placement_id = absint(get_post_meta($post->ID, '_million_dollar_script_placement_id', true));
            $view = \MillionDollarScript\Media\Placements::public_view($placement_id);
            if (is_array($view)) {
                $description = (string) ($view['popup_text_html'] ?? '');
            }
        }
        if ('' === trim(wp_strip_all_tags($description))) {
            $description = (string) get_bloginfo('description');
        }

        $description = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($description)) ?? '');

        return function_exists('wp_html_excerpt') ? wp_html_excerpt($description, 160, '...') : $description;
    }

    private function grid_description(WP_Post $post) {
        $grid_id = absint(get_post_meta($post->ID, '_mds3_grid_id', true));
        $grid = $grid_id && class_exists('\MillionDollarScript\Core\Grids')
            ? \MillionDollarScript\Core\Grids::find($grid_id)
            : null;

        if ($grid) {
            $description = trim((string) $grid->get('description', ''));
            if ('' !== $description) {
                return $description;
            }

            $title = trim((string) $grid->get('title', ''));
            $width = absint($grid->get('width', 0));
            $height = absint($grid->get('height', 0));
            if ('' !== $title && $width && $height) {
                return sprintf(
                    /* translators: 1: grid title, 2: grid width, 3: grid height, 4: site name. */
                    __('Explore %1$s, a %2$s by %3$s pixel advertising grid on %4$s.', 'mds-seo-basic'),
                    $title,
                    number_format_i18n($width),
                    number_format_i18n($height),
                    get_bloginfo('name')
                );
            }
        }

        return sprintf(
            /* translators: 1: page title, 2: site name. */
            __('Explore %1$s on %2$s.', 'mds-seo-basic'),
            get_the_title($post),
            get_bloginfo('name')
        );
    }

    public function output_allowed($area) {
        $setting_keys = [
            'metadata' => 'metadata_enabled',
            'social' => 'social_enabled',
            'schema' => 'schema_enabled',
            'sitemap' => 'sitemap_enabled',
        ];
        $setting_key = $setting_keys[$area] ?? '';
        $allowed = '' !== $setting_key && $this->setting($setting_key);

        if ($allowed && !$this->setting('allow_parallel_output') && !empty($this->detected_seo_plugins())) {
            $allowed = false;
        }

        return (bool) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/seo-basic/output/allowed', ['mds_seo_basic_output_allowed'], $allowed, $area, $this->settings());
    }

    public function detected_seo_plugins() {
        $plugins = [];

        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            $plugins[] = 'Yoast SEO';
        }
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            $plugins[] = 'Rank Math SEO';
        }
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
            $plugins[] = 'All in One SEO';
        }
        if (defined('SEOPRESS_VERSION') || defined('SEOPRESS_PRO_VERSION')) {
            $plugins[] = 'SEOPress';
        }
        if (defined('THE_SEO_FRAMEWORK_VERSION') || function_exists('the_seo_framework')) {
            $plugins[] = 'The SEO Framework';
        }

        return array_values(array_unique((array) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/seo-basic/detected/seo/plugins', ['mds_seo_basic_detected_seo_plugins'], $plugins)));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'mds-seo-basic'));
        }

        $settings = $this->settings();
        $conflicts = $this->detected_seo_plugins();
        $sitemap_url = home_url('/sitemap.xml');
        $sitemap_enabled = $this->output_allowed('sitemap');

        require MDS_SEO_BASIC_PLUGIN_DIR . 'views/admin-page.php';
    }

    public function ajax_generate_sitemap() {
        check_ajax_referer('mds_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to refresh sitemap routes.', 'mds-seo-basic')], 403);
        }

        MDS_SEO_Basic_Sitemap::add_rewrite_rules();
        flush_rewrite_rules(false);

        wp_send_json_success([
            'message' => __('Sitemap routes refreshed. Open the sitemap to verify its current entries.', 'mds-seo-basic'),
            'sitemapUrl' => home_url('/sitemap.xml'),
        ]);
    }

    public function sitemap() {
        return $this->sitemap;
    }
}

if (!class_exists('MDS_SEO_Basic', false)) {
    class_alias(MDS_SEO_Basic::class, 'MDS_SEO_Basic');
}
if (!class_exists('MDS_SEO_Basic_Sitemap', false)) {
    class_alias(MDS_SEO_Basic_Sitemap::class, 'MDS_SEO_Basic_Sitemap');
}

$GLOBALS['mds_seo_basic'] = new MDS_SEO_Basic();

register_activation_hook(__FILE__, function() {
    MDS_SEO_Basic_Sitemap::add_rewrite_rules();
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules(false);
});

add_action('million-dollar-script/register/extensions', static function(): void {
    if (!class_exists('\\MillionDollarScript\\Extensions\\Registry')) {
        return;
    }
    \MillionDollarScript\Extensions\Registry::register([
        'id' => 'mds-seo-basic',
        'name' => 'SEO Basic',
        'description' => 'Add search metadata, social cards, structured data, and scalable sitemaps.',
        'version' => MDS_SEO_BASIC_VERSION,
        'main_file' => __FILE__,
        'premium' => false,
        'license_required' => false,
        'cleanup' => [
            'description' => 'SEO settings and Million Dollar Script SEO post metadata.',
        ],
    ]);
});
