# Repository conventions

- Make and test changes locally. Wait for explicit code approval before committing, pushing or opening/updating a PR.
- Use lowercase, hyphenated WordPress-style PHP filenames. Organise PHP classes in responsibility-based directories directly under the project root, without a PHP `src/` wrapper. Use namespaces and separate classes for new custom PHP functionality, with Composer classmap autoloading where needed.
- Use sentence case for package names: Aeon, Obel and Rune. Keep lowercase code identifiers and package slugs unchanged.
- Use Conventional Commits for every new commit and PR title: `type(scope): description`, with an optional scope and optional `!` for breaking changes.
- Accepted types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- The sole exception is the repository's first commit, whose subject is exactly `Initial Commit`.
- Keep PR descriptions focused on the core premise and features, using one short paragraph or a few brief bullets plus a short validation line. Omit implementation inventories and work logs unless necessary for review. Use British English in documentation.
- Never commit secrets, certificates, local environment files, uploads or generated dependency directories.

## Engineering principles

Apply these principles while planning, implementing and reviewing every change across PHP, JavaScript, SCSS, templates, configuration and documentation. Before considering work complete, check it against each principle:

- **KISS:** Choose the simplest clear solution that meets the requirement; avoid needless layers and cleverness.
- **DRY:** Keep each piece of knowledge and behaviour in one appropriate place. Share genuine common logic, but do not create abstractions for superficial similarities.
- **SOLID:** Keep responsibilities focused, dependencies clear and interfaces small. Use extension points and abstractions where they improve maintainability, not as a goal in themselves.
- **YAGNI:** Build for current requirements and agreed plans. Avoid speculative features, configuration and generalisation.

- After a feature has been agreed and implemented, run the Ponytail `/ponytail-review` review on the completed changes before treating the feature as complete or opening a PR. Address actionable findings and report any deferred findings. Keep this review separate from automated lint and build checks.

## Branch and release workflow

- Create focused feature branches from the latest `dev`; target feature PRs at `dev` and squash-merge using their Conventional Commit titles.
- Release connected batches through a `dev` to `main` PR using a merge commit with a Conventional Commit subject. Preserve feature history and fast-forward `dev` to released `main` before new work.
- Version each repository independently. Tag only the approved release commit on `main`; published tags are immutable. Releases and deployments require explicit agreement.
- Keep `main` as the GitHub default branch. Avoid stacked PRs unless agreed; merge dependencies into `dev` first.
- The local approval requirement above applies before publishing new changes.

- Prefer returned markup built with `sprintf()` in PHP helpers. Use escaped `printf()` only at template or WordPress callback output boundaries, and `fwrite(STDOUT, ...)` in standalone CLI scripts; avoid direct `echo` statements. Escape dynamic values for their actual HTML, attribute or URL context. Formatting and returning values do not replace validation, capability checks or escaping.

- Document each PHP and JavaScript function/method with an adequate DocBlock/JSDoc: a concise purpose, descriptions for each parameter, and a return description/type when applicable. Add documentation to callbacks when their purpose or contract is not self-evident. Use `//` for ordinary single-line comments; reserve DocBlocks/JSDoc blocks for function documentation and multiline block comments for longer notes or required WordPress translator comments.

- Use BEM for custom HTML and SCSS classes: `block`, `block__element`, `block--modifier` or `block__element--modifier`. Preserve WordPress-owned classes and keep block metadata, saved markup and selectors aligned when renaming classes.

- Group SCSS declarations in this order: box model, background, layout, type, then other. Alphabetise properties within each group and separate non-empty groups with a blank line. Keep exceptions for fallbacks and shorthand/longhand dependencies together; document non-obvious exceptions. Apply this to new and edited rules, and refactor existing stylesheets separately.

- Prefer native WordPress blocks and editor controls wherever they meet the requirement. Use Page List for page navigation and Query Loop for dynamic card layouts; introduce custom blocks, shortcodes or renderers only for an agreed gap in core.

- Group CSS/SCSS declarations in this order: box model (dimensions, margins, padding, borders and sizing), background, layout (display, positioning, flex/grid and alignment), type (colour, fonts, line height and text properties), then other (effects, transforms, transitions and remaining properties). Alphabetise properties within each group, and separate non-empty groups with a blank line. Use blank lines to show the groups; do not add group-label comments. Preserve order where repeated declarations, fallbacks, or shorthand/longhand interactions depend on it; keep those declarations together and document non-obvious exceptions. This concerns CSS properties, not HTML attributes or selector order. Apply it to new and edited rules; handle existing stylesheets in a dedicated refactor.
