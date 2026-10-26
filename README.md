# Aeon

Portfolio functionality plugin with namespaced PHP services and Composer classmap autoloading.

## Classic navigation menus

Aeon enables WordPress's **Appearance → Menus** screen by adding `menus` support after the active theme sets up. Manage this under **Settings → Aeon → Classic navigation menus**. The feature is enabled by default.

Turning the setting off stops Aeon adding support on the next request. It never removes menu support supplied by the theme or another plugin, and never deletes saved menus or menu items. With Obel's current scaffold, switching this off hides the classic Menus screen. This controls classic menus, not the block-based Navigation editor.

Menu locations and frontend rendering belong to Obel. Aeon does not create menus, register layout-specific locations, change permissions or force menu output on the frontend.

## Pattern controls

Three independent settings are available under **Settings → Aeon**, all enabled by default:

- **Patterns shortcut** adds **Appearance → Patterns**, linking to WordPress's native manager. It avoids duplicating an existing core shortcut. Turning it off removes only Aeon's link; the native Design/Editor screen and saved patterns remain accessible with their normal permissions.
- **Bundled core patterns** controls the layouts supplied with WordPress. Turning it off unregisters only patterns marked with the `core` source, preserving theme/plugin patterns, categories and independently controlled remote patterns.
- **Remote patterns** controls automatic WordPress.org pattern loading and manual directory REST queries. When disabled, the directory endpoint returns an explicit 403 response; other REST routes are unaffected. Existing saved patterns and content are preserved.

The core and remote controls remain independent: Aeon does not remove `core-block-patterns` theme support, because WordPress uses that flag for both sources. Unsynced layouts already inserted into content and saved synced patterns continue to render. Obel remains responsible for designed theme patterns.

## Architecture

- `aeon.php`: small WordPress entry point and Composer autoloader fallback.
- `class-plugin.php`: composes and registers services.
- `class-settings.php`: reads and sanitises known preferences.
- `admin/class-settings-page.php`: native Settings API form, restricted to administrators.
- `features/class-navigation.php`: theme support for classic navigation menus.
- `features/class-patterns.php`: the native manager shortcut and pattern-source controls.

Classes keep the `Aeon` namespace and existing class names. PHP files use lowercase, hyphenated WordPress-style names, grouped directly at the plugin root without a `src/` wrapper. Constructors receive dependencies; hook registration is explicit. No framework, service container or global singleton is required. Future core controls can live in separate feature classes.

## SEO and sharing

Posts and Pages have a native **Meta boxes → SEO and sharing** panel below the block editor (also available in the classic editor):

- **SEO title** overrides the content portion of WordPress’s document title, retaining its site/tagline and pagination components. It does not rename the post/page.
- **Meta description** overrides the default excerpt or short text summary. Core block-excerpt extraction excludes Query Loop/no-results content; no description is emitted when there is no useful text.
- **Social image** uses the native Media Library picker. Leaving it unset, removing it, or deleting an unavailable override falls back to the featured image. Only image attachment IDs are stored. The existing full-size image is used; this feature creates no additional image sizes.

Aeon adds one meta description plus Open Graph and Twitter Card tags on public Posts and Pages, including the static homepage and configured Posts index. It skips drafts, previews and password-protected/private content. Values are sanitised on save and escaped on output. Fields use native post meta, per-post edit permissions, edit-context REST schemas and revision support.

Core continues to render the document title, canonical links, robots directives and XML sitemaps. Aeon does not print replacement title/canonical/robots markup. **Settings → Aeon → SEO and sharing** turns off Aeon’s metadata hooks and editor panel without deleting saved fields. Turn this off before enabling another SEO metadata provider to avoid duplicate tags.

Run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/seo.sh` from the site directory. The tests restore settings and remove their temporary posts/images. They cover fallbacks, overrides, escaping, image IDs, protected content, native REST/editor persistence, permissions and the handoff switch. Browser checks also cover selecting/removing a social image and saving through the block editor.

## Development and checks

Develop alongside `portfolio`, `obel` and `rune`, using the site's containers. Run `composer install` in this plugin directory to generate its development autoloader. When installed as a production Composer dependency, the site's autoloader loads Aeon's classes. A missing autoloader produces an administrator notice instead of a fatal error. No vendor directory is committed. After adding or moving classes, regenerate the classmap with `composer dump-autoload` in the plugin directory. After updating the package as a site dependency, regenerate the site's Composer metadata/autoloader too.

Run `composer validate --strict`, `composer lint` and PHP syntax checks. WordPress filename checks are enabled. Only the class-to-filename check for `admin/class-settings-page.php` is exempted, preserving the existing `SettingsPage` class name while using a hyphenated filename.

Behavioural tests run against real WordPress, checking default/on/off behaviour, strict sanitisation, preservation of saved menu items and respect for theme-provided support. CI uses an isolated database and a minimal test theme without menu support. Locally, with Aeon active and Obel selected:

```sh
docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/integration.sh
```

Run that command from the site directory. The tests temporarily create one menu and one saved pattern, check all eight combinations of pattern controls, then restore the previous setting and remove both test fixtures. Directory-off behaviour is tested without making external network requests. No theme files or other content are changed.

Code changes are reviewed locally first. Wait for explicit approval before committing, pushing or opening/updating a PR. Publish a new semantic version tag after checks pass, then update the site's production Composer lock separately. Development working copies remain linked locally; production keeps tagged dependencies.

## Contribution conventions

Use Conventional Commit subjects and PR titles. The first commit is the sole exception: `Initial Commit`. See `AGENTS.md` for repository guidance.

## Comment controls

**Settings → Aeon → Comments** disables comments and pingbacks on Posts and Pages by default. It removes their editor support and comment columns, hides the Comments menu and toolbar shortcut, and suppresses classic comment output/counts and native discussion blocks. Settings → Discussion stays available. Existing comments and saved per-post discussion preferences are retained; turning the control off restores normal WordPress behaviour on the next request. Other post types are not changed.

This controls the site's discussion interfaces, not access to stored comments through administrative APIs. It does not delete comments or alter their moderation status. WordPress handles rejection of new standard and REST comment submissions once discussion is closed.

Run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/comments.sh` from the site directory to check both modes. The runner restores the original option and removes its temporary posts/comments.

## Editor controls

**Settings → Aeon → Block editor → Simplify available blocks** is enabled by default. It limits the Posts, Pages and saved patterns inserter to essential native content and layout blocks, including Code, Table, Page List, synced patterns and the complete Query Loop hierarchy used by the portfolio. Other editor contexts are unchanged; earlier block restrictions are respected.

Turning it off restores the selection on the next request. Blocks are not unregistered, saved content is not rewritten and frontend rendering is unchanged. Obel controls visual options such as gradients and duotone through `theme.json`.

Run `docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/editor.sh` from the site directory to verify both modes. The runner restores the original settings.

**Settings → Aeon → Openverse → Hide Openverse in the editor** is enabled by default, independently of block simplification. It disables the native Openverse media category across block editors while retaining uploads, the Media Library and existing images. Turning it off restores the normal editor setting.
