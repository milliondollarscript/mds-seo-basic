<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mds3-admin mds-seo-admin">
    <div class="mds-extension-heading-row">
        <h1><?php esc_html_e('SEO Basic', 'mds-seo-basic'); ?></h1>
        <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-seo-basic')); ?>
    </div>
    <hr class="wp-header-end">

    <?php if (isset($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('SEO settings saved.', 'mds-seo-basic'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($conflicts)) : ?>
        <div class="mds-seo-notice mds-seo-notice--warning" role="status">
            <strong><?php esc_html_e('Another SEO plugin is active', 'mds-seo-basic'); ?></strong>
            <p>
                <?php
                printf(
                    esc_html__('SEO Basic detected %s. Its public metadata and sitemap output are paused to prevent duplicate tags and competing sitemap routes.', 'mds-seo-basic'),
                    esc_html(implode(', ', $conflicts))
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mds_seo_save_settings">
        <?php wp_nonce_field('mds_seo_save_settings'); ?>

        <div class="mds-seo-layout">
            <section class="mds3-card mds-seo-panel" aria-labelledby="mds-seo-output-title">
                <h2 id="mds-seo-output-title"><?php esc_html_e('Search output', 'mds-seo-basic'); ?></h2>
                <p><?php esc_html_e('Choose which public signals SEO Basic manages. Page-specific values are edited in the Search and Social Preview panel on each post or page.', 'mds-seo-basic'); ?></p>

                <fieldset class="mds-seo-fieldset">
                    <legend class="screen-reader-text"><?php esc_html_e('Search output settings', 'mds-seo-basic'); ?></legend>
                    <?php
                    $output_fields = [
                        'metadata_enabled' => [__('Search metadata', 'mds-seo-basic'), __('Use custom titles, descriptions, canonical URLs, and indexing choices.', 'mds-seo-basic')],
                        'social_enabled' => [__('Social sharing cards', 'mds-seo-basic'), __('Output Open Graph and X/Twitter preview metadata.', 'mds-seo-basic')],
                        'schema_enabled' => [__('Structured data', 'mds-seo-basic'), __('Output WebSite, WebPage, Article, or CollectionPage JSON-LD as appropriate.', 'mds-seo-basic')],
                        'keywords_enabled' => [__('Legacy keywords tag', 'mds-seo-basic'), __('Output saved meta keywords for compatibility. Most modern search engines do not use this tag.', 'mds-seo-basic')],
                    ];
                    foreach ($output_fields as $key => $field) :
                        ?>
                        <label class="mds-seo-toggle-row">
                            <input type="checkbox" name="settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>>
                            <span><strong><?php echo esc_html($field[0]); ?></strong><small><?php echo esc_html($field[1]); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <?php if (!empty($conflicts)) : ?>
                    <label class="mds-seo-toggle-row mds-seo-toggle-row--advanced">
                        <input type="checkbox" name="settings[allow_parallel_output]" value="1" <?php checked(!empty($settings['allow_parallel_output'])); ?>>
                        <span>
                            <strong><?php esc_html_e('Allow output alongside the detected SEO plugin', 'mds-seo-basic'); ?></strong>
                            <small><?php esc_html_e('Use only when you have deliberately disabled the overlapping features in the other plugin.', 'mds-seo-basic'); ?></small>
                        </span>
                    </label>
                <?php else : ?>
                    <input type="hidden" name="settings[allow_parallel_output]" value="0">
                <?php endif; ?>
            </section>

            <section class="mds3-card mds-seo-panel" aria-labelledby="mds-seo-sitemap-title">
                <div class="mds-seo-panel__heading">
                    <div>
                        <h2 id="mds-seo-sitemap-title"><?php esc_html_e('XML sitemap', 'mds-seo-basic'); ?></h2>
                        <p><?php esc_html_e('The sitemap is generated from current published content whenever it is requested. No static XML file is stored.', 'mds-seo-basic'); ?></p>
                    </div>
                    <span class="mds-seo-status <?php echo $sitemap_enabled ? 'mds-seo-status--success' : 'mds-seo-status--neutral'; ?>">
                        <?php echo $sitemap_enabled ? esc_html__('Active', 'mds-seo-basic') : esc_html__('Paused', 'mds-seo-basic'); ?>
                    </span>
                </div>

                <label class="mds-seo-toggle-row">
                    <input type="checkbox" name="settings[sitemap_enabled]" value="1" <?php checked(!empty($settings['sitemap_enabled'])); ?>>
                    <span><strong><?php esc_html_e('Enable SEO Basic sitemap', 'mds-seo-basic'); ?></strong><small><?php esc_html_e('Serve the sitemap index and enabled content sections.', 'mds-seo-basic'); ?></small></span>
                </label>

                <fieldset class="mds-seo-fieldset mds-seo-fieldset--nested">
                    <legend><?php esc_html_e('Included content', 'mds-seo-basic'); ?></legend>
                    <?php
                    $sitemap_fields = [
                        'sitemap_posts' => __('Posts', 'mds-seo-basic'),
                        'sitemap_pages' => __('Pages', 'mds-seo-basic'),
                        'sitemap_grids' => __('Public grid pages', 'mds-seo-basic'),
                        'sitemap_advertisers' => __('Individual advertiser pages', 'mds-seo-basic'),
                    ];
                    foreach ($sitemap_fields as $key => $label) :
                        ?>
                        <label><input type="checkbox" name="settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>> <?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="mds-seo-sitemap-link">
                    <code><?php echo esc_html($sitemap_url); ?></code>
                    <a class="button" href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open sitemap', 'mds-seo-basic'); ?></a>
                </div>

                <button type="button" id="mds-seo-generate-sitemap" class="button"><?php esc_html_e('Refresh sitemap routes', 'mds-seo-basic'); ?></button>
                <div id="mds-seo-sitemap-result" class="mds-seo-ajax-status" role="status" aria-live="polite"></div>
            </section>
        </div>

        <?php submit_button(__('Save SEO settings', 'mds-seo-basic')); ?>
    </form>

    <section class="mds3-card mds-seo-panel" aria-labelledby="mds-seo-workflow-title">
        <h2 id="mds-seo-workflow-title"><?php esc_html_e('Publishing workflow', 'mds-seo-basic'); ?></h2>
        <ol class="mds-seo-steps">
            <li><?php esc_html_e('Edit a post, page, or public grid page and open Search and Social Preview.', 'mds-seo-basic'); ?></li>
            <li><?php esc_html_e('Add only the metadata that should differ from the page content and WordPress defaults.', 'mds-seo-basic'); ?></li>
            <li><?php esc_html_e('Preview the public page and share it with the platform debugger you use before promotion.', 'mds-seo-basic'); ?></li>
            <li><?php esc_html_e('Open the XML sitemap and submit its URL through the search tools relevant to your site.', 'mds-seo-basic'); ?></li>
        </ol>
    </section>
</div>
