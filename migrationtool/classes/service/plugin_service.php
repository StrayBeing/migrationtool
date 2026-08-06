<?php
//plugin verifier, checks for other plugins installed for quizes, if there is a plugin from source instance but not in destination instance it will not let the migration to start.
namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class plugin_service {
    public function collect_for_courses(array $courseids): array {
        global $DB;

        $usage = [];
        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            $course = $DB->get_record('course', ['id' => $courseid], 'id,format', MUST_EXIST);
            $this->add_usage($usage, 'format_' . $course->format, $courseid);

            foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
                $this->add_usage($usage, 'mod_' . $cm->modname, $courseid);
            }

            $qtypes = $DB->get_fieldset_sql(
                "SELECT DISTINCT q.qtype
                   FROM {quiz} quiz
                   JOIN {quiz_slots} slot ON slot.quizid = quiz.id
                   JOIN {question_references} qref ON qref.itemid = slot.id
                    AND qref.component = 'mod_quiz'
                    AND qref.questionarea = 'slot'
                   JOIN {question_bank_entries} qbe ON qbe.id = qref.questionbankentryid
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                   JOIN {question} q ON q.id = qv.questionid
                  WHERE quiz.course = :courseid",
                ['courseid' => $courseid]
            );
            foreach ($qtypes as $qtype) {
                $this->add_usage($usage, 'qtype_' . $qtype, $courseid);
            }

            $context = \context_course::instance($courseid);
            $blocks = $DB->get_fieldset_select('block_instances', 'DISTINCT blockname',
                'parentcontextid = :contextid', ['contextid' => $context->id]);
            foreach ($blocks as $block) {
                $this->add_usage($usage, 'block_' . $block, $courseid);
            }
        }

        $manager = \core_plugin_manager::instance();
        $components = [];
        ksort($usage);
        foreach ($usage as $component => $courses) {
            $info = $manager->get_plugin_info($component);
            $components[] = [
                'component' => $component,
                'type' => strtok($component, '_'),
                'source' => $info ? $info->source : null,
                'version_db' => $info && $info->versiondb !== null ? (string)$info->versiondb : null,
                'version_disk' => $info && $info->versiondisk !== null ? (string)$info->versiondisk : null,
                'release' => $info ? $info->release : null,
                'requires' => $info && $info->versionrequires !== null ? (string)$info->versionrequires : null,
                'supported' => $info ? $info->pluginsupported : null,
                'incompatible' => $info && $info->pluginincompatible !== null ? (string)$info->pluginincompatible : null,
                'courses' => array_values(array_map('intval', array_keys($courses))),
            ];
        }
        return $components;
    }

    public function compare(array $sourcecomponents): array {
        $manager = \core_plugin_manager::instance();
        $results = [];
        foreach ($sourcecomponents as $source) {
            $component = clean_param($source['component'] ?? '', PARAM_COMPONENT);
            if ($component === '') {
                $results[] = [
                    'component' => (string)($source['component'] ?? ''),
                    'status' => 'error',
                    'message' => 'Invalid component name in manifest.',
                ];
                continue;
            }

            $target = $manager->get_plugin_info($component);
            if (!$target || !$target->rootdir) {
                $results[] = [
                    'component' => $component,
                    'status' => 'error',
                    'message' => 'Component is missing on the target site.',
                    'source_version' => $source['version_db'] ?? $source['version_disk'] ?? null,
                    'target_version' => null,
                    'courses' => $source['courses'] ?? [],
                ];
                continue;
            }

            $sourceversion = $source['version_db'] ?? $source['version_disk'] ?? null;
            $targetversion = $target->versiondb ?? $target->versiondisk ?? null;
            $status = 'ok';
            $message = 'Component is installed.';
            if ($sourceversion !== null && $targetversion !== null && version_compare((string)$targetversion, (string)$sourceversion, '<')) {
                $status = 'error';
                $message = 'The target component version is older than the source version.';
            } else if ($sourceversion !== null && $targetversion !== null && (string)$targetversion !== (string)$sourceversion) {
                if (($source['source'] ?? null) === \core_plugin_manager::PLUGIN_SOURCE_STANDARD &&
                        $target->source === \core_plugin_manager::PLUGIN_SOURCE_STANDARD) {
                    $message = 'The standard component is available in the target Moodle version.';
                } else {
                    $status = 'warning';
                    $message = 'The extension version differs; the target version is not older.';
                }
            }
            if (($source['source'] ?? null) !== null && $target->source !== ($source['source'] ?? null) && $status === 'ok') {
                $status = 'warning';
                $message = 'The component source differs between sites.';
            }

            $results[] = [
                'component' => $component,
                'status' => $status,
                'message' => $message,
                'source_version' => $sourceversion,
                'target_version' => $targetversion !== null ? (string)$targetversion : null,
                'source_release' => $source['release'] ?? null,
                'target_release' => $target->release,
                'courses' => $source['courses'] ?? [],
            ];
        }
        return $results;
    }

    private function add_usage(array &$usage, string $component, int $courseid): void {
        if (!isset($usage[$component])) {
            $usage[$component] = [];
        }
        $usage[$component][$courseid] = true;
    }
}
