# Moodle Course Migration Tool

Local Moodle plugin for manually transferring selected courses in a ZIP package between Moodle 4.5 and Moodle 5.0 installations.

## Implemented scope

- Multiple-course export to one ZIP package.
- Native Moodle `.mbz` backup files.
- No users, enrolments, role assignments, user completion, logs, comments, grade histories or xAPI user state.
- Category hierarchy metadata and recreation through the Moodle category API.
- Component inventory for course formats, activities, question types and blocks.
- ZIP path validation, package manifest, SHA-256 checksums and size verification.
- Simulation/preflight before migration.
- Blocking of newer-to-older Moodle branch migration.
- Per-course rollback by deleting a course created by a failed restore.
- JSON export, simulation and migration reports.

## Installation

Copy the `migrationtool` directory to:

`moodle/local/migrationtool`

Then visit **Site administration → Notifications**.

## Intended version path

- Source: Moodle 4.5.10+ (Build: 20260306)
- Target: Moodle 5.0.7 (Build: 20260420)

The manifest uses the actual `$CFG->version`, `$CFG->release` and `$CFG->branch` values at runtime.

## Important limitation

Migration runs synchronously in the browser. Large courses may require higher PHP execution and upload limits. Moving execution to Moodle ad-hoc tasks is a recommended later extension.
