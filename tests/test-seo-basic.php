<?php
/**
 * PHPUnit coverage for SEO Basic.
 */
class MDS_SEO_Basic_Tests extends WP_UnitTestCase {
    private $extension;
    private $post_id;

    public function setUp(): void {
        parent::setUp();
        $this->extension = new MDS_SEO_Basic();
        update_option('mds_seo_basic_settings', MDS_SEO_Basic::default_settings(), false);
        $this->post_id = self::factory()->post->create([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Fixture Grid',
            'post_content' => '[mds3_page type="grid" grid_id="1"]',
        ]);
    }

    public function tearDown(): void {
        wp_delete_post($this->post_id, true);
        delete_option('mds_seo_basic_settings');
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_version_and_hooks_are_current(): void {
        // The package must describe itself with one version. A pinned literal breaks on
        // every release and misses the failure that matters - a bump that updates the
        // plugin header and leaves MDS_SEO_BASIC_VERSION (which the assets and the
        // self-check payload use) on the previous number.
        $header = $this->plugin_header_version(dirname(__DIR__) . '/mds-seo-basic.php');
        $this->assertNotFalse($header, 'the plugin header version could not be read');
        $this->assertSame($header, MDS_SEO_BASIC_VERSION, 'plugin header and version constant disagree');
        $this->assertNotFalse(has_filter('pre_get_document_title', [$this->extension, 'filter_document_title']));
        $this->assertNotFalse(has_filter('get_canonical_url', [$this->extension, 'filter_canonical_url']));
        $this->assertNotFalse(has_filter('wp_robots', [$this->extension, 'filter_robots']));
    }

    /** The Version: line of a plugin file header. */
    private function plugin_header_version(string $file)
    {
        $src = (string) file_get_contents($file);
        return preg_match('/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m', $src, $m) ? $m[1] : false;
    }

    public function test_settings_sanitizer_only_accepts_known_boolean_fields(): void {
        $settings = $this->extension->sanitize_settings([
            'metadata_enabled' => 'yes',
            'unknown' => '1',
        ]);

        $this->assertTrue($settings['metadata_enabled']);
        $this->assertFalse($settings['social_enabled']);
        $this->assertArrayNotHasKey('unknown', $settings);
        $this->assertArrayHasKey('sitemap_advertisers', MDS_SEO_Basic::default_settings());
    }

    public function test_advertiser_sitemap_respects_core_visibility_and_indexing(): void {
        register_post_type('mds_advertiser', ['public' => true]);
        $advertiser_id = self::factory()->post->create([
            'post_type' => 'mds_advertiser',
            'post_status' => 'publish',
            'post_title' => 'Fixture advertiser',
        ]);
        update_option('mds3_settings', [
            'mds-pixel-template' => 'yes',
            'exclude-from-search' => 'no',
        ], false);

        $entries = $this->extension->sitemap()->entries('advertisers');
        $this->assertContains($advertiser_id, wp_list_pluck($entries, 'id'));

        $enabled = new ReflectionMethod($this->extension->sitemap(), 'section_enabled');
        $enabled->setAccessible(true);
        $this->assertTrue($enabled->invoke($this->extension->sitemap(), 'advertisers'));

        update_option('mds3_settings', [
            'mds-pixel-template' => 'yes',
            'exclude-from-search' => 'yes',
        ], false);
        $this->assertFalse($enabled->invoke($this->extension->sitemap(), 'advertisers'));

        wp_delete_post($advertiser_id, true);
        delete_option('mds3_settings');
        unregister_post_type('mds_advertiser');
    }

    public function test_grid_sitemap_detects_grid_pages_and_excludes_noindex_content(): void {
        $entries = $this->extension->sitemap()->entries('grids');
        $this->assertContains($this->post_id, wp_list_pluck($entries, 'id'));

        update_post_meta($this->post_id, '_mds_seo_noindex', '1');
        $entries = $this->extension->sitemap()->entries('grids');
        $this->assertNotContains($this->post_id, wp_list_pluck($entries, 'id'));
    }

    public function test_custom_canonical_uses_wordpress_filter_instead_of_duplicate_markup(): void {
        $post = get_post($this->post_id);
        update_post_meta($this->post_id, '_mds_seo_canonical_url', 'https://example.com/preferred/');

        $this->assertSame(
            'https://example.com/preferred/',
            $this->extension->filter_canonical_url(get_permalink($post), $post)
        );

        ob_start();
        $this->extension->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringNotContainsString('rel="canonical"', $output);
    }

    public function test_known_seo_plugin_pauses_overlapping_output(): void {
        $filter = static function ($plugins) {
            $plugins[] = 'Fixture SEO';
            return $plugins;
        };
        add_filter('mds_seo_basic_detected_seo_plugins', $filter);

        $this->assertFalse($this->extension->output_allowed('metadata'));
        $this->assertFalse($this->extension->output_allowed('social'));
        $this->assertFalse($this->extension->output_allowed('schema'));
        $this->assertFalse($this->extension->output_allowed('sitemap'));

        remove_filter('mds_seo_basic_detected_seo_plugins', $filter);
    }
}
