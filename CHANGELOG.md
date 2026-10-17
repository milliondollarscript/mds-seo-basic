# Changelog

## Unreleased

- Added opt-in uninstall cleanup through the core extension policy for extension-owned SEO settings and post metadata.

## 1.1.0 - 2026-07-13
- Added configurable search metadata, social card, structured-data, sitemap, and legacy-keywords output.
- Added canonical URL and noindex controls through WordPress' native canonical and robots pipelines.
- Added Media Library selection, image previews, character counters, and clearer page-level guidance.
- Added `WebSite`, `WebPage`, `Article`, and `CollectionPage` structured data with a developer filter.
- Added paginated 1,000-URL sitemap sections and reliable grid discovery for post types, metadata, shortcodes, and blocks.
- Excluded noindexed content from sitemap entries and page counts.
- Added conflict detection that pauses overlapping public output when a recognized SEO suite is active.
- Removed unconditional index/follow and version-generator tags.
- Moved admin behavior and styling into external assets with accessible light and dark semantic states.
- Added a repeatable local WordPress workflow fixture and refreshed unit coverage and documentation.
- Added excerpt, page-content, and grid-aware description fallbacks while preserving custom SEO descriptions.

## 1.0.1 - 2026-04-25
- Added MDS 3.0 admin menu and grid-page sitemap compatibility.
- Moved custom SEO titles into WordPress' document title filter to avoid duplicate title tags.
- Hardened meta box saves and sitemap AJAX regeneration with nonce/capability checks and type-aware sanitization.
- Escaped sitemap/admin output and removed non-ASCII status glyphs from admin JavaScript.

## 0.1.0 - 2025-01-01
- Added the initial SEO Basic extension scaffold.
