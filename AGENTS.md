# Repository conventions

- Make and test changes locally. Wait for explicit code approval before committing, pushing or opening/updating a PR.
- Use lowercase, hyphenated WordPress-style PHP filenames. Organise PHP classes in responsibility-based directories directly under the project root, without a PHP `src/` wrapper. Use namespaces and separate classes for new custom PHP functionality, with Composer classmap autoloading where needed.
- Use sentence case for package names: Aeon, Obel and Rune. Keep lowercase code identifiers and package slugs unchanged.
- Use Conventional Commits for every new commit and PR title: `type(scope): description`, with an optional scope and optional `!` for breaking changes.
- Accepted types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- The sole exception is the repository's first commit, whose subject is exactly `Initial Commit`.
- Keep PR descriptions concise and include relevant validation. Use British English in documentation.
- Never commit secrets, certificates, local environment files, uploads or generated dependency directories.
