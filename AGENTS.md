# Repository conventions

- Implement changes locally and avoid running the full review/check suite during ordinary iteration. Defer final review and checks until the user asks to open a PR; run them earlier only when the user asks for verification. Wait for explicit code approval before committing, pushing or opening/updating a PR.
- Use lowercase, hyphenated WordPress-style PHP filenames. Organise PHP classes in responsibility-based directories directly under the project root, without a PHP `src/` wrapper. Use namespaces and separate classes for new custom PHP functionality, with Composer classmap autoloading where needed.
- Use sentence case for package names: Aeon, Obel and Rune. Keep lowercase code identifiers and package slugs unchanged.
- Use Title Case for user-facing names, titles and headings, keeping short articles and conjunctions such as “a”, “and”, “in” and “the” lowercase. Use sentence case for user-facing descriptions and explanatory text.
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

- When the user asks to open a PR, run Ponytail `/ponytail-review` once on the final changes. Address actionable findings and report deferred findings. Do not repeat the full review during implementation; after review fixes, rerun only the affected checks.

## Branch and release workflow

- Create focused feature branches from the latest `dev`; target feature PRs at `dev` and squash-merge using their Conventional Commit titles.
- Release connected batches through a `dev` to `main` PR using a merge commit with a Conventional Commit subject. Preserve feature history and fast-forward `dev` to released `main` before new work.
- Version each repository independently. Tag only the approved release commit on `main`; published tags are immutable. Releases and deployments require explicit agreement.
- Track the work merged into each repository's `dev` branch and proactively recommend a semantic version bump when a substantive release batch has accumulated. Base the recommendation on the changes' impact, not a fixed PR or feature count, and explain the proposed bump before preparing release metadata.
- Keep reviewable changes in their corresponding local repository or GitHub PR. When an isolated worktree is useful, place it under the shared portfolio workspace's `.worktrees/<repo>-<purpose>` directory; do not leave review copies in generic temporary folders.
- Keep `main` as the GitHub default branch. Avoid stacked PRs unless agreed; merge dependencies into `dev` first.
- After a feature PR is merged, confirm no open PR depends on its branch and its local working tree is clean, then remove the local and remote feature branches. GitHub may delete the remote branch automatically; squash merges require verifying the merged PR before `git branch -D`. Run `git fetch --prune` to clear stale remote refs. Never delete `dev`, `main` or branches with unmerged work.
- The local approval requirement above applies before publishing new changes.

- Prefer returned markup built with `sprintf()` in PHP helpers. Use escaped `printf()` only at template or WordPress callback output boundaries, and `fwrite(STDOUT, ...)` in standalone CLI scripts; avoid direct `echo` statements. Escape dynamic values for their actual HTML, attribute or URL context. Formatting and returning values do not replace validation, capability checks or escaping.

- Document each PHP and JavaScript function/method with an adequate DocBlock/JSDoc: a concise purpose, descriptions for each parameter, and a return description/type when applicable. Add documentation to callbacks when their purpose or contract is not self-evident. Use `//` for ordinary single-line comments; reserve DocBlocks/JSDoc blocks for function documentation and multiline block comments for longer notes or required WordPress translator comments.
- Group JavaScript imports with comments: WordPress or external dependencies first, then internal dependencies, separated by a blank line. Keep PHP `use` declarations directly after the namespace and alphabetised within their logical groups.
- At final PR preparation, run one custom-code consistency pass and the relevant PHP, JavaScript, SCSS, build and test checks. Check import and comment conventions, user-facing casing, and unused imports and assets. Exclude WordPress core, vendor, generated, backup and third-party plugin files. After fixing findings, rerun only affected checks.

- Use BEM for custom HTML and SCSS classes: `block`, `block__element`, `block--modifier` or `block__element--modifier`. Preserve WordPress-owned classes and keep block metadata, saved markup and selectors aligned when renaming classes.

- Group SCSS declarations in this order: box model, background, layout, type, then other. Alphabetise properties within each group and separate non-empty groups with a blank line. Keep exceptions for fallbacks and shorthand/longhand dependencies together; document non-obvious exceptions. Apply this to new and edited rules, and refactor existing stylesheets separately.

- Prefer native WordPress blocks and editor controls wherever they meet the requirement. Use Page List for page navigation and Query Loop for dynamic card layouts; introduce custom blocks, shortcodes or renderers only for an agreed gap in core.

- Group CSS/SCSS declarations in this order: box model (dimensions, margins, padding, borders and sizing), background, layout (display, positioning, flex/grid and alignment), type (colour, fonts, line height and text properties), then other (effects, transforms, transitions and remaining properties). Alphabetise properties within each group, and separate non-empty groups with a blank line. Use blank lines to show the groups; do not add group-label comments. Preserve order where repeated declarations, fallbacks, or shorthand/longhand interactions depend on it; keep those declarations together and document non-obvious exceptions. This concerns CSS properties, not HTML attributes or selector order. Apply it to new and edited rules; handle existing stylesheets in a dedicated refactor.
