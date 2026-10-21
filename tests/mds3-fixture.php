<?php
/**
 * Local workflow fixture for SEO Basic.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/mds-seo-basic/tests/mds3-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MDS_SEO_Basic') || empty($GLOBALS['mds_seo_basic'])) {
    fwrite(STDERR, "SEO Basic is not active.\n");
    exit(1);
}

$seo = $GLOBALS['mds_seo_basic'];
$missing = '__mds_seo_fixture_missing__';
$settings_before = get_option('mds_seo_basic_settings', $missing);
$query_before = $GLOBALS['wp_query'] ?? null;
$post_before = $GLOBALS['post'] ?? null;
$current_user_before = get_current_user_id();
$fixture_ids = [];
$failure = '';
$conflict_filter = static function ($plugins) {
    $plugins[] = 'Fixture SEO Suite';
    return $plugins;
};

try {
    // The package's own version must be one number: compare the constant with the
    // plugin header instead of a literal, so this still means something after a bump
    // and catches the bump that moved one file and not the other.
    $header_src = (string) file_get_contents(dirname(__DIR__) . '/mds-seo-basic.php');
    if (!preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m', $header_src, $header_version) || $header_version[1] !== MDS_SEO_BASIC_VERSION) {
        throw new RuntimeException('SEO Basic version metadata is stale: the plugin header and MDS_SEO_BASIC_VERSION disagree.');
    }

    wp_set_current_user(1);
    update_option('mds_seo_basic_settings', MDS_SEO_Basic::default_settings(), false);

    $sanitized = $seo->sanitize_settings([
        'metadata_enabled' => '1',
        'social_enabled' => ['invalid'],
        'unknown_setting' => '1',
    ]);
    if (true !== $sanitized['metadata_enabled'] || false !== $sanitized['social_enabled'] || array_key_exists('unknown_setting', $sanitized)) {
        throw new RuntimeException('SEO Basic settings were not normalized to known boolean fields.');
    }

    $seo->add_admin_menu();
    global $submenu;
    $menu_slugs = array_map(static function ($item) {
        return (string) ($item[2] ?? '');
    }, (array) ($submenu['mds3'] ?? []));
    if (!in_array('mds-seo-basic', $menu_slugs, true)) {
        throw new RuntimeException('SEO Basic did not register its extension submenu.');
    }

    $onboarding = apply_filters('million-dollar-script/extension/onboarding/items', []);
    if (empty($onboarding['mds-seo-basic']['actions']) || empty($onboarding['mds-seo-basic']['legal_documents'])) {
        throw new RuntimeException('SEO Basic onboarding is incomplete.');
    }

    $fixture_ids['post'] = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => 'SEO Fixture Post',
        'post_content' => 'A deterministic SEO fixture post.',
    ], true);
    $fixture_ids['page'] = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'SEO Fixture Page',
        'post_content' => 'A deterministic SEO fixture page.',
    ], true);
    $fixture_ids['grid_shortcode'] = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'SEO Fixture Shortcode Grid',
        'post_content' => '[mds3_page type="grid" grid_id="1"]',
    ], true);
    $fixture_ids['grid_block'] = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'SEO Fixture Block Grid',
        'post_content' => '<!-- wp:mds/grid {"gridId":1} /-->',
    ], true);

    foreach ($fixture_ids as $fixture_id) {
        if (is_wp_error($fixture_id)) {
            throw new RuntimeException('SEO fixture content could not be created.');
        }
    }

    $grid_id = (int) $fixture_ids['grid_shortcode'];
    update_post_meta($grid_id, '_mds_seo_title', 'Fixture Search Title');
    update_post_meta($grid_id, '_mds_seo_description', 'Fixture search and social description.');
    update_post_meta($grid_id, '_mds_seo_keywords', 'legacy, fixture');
    update_post_meta($grid_id, '_mds_seo_og_image', 'https://example.com/fixture.jpg');
    update_post_meta($grid_id, '_mds_seo_canonical_url', 'https://example.com/preferred-grid/');

    $query = new WP_Query();
    $query->is_singular = true;
    $query->is_page = true;
    $query->queried_object = get_post($grid_id);
    $query->queried_object_id = $grid_id;
    $query->posts = [$query->queried_object];
    $query->post = $query->queried_object;
    $query->post_count = 1;
    $GLOBALS['wp_query'] = $query;
    $GLOBALS['post'] = $query->queried_object;
    setup_postdata($GLOBALS['post']);

    delete_post_meta($grid_id, '_mds_seo_description');
    ob_start();
    $seo->output_meta_tags();
    $fallback_output = ob_get_clean();
    if (false === strpos($fallback_output, 'name="description"') || false === strpos($fallback_output, 'Explore SEO Fixture Shortcode Grid')) {
        throw new RuntimeException('Grid pages without custom SEO copy did not receive a useful fallback description.');
    }
    update_post_meta($grid_id, '_mds_seo_description', 'Fixture search and social description.');

    if ('Fixture Search Title' !== $seo->filter_document_title('Default title')) {
        throw new RuntimeException('Custom search titles do not use the WordPress title pipeline.');
    }
    if ('https://example.com/preferred-grid/' !== $seo->filter_canonical_url(get_permalink($grid_id), get_post($grid_id))) {
        throw new RuntimeException('Custom canonical URLs do not use the WordPress canonical pipeline.');
    }

    ob_start();
    $seo->output_meta_tags();
    $seo->output_social_cards();
    $seo->output_structured_data();
    $head_output = ob_get_clean();
    if (1 !== substr_count($head_output, 'name="description"') || 1 !== substr_count($head_output, 'property="og:title"')) {
        throw new RuntimeException('SEO Basic emitted missing or duplicate metadata.');
    }
    if (false !== strpos($head_output, 'name="robots"') || false !== strpos($head_output, 'rel="canonical"')) {
        throw new RuntimeException('SEO Basic bypassed WordPress robots or canonical filters.');
    }
    if (false === strpos($head_output, 'CollectionPage') || false === strpos($head_output, 'https://example.com/preferred-grid/')) {
        throw new RuntimeException('Grid structured data is incomplete.');
    }
    if (false !== strpos($head_output, 'name="keywords"')) {
        throw new RuntimeException('Legacy keywords were output while disabled.');
    }

    $settings = MDS_SEO_Basic::default_settings();
    $settings['keywords_enabled'] = true;
    update_option('mds_seo_basic_settings', $settings, false);
    ob_start();
    $seo->output_meta_tags();
    $keywords_output = ob_get_clean();
    if (false === strpos($keywords_output, 'name="keywords" content="legacy, fixture"')) {
        throw new RuntimeException('Legacy keywords compatibility output did not work when enabled.');
    }

    $grid_entries = $seo->sitemap()->entries('grids');
    $grid_entry_ids = array_map('intval', wp_list_pluck($grid_entries, 'id'));
    if (!in_array((int) $fixture_ids['grid_shortcode'], $grid_entry_ids, true) || !in_array((int) $fixture_ids['grid_block'], $grid_entry_ids, true)) {
        throw new RuntimeException('Grid sitemap discovery missed shortcode or block pages.');
    }

    update_post_meta((int) $fixture_ids['grid_block'], '_mds_seo_noindex', '1');
    $fresh_sitemap = new MDS_SEO_Basic_Sitemap([$seo, 'settings'], [$seo, 'output_allowed']);
    $grid_entry_ids = array_map('intval', wp_list_pluck($fresh_sitemap->entries('grids'), 'id'));
    if (in_array((int) $fixture_ids['grid_block'], $grid_entry_ids, true)) {
        throw new RuntimeException('Noindexed grid pages remained in the sitemap.');
    }
    $robots = $seo->filter_robots(['max-image-preview:large' => true]);
    if (!empty($robots['noindex'])) {
        throw new RuntimeException('A different page inherited the noindex setting.');
    }

    $GLOBALS['wp_query']->queried_object = get_post((int) $fixture_ids['grid_block']);
    $GLOBALS['wp_query']->queried_object_id = (int) $fixture_ids['grid_block'];
    $robots = $seo->filter_robots([]);
    if (empty($robots['noindex']) || empty($robots['follow']) || isset($robots['index'])) {
        throw new RuntimeException('Per-page noindex did not integrate with WordPress robots output.');
    }

    ob_start();
    $fresh_sitemap->render('index');
    $index_xml = ob_get_clean();
    if (false === strpos($index_xml, 'sitemap-posts.xml') || false === strpos($index_xml, 'sitemap-pages.xml') || false === strpos($index_xml, 'sitemap-grids.xml')) {
        throw new RuntimeException('The sitemap index omitted an enabled content section.');
    }
    ob_start();
    $fresh_sitemap->render('grids');
    $grid_xml = ob_get_clean();
    if (false === strpos($grid_xml, get_permalink((int) $fixture_ids['grid_shortcode'])) || false !== strpos($grid_xml, get_permalink((int) $fixture_ids['grid_block']))) {
        throw new RuntimeException('The grid sitemap did not honor public and noindex entries.');
    }

    $vars = $fresh_sitemap->query_vars([]);
    if (!in_array('mds_sitemap', $vars, true) || !in_array('mds_sitemap_page', $vars, true)) {
        throw new RuntimeException('Sitemap query variables were not registered.');
    }
    MDS_SEO_Basic_Sitemap::add_rewrite_rules();
    global $wp_rewrite;
    if (!isset($wp_rewrite->extra_rules_top['^sitemap\.xml$'])) {
        throw new RuntimeException('Sitemap rewrite rules were not registered for activation.');
    }

    add_filter('mds_seo_basic_detected_seo_plugins', $conflict_filter);
    if ($seo->output_allowed('metadata') || $seo->output_allowed('social') || $seo->output_allowed('schema') || $seo->output_allowed('sitemap')) {
        throw new RuntimeException('Known SEO plugin conflicts did not pause overlapping output.');
    }
    ob_start();
    $seo->output_meta_tags();
    $seo->output_social_cards();
    $seo->output_structured_data();
    $conflict_output = ob_get_clean();
    if ('' !== trim($conflict_output)) {
        throw new RuntimeException('SEO Basic emitted duplicate public metadata during a detected conflict.');
    }
    remove_filter('mds_seo_basic_detected_seo_plugins', $conflict_filter);

    $seo->admin_assets('million-dollar-script_page_mds-seo-basic');
    if (!wp_style_is('mds-seo-basic-admin', 'enqueued') || !wp_script_is('mds-seo-basic-admin', 'enqueued')) {
        throw new RuntimeException('SEO Basic admin assets were not enqueued.');
    }
    ob_start();
    $seo->admin_page();
    $admin_html = ob_get_clean();
    if (false === strpos($admin_html, 'Refresh sitemap routes') || false === strpos($admin_html, 'Structured data')) {
        throw new RuntimeException('SEO Basic settings do not expose the current workflow.');
    }
    if (false !== strpos($admin_html, '<script') || false !== strpos($admin_html, '<style')) {
        throw new RuntimeException('SEO Basic settings still contain inline scripts or styles.');
    }
} catch (Throwable $error) {
    $failure = $error->getMessage();
} finally {
    remove_filter('mds_seo_basic_detected_seo_plugins', $conflict_filter);
    wp_reset_postdata();
    foreach ($fixture_ids as $fixture_id) {
        if (!is_wp_error($fixture_id) && $fixture_id) {
            wp_delete_post((int) $fixture_id, true);
        }
    }
    if ($missing === $settings_before) {
        delete_option('mds_seo_basic_settings');
    } else {
        update_option('mds_seo_basic_settings', $settings_before, false);
    }
    $GLOBALS['wp_query'] = $query_before;
    $GLOBALS['post'] = $post_before;
    wp_set_current_user($current_user_before);
}

if ($failure) {
    fwrite(STDERR, 'SEO Basic fixture failed: ' . $failure . "\n");
    exit(1);
}

echo "Success: SEO Basic fixture passed.\n";
