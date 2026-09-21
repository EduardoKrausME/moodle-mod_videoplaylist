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
 * video.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
$id = required_param('id', PARAM_INT);
$videoid = optional_param('videoid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoplaylist', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoplaylist:managevideos', $context);
$video = $videoid ? $DB->get_record('videoplaylist_videos',
    ['id' => $videoid, 'playlistid' => $playlist->id], '*', MUST_EXIST) : null;
$PAGE->set_url('/mod/videoplaylist/video.php', ['id' => $cm->id, 'videoid' => $videoid]);
$PAGE->set_title($video ? get_string('editvideo', 'videoplaylist') : get_string('addvideo', 'videoplaylist'));
$PAGE->set_heading(format_string($course->fullname));
$form = new \mod_videoplaylist\form\video_form();
$draftid = file_get_submitted_draft_itemid('videofile');
if ($video) {
    file_prepare_draft_area($draftid, $context->id, 'mod_videoplaylist', 'video', $video->id, ['subdirs' => 0, 'maxfiles' => 1]);
    $video->videofile = $draftid;
    $video->cmid = $cm->id;
    $video->videoid = $video->id;
    $form->set_data($video);
} else {
    $form->set_data((object)['cmid' => $cm->id, 'videoid' => 0, 'videofile' => $draftid]);
}
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]));
}
if ($data = $form->get_data()) {
    $now = time();
    if ($video) {
        $video->title = $data->title;
        $video->description = $data->description;
        $video->source = $data->source;
        $video->sourceurl = $data->source === 'upload' ? '' : $data->sourceurl;
        $video->minpercent = $data->minpercent;
        $video->enabled = $data->enabled;
        $video->timemodified = $now;
        $DB->update_record('videoplaylist_videos', $video);
    } else {
        $max = $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) FROM {videoplaylist_videos} WHERE playlistid = ?', [$playlist->id]);
        $video = (object)['playlistid' => $playlist->id, 'title' => $data->title, 'description' => $data->description,
            'source' => $data->source, 'sourceurl' => $data->source === 'upload' ? '' : $data->sourceurl,
            'minpercent' => $data->minpercent, 'sortorder' => ((int)$max) + 1, 'enabled' => $data->enabled,
            'timecreated' => $now, 'timemodified' => $now];
        $video->id = $DB->insert_record('videoplaylist_videos', $video);
    }
    if ($data->source === 'upload') {
        file_save_draft_area_files($data->videofile, $context->id, 'mod_videoplaylist',
            'video', $video->id, ['subdirs' => 0, 'maxfiles' => 1]);
    } else {
        get_file_storage()->delete_area_files($context->id, 'mod_videoplaylist', 'video', $video->id);
    }
    redirect(new moodle_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]), get_string('videosaved', 'videoplaylist'));
}
echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->footer();
