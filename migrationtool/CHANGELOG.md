# Changelog

## 0.2.0 — 2026-08-06

- Reworked export and import around a versioned `manifest.json`.
- Explicitly excluded user-related data in backup and restore settings.
- Added secure ZIP validation and SHA-256 integrity checks.
- Added Moodle branch and plugin/component compatibility analysis.
- Added a no-course-write simulation step.
- Replaced direct category database inserts with `core_course_category::create()`.
- Preserved the source category hierarchy.
- Replaced CLI `exec()` restore with `restore_controller`.
- Added cleanup of partially created courses after a restore failure.
- Added JSON export, simulation and migration reports.
- Added Moodle forms and sesskey-protected actions/downloads.
- Added Polish and English language files.
