<?php
//scope verify, test version
namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class scope_service {
    //diasbled fields for project
    private const ALWAYS_EXCLUDED = [
        'groups',
        'users',
        'enrolments',
        'role_assignments',
        'user_completion',
        'logs',
        'comments',
        'grade_histories',
        'xapi_user_state',
    ];

    //from export data
    public static function from_form(\stdClass $data): array {
        return self::normalise([
            'course_structure' => true,
            'activities' => !empty($data->scopeactivities),
            'files' => !empty($data->scopefiles),
            'question_bank' => !empty($data->scopequestionbank),
            'blocks' => !empty($data->scopeblocks),
        ]);
    }

    //returns complete array.
    public static function normalise(array $scope): array {
        $normalised = [
            'course_structure' => true,
            'activities' => array_key_exists('activities', $scope) ? (bool)$scope['activities'] : true,
            'files' => array_key_exists('files', $scope) ? (bool)$scope['files'] : true,
            'question_bank' => array_key_exists('question_bank', $scope) ? (bool)$scope['question_bank'] : true,
            'blocks' => array_key_exists('blocks', $scope) ? (bool)$scope['blocks'] : true,
        ];

        foreach (self::ALWAYS_EXCLUDED as $key) {
            $normalised[$key] = false;
        }

        return $normalised;
    }

    //verify
    public static function validate_package_scope(array $scope): void {
        if (!array_key_exists('course_structure', $scope) || !is_bool($scope['course_structure']) ||
                !$scope['course_structure']) {
            throw new \moodle_exception('invalidpackage', 'local_migrationtool');
        }

        foreach (['activities', 'files', 'question_bank', 'blocks'] as $key) {
            if (!array_key_exists($key, $scope) || !is_bool($scope[$key])) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }

        foreach (self::ALWAYS_EXCLUDED as $key) {
            if (!array_key_exists($key, $scope) || !is_bool($scope[$key]) || $scope[$key]) {
                throw new \moodle_exception('invalidpackage', 'local_migrationtool');
            }
        }
    }

    //map to backup and restore
    public static function moodle_settings(array $scope): array {
        $scope = self::normalise($scope);

        return [
            'activities' => $scope['activities'],
            'files' => $scope['files'],
            'questionbank' => $scope['question_bank'],
            'blocks' => $scope['blocks'],
        ];
    }
}
