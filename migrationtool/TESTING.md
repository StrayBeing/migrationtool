# Testing plan

## Environments

1. Moodle 4.5.10+ (Build: 20260306)
2. Moodle 5.0.7 (Build: 20260420)

Install the same `local_migrationtool` version on both sites.

## Minimum functional tests

1. Export one empty course and import it from 4.5 to 5.0.
2. Export multiple courses from nested categories and verify the target category hierarchy.
3. Export a course containing Page, File, URL, Forum, Assignment and Quiz activities.
4. Verify that no source users are created or enrolled on the target site.
5. Verify that attempts, submissions, user completion, comments, logs and grade histories are absent.
6. Remove a required third-party activity plugin from the target and verify that simulation blocks migration.
7. Install the plugin in an older version on the target and verify that simulation reports an older target component version.
8. Modify one byte of an `.mbz` inside the ZIP and verify checksum rejection.
9. Add `../test.php` to the ZIP and verify package rejection.
10. Force restore failure and verify that the partially created course is deleted and the failure is reported.

## Measurements for the thesis

Record for each scenario:

- Package size.
- Number of courses and activities.
- Simulation duration.
- Migration duration.
- Number of warnings and errors.
- Number of migrated sections, resources and activities.
- Whether the course structure is complete.
