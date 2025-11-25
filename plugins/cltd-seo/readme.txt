=== CLTD SEO Toolkit ===
Contributors: crystalthedeveloper
Requires at least: 6.3
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight SEO helper that adds custom meta-title/description fields to posts & pages with an optional OpenAI-powered generator.

== Description ==

CLTD SEO Toolkit lets you keep meta data portable across projects:

* Adds dedicated “Meta Title” and “Meta Description” fields to posts & pages.
* Outputs the chosen values inside `<head>` automatically (falls back to the document title/content excerpt).
* Optional OpenAI integration (free tier supported) to auto-generate descriptive copy, plus a safe local fallback if no API key is provided.
* Hardened with nonces, capability checks, and proper escaping.

No paid dependencies, no heavy UI—just the essentials for projects that need reusable SEO controls.

== Installation ==

1. Upload the `cltd-seo` folder to `/wp-content/plugins/` or install via the admin plugin uploader.
2. Activate “CLTD SEO Toolkit” from **Plugins → Installed Plugins**.
3. (Optional) Visit **Settings → CLTD SEO** to paste your OpenAI API key. Leave blank to rely on the local fallback generator.
4. Edit any post or page—you’ll see the “CLTD SEO” panel in the editor sidebar with Title/Description fields, OG Image controls, preview, and a “Generate with AI” button.

== Frequently Asked Questions ==

= Is the OpenAI key required? =

No. If you don’t provide a key, the generator falls back to a local summary built from your content, so you still get a suggested description.

= Does it work with custom post types? =

Yes—hook into `cltd_seo_supported_post_types` to append your CPT slugs:

`add_filter( 'cltd_seo_supported_post_types', function( $types ) { $types[] = 'product'; return $types; } );`

== Changelog ==

= 1.0.0 =
* Initial release with meta fields, OpenAI/local generator, and settings screen.
