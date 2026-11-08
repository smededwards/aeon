# Aeon

Aeon adds portfolio features to WordPress through namespaced PHP services loaded by Composer.

## Name

Aeon comes from Greek *aiōn*, meaning an age or extended passage of time. The name reflects functionality that can remain useful as a site changes and the plugin evolves; it does not limit Aeon to time-based features.

Settings are grouped under **Dashboard** and **Content**. Rune manages editor controls and pattern settings under **Settings → Rune**. The site menu is managed through the active block theme's native Navigation editor.

## Contact Submissions

`features/contact/class-submission.php` creates the validated contact-submission value used for delivery. Its private constructor and readonly properties keep construction inside the factory. The factory checks raw input lengths and email line breaks before sanitising; retained invalid values are length-limited for form feedback. `functions-contact.php` exposes `aeon_sanitise_contact_submission()` as a global adapter for the WordPress coding-standard sanitisation rule. PHPCS registers that adapter directly.

Rune’s Contact Form block posts to its published Page without front-end JavaScript. Aeon checks the page-bound nonce, honeypot, field types, required values, email syntax, header safety and length limits: 100 characters for names, 254 for email addresses and 5,000 for messages. It sanitises text before sending plain-text mail. The recipient is the administration email in Settings → General. The sender uses `wordpress@` followed by the site hostname; the validated visitor address is used for Reply-To.

Validation errors appear on the same page with HTTP 422, and entered values are retained for that request. Successful submissions redirect with HTTP 303 to a readable `?sent=1#contact-form` receipt, preventing a refresh from resubmitting the form. Aeon passes the success state to the block. Submission content is not stored in WordPress. Rate limiting stores a salted hash of the peer address and an attempt counter for up to 15 minutes, allowing five attempts in each window. Forwarded headers are ignored. Contact pages send no-cache headers to keep nonces fresh; exclude them from production page and CDN caches as well.

From the site directory, run `docker compose run --rm tools wp eval-file /workspace/aeon/tests/contact.php`. The test intercepts mail, removes its fixture page and rate counters, and checks validation, sanitisation, header injection, the honeypot, rate limits and delivery failure. Local mail uses Mailpit. Configure production mail before launch.

## Architecture

- `aeon.php`: small WordPress entry point and Composer autoloader fallback.
- `class-plugin.php`: composes and registers services.
- `class-settings.php`: reads and sanitises known preferences.
- `admin/class-settings-page.php`: native Settings API form, restricted to administrators.

Classes use the `Aeon` namespace and responsibility-based directories at the plugin root. PHP filenames are lowercase and hyphenated, with no `src/` directory. Constructors receive their dependencies and register hooks explicitly. Add new core controls as separate feature classes when needed.

## SEO and Sharing

Posts and Pages have a native **Meta Boxes → SEO and Sharing** panel below the block editor (also available in the classic editor):

- **SEO Title** replaces the content portion of WordPress’s document title while keeping the site name, tagline and pagination. The post or page title stays unchanged.
- **Meta Description** replaces the default excerpt or short summary. Core block-excerpt extraction skips Query Loop and no-results content. Aeon omits the description when no useful text is available.
- **Social Image** uses the Media Library picker. Aeon falls back to the featured image when the field is empty or its saved image is unavailable. It stores an attachment ID and uses the full-size image without generating extra sizes.

Aeon outputs a meta description, Open Graph tags and Twitter Card tags for public Posts and Pages, including the static homepage and configured Posts index. Drafts, previews, password-protected posts and private content are skipped. Values are sanitised when saved and escaped on output. Fields use native post meta, post-level edit permissions, edit-context REST schemas and revision support.

WordPress core supplies the document title, canonical links, robots directives and XML sitemaps. **Settings → Aeon → SEO and Sharing** disables Aeon’s metadata hooks and editor panel while keeping saved fields. Disable Aeon before enabling another SEO provider to prevent duplicate metadata.

