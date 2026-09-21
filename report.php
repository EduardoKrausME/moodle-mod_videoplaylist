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
 * report.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_videoplaylist\progress_manager;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videoplaylist', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoplaylist:viewreport', $context);
$PAGE->set_url('/mod/videoplaylist/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('reporttitle', 'videoplaylist'));
$PAGE->set_heading(format_string($course->fullname));
$manager = new progress_manager();
$videos = $manager->get_videos($playlist->id);
$students = get_enrolled_users($context, 'mod/videoplaylist:view', 0,
    'u.id,u.firstname,u.lastname,u.email', 'u.lastname,u.firstname');
$rows = [];
foreach ($students as $student) {
    if (has_capability('mod/videoplaylist:viewreport', $context, $student->id)) {
        continue;
    }
    $cells = [];
    foreach ($videos as $video) {
        $progress = $manager->get_progress($video->id, $student->id);
        $percent = $progress ? (float)$progress->percent : 0;
        $cells[] = ['percent' => round($percent, 2), 'completed' => $percent >= (float)$video->minpercent];
    }
    $rows[] = ['fullname' => fullname($student), 'email' => $student->email, 'videos' => $cells,
        'overall' => $manager->get_overall_percent($playlist->id, $student->id)];
}
$data = [
    'name' => format_string($playlist->name),
    'videos' => array_map(static fn($v) => ['title' => format_string($v->title), 'minpercent' => $v->minpercent], $videos),
    'students' => $rows,
    'backurl' => (new moodle_url('/mod/videoplaylist/view.php', ['id' => $cm->id]))->out(false),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoplaylist/report', $data);
echo $OUTPUT->footer();
