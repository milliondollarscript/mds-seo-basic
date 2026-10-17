# SEO Basic

SEO Basic manages search titles, descriptions, canonical URLs, indexing choices, social sharing cards, structured data, and XML sitemaps for Million Dollar Script sites.

## Practical Examples

- Give a public grid page a useful search title and description even when most of its visible content comes from a block or shortcode.
- Choose a campaign image for social sharing so links posted to social networks do not use an unrelated theme image.
- Keep account, ordering, duplicate, or temporary pages out of XML sitemaps and search results while still allowing direct access where appropriate.

### Example workflow: prepare a grid page for discovery

1. Open the public grid page in the WordPress editor and find **Search and Social Preview**.
2. Add an accurate title and description, select a social image, and leave the canonical URL empty unless another URL is genuinely preferred.
3. Publish or update the page, then open `/sitemap.xml` and confirm the page appears under the grid sitemap.
4. Inspect the page source or a search-preview tool to verify its canonical, description, social metadata, and structured data. If a full SEO suite is active, decide which plugin owns these outputs before enabling the compatibility override.

## Setup

1. Activate Million Dollar Script and SEO Basic.
2. Open **Million Dollar Script -> Extensions -> SEO Basic**.
3. Choose the public output SEO Basic should manage. Search metadata, social cards, structured data, and the XML sitemap are enabled by default.
4. Open the sitemap URL shown on the settings page and confirm its enabled sections contain the expected public content.
5. Edit important posts, pages, and public grid pages to customize their Search and Social Preview values.

The legacy keywords tag is disabled by default. It is available for integrations that still consume saved keyword metadata.

## Page And Post Metadata

SEO Basic adds a **Search and Social Preview** panel to posts, pages, and Million Dollar Script grid page post types.

The panel provides:

- A search title that uses WordPress' normal document-title pipeline.
- A search description for search metadata, social previews, and structured data.
- A canonical URL override for content whose preferred public URL is elsewhere.
- A social sharing image selected from the Media Library or entered by URL.
- An optional legacy keywords field when keyword output is enabled.
- A noindex choice that also removes the page from SEO Basic sitemaps.

Leave a value empty to keep the normal WordPress title, permalink, or featured image. An empty description falls back to the excerpt, readable page content, a linked grid's description, or concise grid details so shortcode-only public grid pages still have useful search and social metadata. A saved description always takes priority. SEO Basic uses WordPress' canonical and robots filters rather than printing competing tags directly.

## Social Cards And Structured Data

Social sharing output includes Open Graph and X/Twitter card metadata. A saved social image takes priority over the featured image. Cards without an image use a compact summary type.

Structured data uses the closest supported page type:

- `WebSite` for the site front page.
- `Article` for posts.
- `WebPage` for standard pages.
- `CollectionPage` for public Million Dollar Script grid pages.

Developers can adjust the generated array with the `mds_seo_basic_schema` filter.

## XML Sitemaps

The sitemap index is served dynamically at:

```text
/sitemap.xml
```

Enabled sections use these routes:

```text
/sitemap-posts.xml
/sitemap-pages.xml
/sitemap-grids.xml
/sitemap-advertisers.xml
```

Large sections are split into pages of 1,000 URLs. Additional pages use routes such as `/sitemap-grids-2.xml`. Each request reads current published content; the extension does not create a static XML file.

Grid discovery supports public grid page post types, page metadata, Million Dollar Script grid shortcodes, and native grid blocks. Individual advertiser pages are included only while core advertiser pages are enabled and **Exclude Advertiser Pages From Search** is disabled. Standard ordering, account, upload, and management pages are not included unless they contain a public grid view.

Use **Refresh sitemap routes** after changing permalink behavior or when a server cache still reports a stale route. The action refreshes WordPress rewrite rules; it does not rebuild sitemap content.

## SEO Plugin Compatibility

SEO Basic detects common full SEO suites. When one is active, SEO Basic pauses its public metadata, social, structured-data, and sitemap output to prevent duplicate tags and competing routes. Saved values remain intact.

An advanced override is available on the settings page. Enable it only after disabling the overlapping features in the other SEO plugin. Developers can also control individual output areas with `mds_seo_basic_output_allowed`.

## Legal And Privacy

SEO Basic can contribute a **Search and Social Metadata Terms** page through the Million Dollar Script extension setup flow. Review the page against the site's actual content and policies before publishing it.

Search engines and social platforms may cache page titles, descriptions, images, URLs, and public content. Site owners remain responsible for accurate claims, permissions, disclosures, and removal expectations.

## Troubleshooting

If `/sitemap.xml` returns a 404:

- Confirm the XML sitemap is enabled on **Million Dollar Script -> Extensions -> SEO Basic**.
- Use **Refresh sitemap routes**.
- Open **Settings -> Permalinks** and save the current permalink structure.
- Clear any page, object, reverse-proxy, or CDN cache.
- Check the SEO Basic settings page for a detected SEO-plugin conflict.

If a public grid is missing from `/sitemap-grids.xml`:

- Confirm its WordPress page is published.
- Confirm the page contains a public grid block or shortcode.
- Confirm **Exclude this page from search results and SEO Basic sitemaps** is not selected.
- Confirm **Public grid pages** is enabled in the sitemap settings.