From the site directory, run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/seo.sh`. The tests restore settings, remove temporary posts and images, and check fallbacks, overrides, escaping, image IDs, protected content, REST/editor persistence, permissions and disabling Aeon’s metadata. Browser checks cover choosing or removing a social image and saving the post in the block editor.

## Development and Checks

Develop alongside `portfolio`, `obel` and `rune` using the site containers. Run `composer install` here to create the development autoloader. In production, the site Composer autoloader loads Aeon’s classes. If no autoloader is available, Aeon displays an administrator notice. The `vendor` directory is not committed. After adding or moving classes, run `composer dump-autoload` in this directory and refresh the site Composer metadata when updating the site dependency.

Dashboard colours and the featured-image picker are in `src/`. `@wordpress/scripts` and Webpack compile them into the ignored `build/` directory. In this directory, run `npm ci && npm run build`; run `npm run lint:js` and `npm run lint:scss` for lint checks. From the portfolio directory, use `make assets-install assets-build`. CI builds assets before packaging the site. Generated assets are not committed.

Run `composer validate --strict`, `composer lint` and the PHP syntax checks. WordPress filename checks are enabled; the class-to-filename check is exempted for `admin/class-settings-page.php` to retain the `SettingsPage` class name.

Behavioural tests use WordPress to check settings registration and sanitisation. CI runs them with an isolated database and a minimal theme. Locally, activate Aeon and select Obel, then run:

```sh
docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/integration.sh
```

Run the command from the site directory. The test restores the previous settings and leaves theme files and site content unchanged.

Review code changes locally and get approval before committing, pushing or opening or updating a PR. After checks pass, publish a semantic version tag and update the site’s production Composer lock in a separate change. Local development uses linked working copies; production uses tagged releases.

## Emoji Assets

Aeon removes WordPress’s emoji detection scripts, inline styles and image conversion hooks. Emoji characters remain text and use the operating system’s native rendering.

## Contribution Conventions

Use Conventional Commit subjects and PR titles. Name the first commit `Initial Commit`. See `AGENTS.md` for the repository conventions.

## Comment Controls

By default, **Settings → Aeon → Comments** disables comments and pingbacks on Posts and Pages. Aeon removes their editor controls and comment columns, hides the Comments menu and toolbar shortcut, and suppresses classic comments, counts and discussion blocks. Settings → Discussion remains available. Existing comments and per-post discussion settings are retained. Turning the control off restores WordPress behaviour on the next request. Other post types are unaffected.

This setting does not delete comments, change moderation status or restrict access through administrative APIs. WordPress rejects new standard and REST comment submissions when discussion is closed.

From the site directory, run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/comments.sh` to check both settings. The runner restores the original option and removes its temporary posts and comments.

## Content Duplication

Posts and Pages have a native **Duplicate** row action. Aeon creates a Draft copy with the editor content, excerpt, taxonomies, custom metadata and actual featured image, while leaving media files shared and excluding editor locks and old-slug metadata.

From the site directory, run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/duplicate.sh` to verify the Post and Page actions and copied data.

## Dashboard Environment and Media Defaults

Under **Settings → Aeon → Dashboard**, select WordPress blue, Production red, Development orange or Local green. Aeon applies the chosen accent to the dashboard and block editor, including component colours and hover and focus states. Bedrock continues to determine the active site environment.

The Dashboard section also stores optional base URLs for Local, Development and Production. The admin toolbar shows the detected environment and links to configured sites, preserving the current path and query where possible. Aeon hides Bedrock’s search-indexing notice. Bedrock’s indexing settings continue to control indexing.

Under **Settings → Aeon → Content → Default Featured Image**, choose an optional Media Library image for Posts and Pages that have no featured image. Clearing the setting or deleting the attachment removes the fallback. Aeon selects `placeholder.png` initially if it exists in the Media Library.

WebP uploads are enabled. SVG uploads require administrator access and the `unfiltered_html` capability. Aeon validates the SVG document and rejects doctype and entity declarations. Upload SVGs only from trusted sources; this check does not sanitise SVG markup.
