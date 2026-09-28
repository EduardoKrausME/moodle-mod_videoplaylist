<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * lib.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoplaylist\progress_manager;

function videoplaylist_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

function videoplaylist_add_instance(stdClass $data, ?mod_videoplaylist_mod_form $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $id = $DB->insert_record('videoplaylist', $data);
    $data->id = $id;
    videoplaylist_grade_item_update($data);
    return $id;
}

function videoplaylist_update_instance(stdClass $data, ?mod_videoplaylist_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('videoplaylist', $data);
    videoplaylist_grade_item_update($data);
    return $result;
}

function videoplaylist_delete_instance(int $id): bool {
    global $DB;
    $playlist = $DB->get_record('videoplaylist', ['id' => $id]);
    if (!$playlist) {
        return false;
    }
    $cm = get_coursemodule_from_instance('videoplaylist', $id, $playlist->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_videoplaylist');
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('videoplaylist_sessions', ['playlistid' => $id]);
    $DB->delete_records('videoplaylist_progress', ['playlistid' => $id]);
    $DB->delete_records('videoplaylist_videos', ['playlistid' => $id]);
    $DB->delete_records('videoplaylist', ['id' => $id]);
    $transaction->allow_commit();
    videoplaylist_grade_item_delete($playlist);
    return true;
}

function mod_videoplaylist_pluginfile($course, $cm, $context, string $filearea, array $args,
                                      bool $forcedownload, array $options = []): bool {
    global $DB;
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'video') {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videoplaylist:view', $context);
    $videoid = (int)array_shift($args);
    $video = $DB->get_record('videoplaylist_videos', ['id' => $videoid, 'playlistid' => $cm->instance], '*', MUST_EXIST);
    if ($video->source !== 'upload') {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_videoplaylist', 'video', $videoid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function videoplaylist_get_file_areas($course, $cm, $context): array {
    return ['video' => get_string('videofile', 'videoplaylist')];
}

function videoplaylist_grade_item_update(stdClass $playlist, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = [
        'itemname' => clean_param($playlist->name, PARAM_NOTAGS),
        'gradetype' => ((float)$playlist->grade > 0) ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
        'grademin' => 0,
        'grademax' => 100,
    ];
    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/videoplaylist', $playlist->course, 'mod', 'videoplaylist', $playlist->id, 0, $grades, $item);
}

function videoplaylist_grade_item_delete(stdClass $playlist): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/videoplaylist', $playlist->course, 'mod', 'videoplaylist', $playlist->id, 0, null, ['deleted' => 1]);
}

function videoplaylist_update_grades(stdClass $playlist, int $userid = 0, bool $nullifnone = true): void {
    global $DB;
    $manager = new progress_manager();
    $grades = [];
    if ($userid) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => $manager->get_overall_percent($playlist->id, $userid)];
    } else {
        $userids = $DB->get_fieldset_select('videoplaylist_progress',
            'DISTINCT userid', 'playlistid = :id', ['id' => $playlist->id]);
        foreach ($userids as $uid) {
            $grades[$uid] = (object)['userid' => $uid, 'rawgrade' => $manager->get_overall_percent($playlist->id, $uid)];
        }
    }
    if (!$grades && $userid && $nullifnone) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => null];
    }
    videoplaylist_grade_item_update($playlist, $grades);
}

function videoplaylist_get_coursemodule_info(stdClass $cm): cached_cm_info|null {
    global $DB;
    $playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], 'id,name,intro,introformat,completionpercent');
    if (!$playlist) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $playlist->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('videoplaylist', $playlist, $cm->id, false);
    }
    if ((int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules'] = ['completionpercent' => (int)$playlist->completionpercent];
    }
    return $info;
}

function videoplaylist_get_completion_active_rule_descriptions($cm): array {
    global $DB;

    if ((int)$cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $completionpercent = $cm->customdata['customcompletionrules']['completionpercent'] ?? null;
    if ($completionpercent === null && !empty($cm->instance)) {
        $completionpercent = $DB->get_field('videoplaylist', 'completionpercent', ['id' => $cm->instance]);
    }
    if (empty($completionpercent)) {
        return [];
    }

    return [get_string('completiondetail:percent', 'videoplaylist', $completionpercent)];
}

function videoplaylist_get_completion_state($course, $cm, int $userid, bool $type): bool {
    global $DB;
    $playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
    return (new progress_manager())->get_overall_percent($playlist->id, $userid) >= (float)$playlist->completionpercent;
}

/**
 * Add Video Playlist options to the course reset form.
 *
 * @param MoodleQuickForm $mform Reset form.
 * @return void
 */
function videoplaylist_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'videoplaylistheader', get_string('modulenameplural', 'videoplaylist'));
    $mform->addElement('static', 'videoplaylistdelete', get_string('delete'));
    $mform->addElement('advcheckbox', 'reset_videoplaylist_progress', get_string('resetprogress', 'videoplaylist'));
}

/**
 * Default values for the course reset form.
 *
 * @param stdClass $course Course record.
 * @return array
 */
function videoplaylist_reset_course_form_defaults(stdClass $course): array {
    return ['reset_videoplaylist_progress' => 1];
}

/**
 * Reset learner data for all Video Playlist activities in a course.
 *
 * @param stdClass $data Course reset data.
 * @return array Reset status.
 */
function videoplaylist_reset_userdata(stdClass $data): array {
    global $DB;

    if (empty($data->reset_videoplaylist_progress)) {
        return [];
    }

    $playlistids = $DB->get_fieldset_select(
        'videoplaylist',
        'id',
        'course = :courseid',
        ['courseid' => $data->courseid]
    );

    if ($playlistids) {
        $DB->delete_records_list('videoplaylist_sessions', 'playlistid', $playlistids);
        $DB->delete_records_list('videoplaylist_progress', 'playlistid', $playlistids);
    }

    if (empty($data->reset_gradebook_grades)) {
        videoplaylist_reset_gradebook($data->courseid);
    }

    return [[
        'component' => get_string('modulenameplural', 'videoplaylist'),
        'item' => get_string('resetprogress', 'videoplaylist'),
        'error' => false,
    ]];
}

/**
 * Remove all Video Playlist grades from the gradebook for a course.
 *
 * @param int $courseid Course ID.
 * @return void
 */
function videoplaylist_reset_gradebook(int $courseid): void {
    global $DB;

    $playlists = $DB->get_records('videoplaylist', ['course' => $courseid]);
    foreach ($playlists as $playlist) {
        videoplaylist_grade_item_update($playlist, 'reset');
    }
}
