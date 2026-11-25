# Changelog

All notable changes to Aeon are documented here.

## [Unreleased]

### Changed

- Clarify Flux status output with separate site and target environments and handshake results.

## [0.5.0] - 2026-11-25

### Added

- Add Flux environment synchronisation for signed Post and Page payloads, field-level previews, local revisions and guarded remote writes.
- Add Flux to the Aeon settings page as a dedicated tab.
- Use the existing Aeon environment URLs for Flux connections instead of duplicating them.

## [0.4.1] - 2026-11-23

### Security

- Update the WordPress build tooling and override the vulnerable serialize-javascript transitive dependency.

## [0.4.0] - 2026-11-08

### Added

- Duplicate Posts and Pages from the WordPress admin.
- Hide the Front-End admin bar while scrolling, with keyboard and scroll-direction behaviour.

### Changed

- Improve contact delivery metadata and the success receipt URL.
- Refine admin settings and dashboard palettes, and move editor controls to Rune.

### Fixed

- Removed emoji assets from the Front-End.

### Documentation

- Standardise PHP filenames, readable markup and release guidance.
