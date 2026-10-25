# Aeon

Minimal portfolio regular plugin scaffold. No portfolio features or custom blocks are introduced yet.

Develop alongside `portfolio`, `obel`, `aeon` and `rune`. Use the site Makefile to run all tools inside containers. This repository has its own Composer lock and checks.

Run `composer install` and `composer lint` in the PHP tools container. CI also syntax-checks PHP. No behavioural tests are added to empty scaffolds.

For release, publish this repository separately and create a semantic version tag after checks pass. The site must require a real tag and update its production lock. Do not deploy a local path dependency.

## Contribution conventions

Use Conventional Commit subjects and PR titles, for example `feat(theme): add navigation` or `fix(blocks): correct editor styles`. The repository's first commit is the explicit exception: `Initial Commit`. PR CI validates titles and every commit in the PR. See `AGENTS.md` for repository guidance.
