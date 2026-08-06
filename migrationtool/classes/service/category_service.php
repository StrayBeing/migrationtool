<?php

namespace local_migrationtool\service;

defined('MOODLE_INTERNAL') || die();

class category_service {
    public function export_for_courses(array $courseids): array {
        global $DB;

        $categories = [];
        foreach ($courseids as $courseid) {
            $categoryid = (int)$DB->get_field('course', 'category', ['id' => (int)$courseid], MUST_EXIST);
            while ($categoryid > 0 && !isset($categories[$categoryid])) {
                $category = $DB->get_record('course_categories', ['id' => $categoryid],
                    'id,name,idnumber,description,descriptionformat,parent,depth,path,visible', MUST_EXIST);
                $categories[$categoryid] = [
                    'id' => (int)$category->id,
                    'name' => $category->name,
                    'idnumber' => $category->idnumber,
                    'description' => $category->description,
                    'descriptionformat' => (int)$category->descriptionformat,
                    'parent' => (int)$category->parent,
                    'depth' => (int)$category->depth,
                    'path' => $category->path,
                    'visible' => (int)$category->visible,
                ];
                $categoryid = (int)$category->parent;
            }
        }
        usort($categories, static fn(array $a, array $b): int => [$a['depth'], $a['id']] <=> [$b['depth'], $b['id']]);
        return array_values($categories);
    }

    public function plan(array $sourcecategories): array {
        $map = [];
        $items = [];
        usort($sourcecategories, static fn(array $a, array $b): int => [$a['depth'], $a['id']] <=> [$b['depth'], $b['id']]);

        foreach ($sourcecategories as $source) {
            $sourceid = (int)$source['id'];
            $sourceparent = (int)($source['parent'] ?? 0);
            $targetparent = $sourceparent === 0 ? 0 : ($map[$sourceparent] ?? null);
            if ($targetparent === null) {
                $items[] = [
                    'source_id' => $sourceid,
                    'name' => $source['name'],
                    'status' => 'error',
                    'action' => 'blocked',
                    'message' => 'The source parent category is missing from the manifest.',
                ];
                continue;
            }

            $existing = $this->find_existing($source, $targetparent);
            if ($existing) {
                $map[$sourceid] = (int)$existing->id;
                $items[] = [
                    'source_id' => $sourceid,
                    'target_id' => (int)$existing->id,
                    'target_parent' => $targetparent,
                    'name' => $source['name'],
                    'status' => 'ok',
                    'action' => 'reuse',
                    'message' => 'An existing target category will be used.',
                ];
            } else {
                $virtualid = -$sourceid;
                $map[$sourceid] = $virtualid;
                $items[] = [
                    'source_id' => $sourceid,
                    'target_id' => $virtualid,
                    'target_parent' => $targetparent,
                    'name' => $source['name'],
                    'status' => 'ok',
                    'action' => 'create',
                    'message' => 'The category will be created during migration.',
                ];
            }
        }
        return ['map' => $map, 'items' => $items];
    }

    public function restore(array $sourcecategories): array {
        global $DB;

        $map = [];
        $items = [];
        usort($sourcecategories, static fn(array $a, array $b): int => [$a['depth'], $a['id']] <=> [$b['depth'], $b['id']]);

        foreach ($sourcecategories as $source) {
            $sourceid = (int)$source['id'];
            $sourceparent = (int)($source['parent'] ?? 0);
            $targetparent = $sourceparent === 0 ? 0 : ($map[$sourceparent] ?? null);
            if ($targetparent === null) {
                throw new \moodle_exception('invaliddata', 'error', '', null, 'Missing parent category mapping.');
            }

            $existing = $this->find_existing($source, $targetparent);
            if ($existing) {
                $targetid = (int)$existing->id;
                $action = 'reuse';
            } else {
                $data = (object)[
                    'name' => $source['name'],
                    'parent' => $targetparent,
                    'description' => $source['description'] ?? '',
                    'descriptionformat' => (int)($source['descriptionformat'] ?? FORMAT_HTML),
                    'visible' => (int)($source['visible'] ?? 1),
                ];
                $idnumber = trim((string)($source['idnumber'] ?? ''));
                if ($idnumber !== '' && !$DB->record_exists('course_categories', ['idnumber' => $idnumber])) {
                    $data->idnumber = $idnumber;
                }
                $created = \core_course_category::create($data);
                $targetid = (int)$created->id;
                $action = 'create';
            }
            $map[$sourceid] = $targetid;
            $items[] = [
                'source_id' => $sourceid,
                'target_id' => $targetid,
                'target_parent' => $targetparent,
                'name' => $source['name'],
                'status' => 'success',
                'action' => $action,
            ];
        }
        return ['map' => $map, 'items' => $items];
    }

    private function find_existing(array $source, int $parentid): ?\core_course_category {
        global $DB;

        $idnumber = trim((string)($source['idnumber'] ?? ''));
        if ($idnumber !== '') {
            $byidnumber = $DB->get_record('course_categories', ['idnumber' => $idnumber], 'id');
            if ($byidnumber) {
                return \core_course_category::get($byidnumber->id, MUST_EXIST, true);
            }
        }

        $record = $DB->get_record('course_categories', [
            'parent' => $parentid,
            'name' => $source['name'],
        ], 'id', IGNORE_MULTIPLE);
        return $record ? \core_course_category::get($record->id, MUST_EXIST, true) : null;
    }
}
