<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds-seo-meta-box">
    <p class="mds-seo-meta-box__intro"><?php esc_html_e('Leave a field empty to keep the normal WordPress value. Search engines and social platforms may rewrite or crop previews.', 'mds-seo-basic'); ?></p>

    <div class="mds-seo-form-field">
        <label for="mds_seo_title"><strong><?php esc_html_e('Search title', 'mds-seo-basic'); ?></strong></label>
        <input type="text" id="mds_seo_title" name="mds_seo_title" value="<?php echo esc_attr($metadata['title']); ?>" class="widefat" data-mds-seo-count="title" maxlength="200">
        <p class="description"><span data-mds-seo-counter="title"><?php echo esc_html(function_exists('mb_strlen') ? mb_strlen($metadata['title']) : strlen($metadata['title'])); ?></span> <?php esc_html_e('characters. Overrides the document title when supplied.', 'mds-seo-basic'); ?></p>
    </div>

    <div class="mds-seo-form-field">
        <label for="mds_seo_description"><strong><?php esc_html_e('Search description', 'mds-seo-basic'); ?></strong></label>
        <textarea id="mds_seo_description" name="mds_seo_description" rows="4" class="widefat" data-mds-seo-count="description" maxlength="500"><?php echo esc_textarea($metadata['description']); ?></textarea>
        <p class="description"><span data-mds-seo-counter="description"><?php echo esc_html(function_exists('mb_strlen') ? mb_strlen($metadata['description']) : strlen($metadata['description'])); ?></span> <?php esc_html_e('characters. Used for search metadata, social previews, and structured data.', 'mds-seo-basic'); ?></p>
    </div>

    <div class="mds-seo-form-field">
        <label for="mds_seo_canonical_url"><strong><?php esc_html_e('Canonical URL', 'mds-seo-basic'); ?></strong></label>
        <input type="url" id="mds_seo_canonical_url" name="mds_seo_canonical_url" value="<?php echo esc_attr($metadata['canonical']); ?>" class="widefat" placeholder="<?php echo esc_attr(get_permalink($post)); ?>">
        <p class="description"><?php esc_html_e('Use only when another URL should be treated as the preferred version of this content.', 'mds-seo-basic'); ?></p>
    </div>

    <div class="mds-seo-form-field">
        <label for="mds_seo_og_image"><strong><?php esc_html_e('Social sharing image', 'mds-seo-basic'); ?></strong></label>
        <div class="mds-seo-media-field">
            <input type="url" id="mds_seo_og_image" name="mds_seo_og_image" value="<?php echo esc_attr($metadata['image']); ?>" class="widefat">
            <button type="button" class="button" data-mds-seo-choose-image><?php esc_html_e('Choose image', 'mds-seo-basic'); ?></button>
            <button type="button" class="button-link-delete" data-mds-seo-remove-image <?php echo '' === $metadata['image'] ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'mds-seo-basic'); ?></button>
        </div>
        <img class="mds-seo-image-preview" data-mds-seo-image-preview src="<?php echo esc_url($metadata['image']); ?>" alt="" <?php echo '' === $metadata['image'] ? 'hidden' : ''; ?>>
        <p class="description"><?php esc_html_e('Uses the featured image when this field is empty.', 'mds-seo-basic'); ?></p>
    </div>

    <?php if ($keywords_enabled) : ?>
        <div class="mds-seo-form-field">
            <label for="mds_seo_keywords"><strong><?php esc_html_e('Legacy keywords', 'mds-seo-basic'); ?></strong></label>
            <input type="text" id="mds_seo_keywords" name="mds_seo_keywords" value="<?php echo esc_attr($metadata['keywords']); ?>" class="widefat">
            <p class="description"><?php esc_html_e('Comma-separated values for systems that still consume the keywords meta tag.', 'mds-seo-basic'); ?></p>
        </div>
    <?php else : ?>
        <input type="hidden" name="mds_seo_keywords" value="<?php echo esc_attr($metadata['keywords']); ?>">
    <?php endif; ?>

    <label class="mds-seo-noindex-row">
        <input type="checkbox" name="mds_seo_noindex" value="1" <?php checked($metadata['noindex']); ?>>
        <span><strong><?php esc_html_e('Exclude this page from search results and SEO Basic sitemaps', 'mds-seo-basic'); ?></strong><small><?php esc_html_e('Adds a noindex directive while allowing crawlers to follow links.', 'mds-seo-basic'); ?></small></span>
    </label>
</div>
