=== CLTD Migrate Free ===
Contributors: crystalthedeveloper
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

CLTD Migrate Free is a 100% free, unlimited-size WordPress migration plugin created by Crystal The Developer Inc. — designed for developers and site owners who want simple, reliable migrations without paid extensions or upload limits.

It securely exports and imports your full WordPress site — database, themes, plugins, and media — in a single .zip file that you can move between local, staging, and production environments.

Unlike commercial tools, CLTD Migrate Free runs entirely on your own server, using native PHP ZipArchive and mysqldump commands, so there are no file-size caps, subscriptions, or cloud dependencies.

Perfect for freelancers, agencies, and power users who want complete control over their data and a smooth, one-click migration workflow — completely free, forever.

== Installation ==

1. Upload the `cltd-migrate-free` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Visit **Tools → CLTD Migrate Free** to create exports or run imports.

== Bitnami / Lightsail deployments ==

Bitnami stacks often mark `/opt/bitnami/wordpress/wp-content/plugins/` as `root:root`, which blocks SFTP clients from creating `/assets/css` or `/assets/js` folders inside the plugin. Before running a build or rsync, execute:

```
cd /opt/bitnami/wordpress/wp-content/plugins/cltd-migrate-free
./scripts/bitnami-deploy.sh
```

You can pass a custom plugin path as the first argument, or override `PLUGIN_OWNER`, `PLUGIN_GROUP`, and `PLUGIN_PERMS` environment variables (defaults: `bitnami:daemon` with `775`) to match your stack’s policy.

== Changelog ==

= 1.0.0 =
* Initial release.
