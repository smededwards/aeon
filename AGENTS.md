# Repository conventions

- Use sentence case for package names: Aeon, Obel and Rune. Keep lowercase code identifiers and package slugs unchanged.
- Use Conventional Commits for every new commit and PR title: `type(scope): description`, with an optional scope and optional `!` for breaking changes.
- Accepted types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
- The sole exception is the repository's first commit, whose subject is exactly `Initial Commit`.
- Keep PR descriptions concise and include relevant validation. Use British English in documentation.
- Never commit secrets, certificates, local environment files, uploads or generated dependency directories.
