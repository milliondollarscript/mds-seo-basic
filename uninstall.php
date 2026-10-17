<?php
/** SEO Basic uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-seo-basic', [
    'option_prefixes' => ['mds_seo_basic_'],
    'post_meta_prefixes' => ['mds_seo_', '_mds_seo_'],
]);
