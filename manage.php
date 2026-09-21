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
 * manage.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$videoid = optional_param('videoid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoplaylist', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoplaylist:managevideos', $context);
$PAGE->set_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managevideos', 'videoplaylist'));
$PAGE->set_heading(format_string($course->fullname));
if ($action === 'delete' && $videoid && confirm_sesskey()) {
    $video = $DB->get_record('videoplaylist_videos', ['id' => $videoid, 'playlistid' => $playlist->id], '*', MUST_EXIST);
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if ($confirm) {
        get_file_storage()->delete_area_files($context->id, 'mod_videoplaylist', 'video', $video->id);
        $DB->delete_records('videoplaylist_sessions', ['videoid' => $video->id]);
        $DB->delete_records('videoplaylist_progress', ['videoid' => $video->id]);
        $DB->delete_records('videoplaylist_videos', ['id' => $video->id]);
        redirect(new moodle_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]), get_string('videodeleted', 'videoplaylist'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmdeletevideo', 'videoplaylist', format_string($video->title)),
        new moodle_url('/mod/videoplaylist/manage.php',
            ['id' => $cm->id, 'action' => 'delete', 'videoid' => $video->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        new moodle_url('/mod/videoplaylist/manage.php',
            ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}
if (in_array($action, ['up', 'down'], true) && $videoid && confirm_sesskey()) {
    $videos = array_values($DB->get_records('videoplaylist_videos', ['playlistid' => $playlist->id], 'sortorder ASC, id ASC'));
    foreach ($videos as $i => $video) {
        if ((int)$video->id === $videoid) {
            $j = $action === 'up' ? $i - 1 : $i + 1;
            if (isset($videos[$j])) {
                $a = $video->sortorder;
                $video->sortorder = $videos[$j]->sortorder;
                $videos[$j]->sortorder = $a;
                if ($video->sortorder === $videos[$j]->sortorder) {
                    $video->sortorder = $j;
                    $videos[$j]->sortorder = $i;
                }
                $DB->update_record('videoplaylist_videos', $video);
                $DB->update_record('videoplaylist_videos', $videos[$j]);
            }
            break;
        }
    }
    redirect(new moodle_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]));
}
$videos = array_values($DB->get_records('videoplaylist_videos', ['playlistid' => $playlist->id], 'sortorder ASC, id ASC'));
$data = [
    'name' => format_string($playlist->name),
    'addurl' => (new moodle_url('/mod/videoplaylist/video.php', ['id' => $cm->id]))->out(false),
    'backurl' => (new moodle_url('/mod/videoplaylist/view.php', ['id' => $cm->id]))->out(false),
    'videos' => [],
];
foreach ($videos as $video) {
    $data['videos'][] = [
        'title' => format_string($video->title),
        'source' => $video->source, 'minpercent' => $video->minpercent, 'enabled' => $video->enabled,
        'editurl' => (new moodle_url('/mod/videoplaylist/video.php',
            ['id' => $cm->id, 'videoid' => $video->id]))->out(false),
        'upurl' => (new moodle_url('/mod/videoplaylist/manage.php',
            ['id' => $cm->id, 'action' => 'up', 'videoid' => $video->id, 'sesskey' => sesskey()]))->out(false),
        'downurl' => (new moodle_url('/mod/videoplaylist/manage.php',
            ['id' => $cm->id, 'action' => 'down', 'videoid' => $video->id, 'sesskey' => sesskey()]))->out(false),
        'deleteurl' => (new moodle_url('/mod/videoplaylist/manage.php',
            ['id' => $cm->id, 'action' => 'delete', 'videoid' => $video->id, 'sesskey' => sesskey()]))->out(false),
    ];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoplaylist/manage', $data);
echo $OUTPUT->footer();
