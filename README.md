# Aeon

Portfolio functionality plugin with namespaced PHP services and Composer PSR-4 autoloading.

## Classic navigation menus

Aeon enables WordPress's **Appearance → Menus** screen by adding `menus` support after the active theme sets up. Manage this under **Settings → Aeon → Classic navigation menus**. The feature is enabled by default.

Turning the setting off stops Aeon adding support on the next request. It never removes menu support supplied by the theme or another plugin, and never deletes saved menus or menu items. With Obel's current scaffold, switching this off hides the classic Menus screen. This controls classic menus, not the block-based Navigation editor.

Menu locations and frontend rendering belong to Obel. Aeon does not create menus, register layout-specific locations, change permissions or force menu output on the frontend.

## Architecture

- `aeon.php`: small WordPress entry point and Composer autoloader fallback.
- `src/Plugin.php`: composes and registers services.
- `src/Settings.php`: reads and sanitises known preferences.
- `src/Admin/SettingsPage.php`: native Settings API form, restricted to administrators.
- `src/Features/Navigation.php`: theme support for classic navigation menus.

Classes use the `Aeon` namespace. Constructors receive dependencies; hook registration is explicit. No framework, service container or global singleton is required. Future core controls can live in separate feature classes.

## Development and checks

Develop alongside `portfolio`, `obel` and `rune`, using the site's containers. Run `composer install` in this plugin directory to generate its development autoloader. When installed as a production Composer dependency, the site's autoloader loads Aeon's classes. A missing autoloader produces an administrator notice instead of a fatal error. No vendor directory is committed.

Run `composer validate --strict`, `composer lint` and PHP syntax checks. PSR-4 class filenames are the explicit exception to WordPress's lowercase/hyphenated filename convention; remaining WPCS checks are retained.

Behavioural tests run against real WordPress, checking default/on/off behaviour, strict sanitisation, preservation of saved menu items and respect for theme-provided support. CI uses an isolated database and a minimal test theme without menu support. Locally, with Aeon active and Obel selected:

```sh
docker compose run --rm -w /workspace/aeon tools env WP_PATH=/workspace/portfolio/web/wp sh tests/integration.sh
```

Run that command from the site directory. The tests temporarily create one menu and change Aeon's option, then restore the previous setting and remove the test menu. No theme files or other content are changed.

Package changes are reviewed in PRs. Publish a new semantic version tag after checks pass, then update the site's production Composer lock separately. Development working copies remain linked locally; production keeps tagged dependencies.

## Contribution conventions

Use Conventional Commit subjects and PR titles. The first commit is the sole exception: `Initial Commit`. See `AGENTS.md` for repository guidance.
