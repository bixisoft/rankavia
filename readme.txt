=== Rankavia – SEO and Site Discovery ===
Contributors: bixisoft
Tags: seo, schema, sitemap, redirects, robots
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO metadata, site discovery controls and a Visibility Score dashboard for WordPress.

== Description ==

Rankavia provides a Visibility Score dashboard and editable SEO settings. Its current development scope includes page metadata and social tags, site discovery controls, and selected structured data. Scores are guidance for review; they are not search ranking or AI citation predictions.

This is an early development release. It provides the Visibility Score dashboard, page metadata and social tags, noindex controls, WordPress core XML sitemap controls, robots.txt additions, AI crawler policies, an optional llms.txt courtesy index, and a basic WebSite, WebPage and configured Organization JSON-LD graph. The [rankavia_breadcrumbs] shortcode renders a public breadcrumb trail with matching BreadcrumbList markup. The Redirects submenu manages up to 100 exact-path, same-site 301/302 redirects or 410 responses. Regex redirects, a 404 monitor, full-data importers, richer schema and the full speed engine remain planned.

Rankavia does not claim that llms.txt or FAQ schema improve rankings; where it offers them, it labels what they do and do not do.

Use [rankavia_sitemap] for a bounded HTML list of up to 100 recent public posts and pages selected in sitemap settings. The authored FAQ editor block renders up to ten question and answer pairs and matching FAQPage structured data on eligible public pages. Private, password-protected and noindex content is excluded from the HTML sitemap; FAQ structured data is suppressed on nonpublic or noindex pages and when a recognized peer SEO plugin is active.

The SEO migration submenu provides a read-only preview, explicit apply and conflict-aware rollback for supported Yoast and Rank Math post/page metadata. Back up your database first. Existing destination fields are never overwritten. Source plugins are not modified or deactivated. Native wpdb and transactional InnoDB tables are required; custom database drop-ins are not supported.

== External services ==

The Visibility Score fetches your own site's home page over HTTP (from your server to your server) to analyse the HTML. Nothing is sent to Bixisoft or any third party. No analytics or tracking is included.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate it.
3. Open **Rankavia** in the admin menu to see your Visibility Score.

== Frequently Asked Questions ==

= Does it replace my current SEO plugin? =

Not yet. Run it alongside your current plugin to see your score. Third-party migration is pending validation; do not remove or deactivate an existing SEO plugin on the assumption that its settings have been imported.

= Will it slow my site down? =

Front-end metadata is generated locally. The score is computed only when you open the dashboard and is cached for ten minutes. Test the plugin with your theme and other plugins before using it on a live site.

== Changelog ==

= 0.1.0 =
* Development additions: sitemap controls, virtual robots.txt and AI crawler policies, optional llms.txt with saved-setting previews, and a basic evidence-based JSON-LD graph.
* Development additions: public breadcrumb shortcode with structured data, and a nonce-protected exact-path redirect manager with stale-save protection.
* Development additions: bounded public HTML sitemap shortcode and an authored FAQ block with sanitized visible answers and conditional structured data.
* Initial release: Visibility Score dashboard, meta description, canonical, Open Graph and Twitter card output, noindex for search pages.
