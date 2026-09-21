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
 * view.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_videoplaylist\progress_manager;

$id = required_param('id', PARAM_INT);
$videoid = optional_param('video', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoplaylist', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoplaylist:view', $context);
$PAGE->set_url('/mod/videoplaylist/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($playlist->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('mod_videoplaylist/player', 'init');
$manager = new progress_manager();
$videos = $manager->get_videos($playlist->id);
$items = [];
$current = null;
foreach ($videos as $video) {
    $progress = $manager->get_progress($video->id, $USER->id);
    $unlocked = $manager->is_video_unlocked($playlist, $video, $USER->id);
    $percent = $progress ? (float)$progress->percent : 0.0;
    $completed = $percent >= (float)$video->minpercent;
    $item = [
        'id' => $video->id, 'title' => format_string($video->title), 'description' => s($video->description),
        'percent' => round($percent, 2), 'completed' => $completed, 'unlocked' => $unlocked,
        'locked' => !$unlocked, 'notstarted' => !$progress, 'current' => false,
        'url' => (new moodle_url('/mod/videoplaylist/view.php', ['id' => $cm->id, 'video' => $video->id]))->out(false),
        'minpercent' => (int)$video->minpercent,
    ];
    $items[$video->id] = $item;
}
if ($videoid && isset($items[$videoid]) && $items[$videoid]['unlocked']) {
    $currentid = $videoid;
} else {
    $currentid = 0;
    foreach ($items as $vid => $item) {
        if ($item['unlocked'] && !$item['completed']) {
            $currentid = $vid;
            break;
        }
    }
    if (!$currentid) {
        foreach ($items as $vid => $item) {
            if ($item['unlocked']) {
                $currentid = $vid;
                break;
            }
        }
    }
}
if ($currentid) {
    $video = $DB->get_record('videoplaylist_videos', ['id' => $currentid], '*', MUST_EXIST);
    $progress = $manager->get_progress($video->id, $USER->id);
    $src = '';
    if ($video->source === 'upload') {
        $files = get_file_storage()->get_area_files($context->id, 'mod_videoplaylist', 'video', $video->id, 'filename', false);
        if ($files) {
            $file = reset($files);
            $src = moodle_url::make_pluginfile_url($context->id,
                'mod_videoplaylist', 'video', $video->id, $file->get_filepath(),
                $file->get_filename())->out(false);
        }
    } else {
        $src = (string)$video->sourceurl;
    }
    $current = [
        'id' => $video->id,
        'title' => format_string($video->title),
        'description' => format_text($video->description, FORMAT_PLAIN),
        'source' => $video->source, 'src' => $src,
        'lastposition' => $progress && $playlist->resumeplayback ? (float)$progress->lastposition : 0,
        'percent' => $progress ? (float)$progress->percent : 0,
        'segments' => $progress ? (string)$progress->watchedsegments : '[]',
        'cmid' => $cm->id, 'allowseek' => !empty($playlist->allowseek),
    ];
    $items[$currentid]['current'] = true;
}
$overall = $manager->get_overall_percent($playlist->id, $USER->id);
$data = [
    'name' => format_string($playlist->name), 'intro' => format_module_intro('videoplaylist', $playlist, $cm->id, false),
    'videos' => array_values($items), 'hasvideos' => !empty($items), 'current' => $current,
    'overallpercent' => $overall, 'manageurl' => (new moodle_url('/mod/videoplaylist/manage.php', ['id' => $cm->id]))->out(false),
    'reporturl' => (new moodle_url('/mod/videoplaylist/report.php', ['id' => $cm->id]))->out(false),
    'canmanage' => has_capability('mod/videoplaylist:managevideos', $context),
    'canreport' => has_capability('mod/videoplaylist:viewreport', $context),
];
$event = \mod_videoplaylist\event\course_module_viewed::create(['objectid' => $playlist->id, 'context' => $context]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoplaylist/view', $data);
echo $OUTPUT->footer();
