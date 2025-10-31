# CLTD WordPress Theme (October 2025)

Custom WordPress theme tailored for the Community Leadership & Training Division (CLTD) October 2025 site refresh.

## Features
- Flexible page templates for landing pages, resources, and event listings
- Customizable color palette aligned with CLTD brand guidelines
- Gutenberg block patterns for hero sections, testimonials, and calls to action
- Responsive layout and accessible typography baseline
- Support for WordPress navigation menus, widgets, and featured images

## Requirements
- WordPress 6.4 or newer
- PHP 8.1+
- Node.js 18+ (for asset compilation)
- Composer 2.x (for PHP autoloaders, if used)

## Installation
1. Clone or download this repository into your WordPress installation under `wp-content/themes/cltd-theme-oct-2025`.
2. From the project root, install front-end dependencies:
   ```sh
   npm install
   ```
3. (Optional) Install PHP dependencies:
   ```sh
   composer install
   ```
4. Compile assets for production:
   ```sh
   npm run build
   ```
5. Activate the **CLTD Theme (October 2025)** theme in the WordPress admin dashboard.

## Development
- Use `npm run dev` for watch mode while editing styles or scripts.
- Lint PHP with `composer run lint` if the script is defined.
- Maintain consistent coding standards by running `npm run lint` before committing.

## Deployment
- Ensure `npm run build` has been executed to generate optimized assets.
- Tag releases with semantic versioning (e.g., `v1.0.0`) for easier rollback.
- Upload the built theme directory to the target WordPress environment.

## Support & Contributions
- File issues or enhancement requests via GitHub Issues.
- Submit pull requests from feature branches following the naming convention `feature/<description>`.

## License
Specify your license terms here (e.g., MIT, GPL-2.0+). Update or remove this section if a different license applies.
