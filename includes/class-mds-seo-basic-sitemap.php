<?php

namespace MillionDollarScript\Extensions\SeoBasic;

use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic sitemap routing and rendering.
 */
class MDS_SEO_Basic_Sitemap {
    private const URLS_PER_PAGE = 1000;

    /** @var callable */
    private $settings_callback;

    /** @var callable */
    private $output_allowed_callback;

    /** @var WP_Post[]|null */
    private $grid_posts;

    public function __construct($settings_callback, $output_allowed_callback) {
        $this->settings_callback = $settings_callback;
        $this->output_allowed_callback = $output_allowed_callback;
    }

    public function register_hooks() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('parse_request', [$this, 'maybe_serve_request']);
        add_action('template_redirect', [$this, 'serve_query']);
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?mds_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-(posts|pages|grids|advertisers)(?:-([0-9]+))?\.xml$', 'index.php?mds_sitemap=$matches[1]&mds_sitemap_page=$matches[2]', 'top');
    }

    public function register_rewrite_rules() {
        self::add_rewrite_rules();
    }

    public function query_vars($vars) {
        $vars[] = 'mds_sitemap';
        $vars[] = 'mds_sitemap_page';

        return array_values(array_unique($vars));
    }

    public function maybe_serve_request($wp) {
        if (is_admin() || !$this->output_allowed()) {
            return;
        }

        $request = trim((string) ($wp->request ?? ''), '/');
        if ('' === $request) {
            $request_uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
                ? wp_unslash($_SERVER['REQUEST_URI'])
                : '';
            $request = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        }

        if ('sitemap.xml' === $request) {
            $this->render('index', 1);
            exit;
        }

        if (preg_match('/^sitemap-(posts|pages|grids|advertisers)(?:-([0-9]+))?\.xml$/', $request, $matches)) {
            $this->render($matches[1], max(1, absint($matches[2] ?? 1)));
            exit;
        }
    }

    public function serve_query() {
        if (!$this->output_allowed()) {
            return;
        }

        $type = sanitize_key((string) get_query_var('mds_sitemap'));
        if ('' === $type) {
            return;
        }

        $this->render($type, max(1, absint(get_query_var('mds_sitemap_page'))));
        exit;
    }

    public function render($type = 'index', $page = 1) {
        $type = '1' === (string) $type ? 'index' : sanitize_key((string) $type);
        $page = max(1, absint($page));
        $allowed = ['index', 'posts', 'pages', 'grids', 'advertisers'];

        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');

        if (!in_array($type, $allowed, true) || ('index' !== $type && !$this->section_enabled($type))) {
            status_header(404);
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<error>' . $this->xml(__('Sitemap not found.', 'mds-seo-basic')) . '</error>' . "\n";
            return;
        }

        status_header(200);
        if ('index' === $type) {
            $this->render_index();
            return;
        }

        $entries = $this->entries($type, $page);
        if (empty($entries) && $page > 1) {
            status_header(404);
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<error>' . $this->xml(__('Sitemap page not found.', 'mds-seo-basic')) . '</error>' . "\n";
            return;
        }

        $this->render_urlset($entries);
    }

    private function render_index() {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (['posts', 'pages', 'grids', 'advertisers'] as $type) {
            if (!$this->section_enabled($type)) {
                continue;
            }

            $count = $this->entry_count($type);
            if ($count < 1) {
                continue;
            }

            $pages = (int) ceil($count / self::URLS_PER_PAGE);
            for ($page = 1; $page <= $pages; $page++) {
                $entries = $this->entries($type, $page);
                if (empty($entries)) {
                    continue;
                }

                $path = 1 === $page ? "/sitemap-{$type}.xml" : "/sitemap-{$type}-{$page}.xml";
                echo '<sitemap><loc>' . $this->xml(home_url($path)) . '</loc>';
                echo '<lastmod>' . $this->xml($entries[0]['modified']) . '</lastmod></sitemap>' . "\n";
            }
        }

        echo '</sitemapindex>' . "\n";
    }

    private function render_urlset(array $entries) {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            echo '<url>' . "\n";
            echo '<loc>' . $this->xml($entry['url']) . '</loc>' . "\n";
            echo '<lastmod>' . $this->xml($entry['modified']) . '</lastmod>' . "\n";
            if (!empty($entry['changefreq']) && in_array($entry['changefreq'], ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], true)) {
                echo '<changefreq>' . $this->xml($entry['changefreq']) . '</changefreq>' . "\n";
            }
            if (isset($entry['priority']) && preg_match('/^(?:0(?:\.\d)?|1(?:\.0)?)$/', (string) $entry['priority'])) {
                echo '<priority>' . $this->xml($entry['priority']) . '</priority>' . "\n";
            }
            echo '</url>' . "\n";
        }
        echo '</urlset>' . "\n";
    }

    public function entries($type, $page = 1) {
        $type = sanitize_key((string) $type);
        $page = max(1, absint($page));

        if ('grids' === $type) {
            $offset = ($page - 1) * self::URLS_PER_PAGE;
            $posts = array_slice($this->grid_posts(), $offset, self::URLS_PER_PAGE);
        } elseif (in_array($type, ['posts', 'pages', 'advertisers'], true)) {
            $post_type = 'posts' === $type ? 'post' : ('pages' === $type ? 'page' : 'mds_advertiser');
            $args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => self::URLS_PER_PAGE,
                'paged' => $page,
                'orderby' => ['modified' => 'DESC', 'ID' => 'DESC'],
                'no_found_rows' => true,
                'suppress_filters' => false,
                'meta_query' => $this->indexable_meta_query(),
            ];
            $args = \MillionDollarScript\Core\Hooks::apply_compat(
                'million-dollar-script/extensions/seo-basic/sitemap/query/args',
                ['mds_seo_basic_sitemap_query_args'],
                $args,
                $type
            );
            $posts = get_posts(is_array($args) ? $args : []);
        } else {
            return [];
        }

        $entries = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post || get_post_meta($post->ID, '_mds_seo_noindex', true)) {
                continue;
            }

            $url = get_permalink($post);
            if (!$url) {
                continue;
            }

            $modified = $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt
                ? $post->post_modified_gmt
                : $post->post_modified;
            $entries[] = [
                'id' => (int) $post->ID,
                'url' => $url,
                'modified' => mysql2date(DATE_W3C, $modified, false),
            ];
        }

        $entries = \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/seo-basic/sitemap/entries',
            ['mds_seo_basic_sitemap_entries'],
            $entries,
            $type,
            $page
        );

        return is_array($entries) ? array_values($entries) : [];
    }

    public function entry_count($type) {
        if ('grids' === $type) {
            $count = count($this->grid_posts());
            return max(0, (int) \MillionDollarScript\Core\Hooks::apply_compat(
                'million-dollar-script/extensions/seo-basic/sitemap/count',
                ['mds_seo_basic_sitemap_count'],
                $count,
                $type
            ));
        }

        $post_type = 'posts' === $type ? 'post' : ('pages' === $type ? 'page' : ('advertisers' === $type ? 'mds_advertiser' : ''));
        if ('' === $post_type) {
            return 0;
        }

        $args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'suppress_filters' => false,
            'meta_query' => $this->indexable_meta_query(),
        ];
        $args = \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/seo-basic/sitemap/query/args',
            ['mds_seo_basic_sitemap_query_args'],
            $args,
            $type
        );
        $query = new WP_Query(is_array($args) ? $args : []);

        return max(0, (int) \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/seo-basic/sitemap/count',
            ['mds_seo_basic_sitemap_count'],
            (int) $query->found_posts,
            $type
        ));
    }

    private function grid_posts() {
        if (null !== $this->grid_posts) {
            return $this->grid_posts;
        }

        $candidate_ids = $this->grid_candidate_ids();

        $posts = [];
        foreach (array_map('absint', $candidate_ids) as $post_id) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post && $this->is_grid_post($post) && !get_post_meta($post->ID, '_mds_seo_noindex', true)) {
                $posts[] = $post;
            }
        }

        $posts = \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/seo-basic/sitemap/grid/posts',
            ['mds_seo_basic_sitemap_grid_posts'],
            $posts
        );
        $this->grid_posts = is_array($posts) ? array_values($posts) : [];

        return $this->grid_posts;
    }

    private function grid_candidate_ids() {
        global $wpdb;

        $tokens = [
            '[mds_grid',
            '[pixel_grid',
            '[mds3_page',
            '[milliondollarscript',
            '[million_dollar_script',
            '[mds ',
            '[mds]',
            '[mds_display',
            '<!-- wp:mds/grid',
            '<!-- wp:mds/page',
        ];
        $content_conditions = [];
        $arguments = ['publish', 'mds3_grid_page', 'page', '_mds3_page_type', 'grid'];
        foreach ($tokens as $token) {
            $content_conditions[] = 'p.post_content LIKE %s';
            $arguments[] = '%' . $wpdb->esc_like($token) . '%';
        }

        $sql = "SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} page_type
                ON page_type.post_id = p.ID AND page_type.meta_key = %s
            WHERE p.post_status = %s
                AND (
                    p.post_type = %s
                    OR (
                        p.post_type = %s
                        AND (page_type.meta_value = %s OR " . implode(' OR ', $content_conditions) . ")
                    )
                )
            ORDER BY p.post_modified_gmt DESC, p.ID DESC";

        // The meta key belongs in the JOIN placeholder before the WHERE placeholders.
        $prepared_arguments = array_merge([$arguments[3]], array_slice($arguments, 0, 3), array_slice($arguments, 4));
        $prepared = $wpdb->prepare($sql, $prepared_arguments);

        return array_map('absint', (array) $wpdb->get_col($prepared));
    }

    public function is_grid_post($post) {
        if (!$post instanceof WP_Post) {
            return false;
        }

        if ('mds3_grid_page' === $post->post_type) {
            return true;
        }

        if ('grid' === sanitize_key((string) get_post_meta($post->ID, '_mds3_page_type', true))) {
            return true;
        }

        $content = (string) $post->post_content;
        if (has_shortcode($content, 'mds_grid') || has_shortcode($content, 'pixel_grid')) {
            return true;
        }

        if ($this->content_has_grid_page_shortcode($content)) {
            return true;
        }

        return function_exists('parse_blocks') && $this->blocks_include_grid(parse_blocks($content));
    }

    private function content_has_grid_page_shortcode($content) {
        $tags = ['mds3_page', 'milliondollarscript', 'million_dollar_script', 'mds', 'mds_display'];
        $pattern = get_shortcode_regex($tags);
        if (!preg_match_all('/' . $pattern . '/s', (string) $content, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $atts = shortcode_parse_atts((string) ($match[3] ?? ''));
            $atts = is_array($atts) ? $atts : [];
            $type = sanitize_key((string) ($atts['type'] ?? $atts['display'] ?? $atts['display_type'] ?? $atts['milliondollarscript_type'] ?? $atts['mds_type'] ?? 'grid'));
            if ('' === $type || 'grid' === $type) {
                return true;
            }
        }

        return false;
    }

    private function blocks_include_grid(array $blocks) {
        foreach ($blocks as $block) {
            $name = (string) ($block['blockName'] ?? '');
            if ('mds/grid' === $name) {
                return true;
            }

            if ('mds/page' === $name) {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $type = sanitize_key((string) ($attrs['type'] ?? 'grid'));
                if ('' === $type || 'grid' === $type) {
                    return true;
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks']) && $this->blocks_include_grid($block['innerBlocks'])) {
                return true;
            }
        }

        return false;
    }

    private function section_enabled($type) {
        $settings = call_user_func($this->settings_callback);
        $key = 'sitemap_' . sanitize_key((string) $type);

        $enabled = !empty($settings[$key]);
        if ('advertisers' === $type) {
            $core_settings = get_option('mds3_settings', []);
            $core_settings = is_array($core_settings) ? $core_settings : [];
            $enabled = $enabled
                && 'yes' === strtolower((string) ($core_settings['mds-pixel-template'] ?? 'no'))
                && 'yes' !== strtolower((string) ($core_settings['exclude-from-search'] ?? 'no'));
        }

        return (bool) \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/seo-basic/sitemap/section/enabled',
            ['mds_seo_basic_sitemap_section_enabled'],
            $enabled,
            $type,
            $settings
        );
    }

    private function indexable_meta_query() {
        return [
            'relation' => 'OR',
            [
                'key' => '_mds_seo_noindex',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_mds_seo_noindex',
                'value' => '1',
                'compare' => '!=',
            ],
        ];
    }

    private function output_allowed() {
        return (bool) call_user_func($this->output_allowed_callback, 'sitemap');
    }

    private function xml($value) {
        return function_exists('esc_xml') ? esc_xml((string) $value) : esc_html((string) $value);
    }
}
