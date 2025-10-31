CLTD Theme (October 2025)
=========================

Overview
--------
CLTD Theme (Oct 2025) delivers a lean WordPress layout framework that keeps all
structure, layout, and styling inside the theme while delegating editable
content to the companion **CLTD Theme Admin** plugin. Site owners manage hero
copy, sidebar links, footer legal text, and popup content from a branded
settings panel powered by the WordPress Options API. The result is a locked,
high-performance presentation layer with a familiar, content-first editing
experience.

How Content Is Loaded
---------------------
* The theme reads from the `cltd_theme_content` option (array) and merges it
  with defaults defined in `functions.php::cltd_theme_get_content_defaults()`.
* A filter hook `cltd_theme_content` lets the admin plugin (or custom code)
  modify the payload before it is rendered.
* All data is accessed through `cltd_theme_get_content()` inside templates,
  keeping markup declarative and designer-friendly.

Content Shape
-------------
The option payload mirrors the default structure below (all keys optional):

```
[
  'sidebar_socials' => [
    [ 'label' => 'Github', 'url' => 'https://github.com/', 'target' => '_blank' ],
    …
  ],
  'auth_links' => [
    [ 'label' => 'Sign In', 'url' => 'https://example.com/login' ],
    …
  ],
  'hero' => [
    'title' => 'Your CMS, My code.',
    'subtitle' => 'Scalable, accessible websites…',
    'tagline' => 'WordPress frameworks',
    'background_image' => 'https://…/hero.jpg',
    'buttons' => [
      [ 'label' => 'Start a project', 'url' => '#contact', 'style' => 'primary' ],
      …
    ],
  ],
  'sections' => [], // optional; grids are normally generated from popup_group taxonomy
  'footer' => [
    'text' => '2025 © Crystal The Developer Inc. All rights reserved',
    'links' => [
      [ 'label' => 'Terms of Service', 'url' => '/terms' ],
      …
    ],
  ],
]
```

Hero Background Panel
---------------------
* Visit **Hero Background** in the WordPress admin menu to manage media behind
  the hero section.
* Supported types: still image, GIF, HTML5 video (MP4 / WebM), and Lottie JSON.
* The panel uses the Media Library so editors can upload, preview, and swap
  assets without touching code. JSON uploads are enabled for animation export
  workflows (e.g., Blender → Lottie).
* Videos can be muted, looped, and paired with a poster frame. Lottie playback
  supports speed and loop options.
* Add multiple slides to build an auto-advancing hero background slider (no UI
  chrome); slides rotate according to the configured interval.
* Adjust the overlay opacity (0–1) to tune readability for the foreground hero
  content. The media runs full-screen at 100vw × 100vh with a fixed position
  and is optimised for smooth playback on the front end.

Popup Architecture
------------------
* Circular triggers with `type: popup` call the REST route
  `cltd/v1/popup/{slug}` (see `functions.php`).
* The route returns the title and rendered content from a WordPress page (or
  optional `cltd_popup` custom post type) whose path matches the slug.
* If a popup slug matches a published blog tag (`post_tag`), the theme appends
  a list of recent posts carrying that tag to the modal output automatically.
* `js/main.js` handles focus management, async loading states, and accessible
  modal interactions.

Popup Custom Post Type
----------------------
* The theme can automatically register a `cltd_popup` custom post type (can be
  disabled via the `cltd_theme_register_popup_cpt` filter).
* Popups live under the “Popups” menu in the dashboard; each post title becomes
  the modal headline and the post content powers the modal body.
* Assign the URL-friendly slug in WordPress (e.g. `webflow`, `drupal`) and use
  that value for the `data-popup-slug` attribute in the layout or options.
* The CPT is REST-enabled (`/wp-json/cltd/v1/popup/{slug}`) and honours native
  editor features (revisions, media library, featured images).
* A “Duplicate” link appears in the Popups list table so editors can copy an
  existing modal (content, taxonomies, and metadata) without re-entering data.
* Each popup supports an SVG icon (Icon meta box) that renders centered inside
  its circular trigger on the front end.

Popup Group Taxonomy
--------------------
* Popup groups can now define their grid column span (1–3) and a custom order,
  giving editors control over layout density and sequence.
* Only published popups surface on the front end, keeping the layout synced
  with live content. Drafts remain hidden until they are published.
* Group descriptions can be used to add contextual copy to each section in the
  layout, while additional manual items can still be appended via the Options
  API payload if needed.

Styling Notes
-------------
* All layout and component rules live in `style.css` for easy version control.
* CSS variables define the core palette so designers can iterate without
  touching PHP.
* Utility selectors such as `.sr-only`, `.circle--link`, and `.circle--inactive`
  keep templates clean while enabling stateful styling.

Development Tips
----------------
* Customize or extend defaults by hooking into `cltd_theme_content`.
* When adding new popup slugs, ensure a matching page exists (or that the
  admin plugin pushes the content into a compatible source).
* The modal adds `body.cltd-modal-open` while active. If additional overlays
  are introduced, reuse that class to centralize scroll locking.

This README will grow alongside the CLTD Theme Admin plugin documentation so
the entire workflow—from content entry to deployment—remains transparent and
maintainable.
