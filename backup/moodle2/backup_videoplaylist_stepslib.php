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
 * backup_videoplaylist_stepslib.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class backup_videoplaylist_activity_structure_step.
 */
class backup_videoplaylist_activity_structure_step extends backup_activity_structure_step {
    /**
     * Method define_structure.
     *
     * @return mixed Return value.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $activity = new backup_nested_element('videoplaylist', ['id'],
            [
                'name', 'intro', 'introformat', 'sequential', 'allowseek', 'resumeplayback',
                'completionpercent', 'grade', 'timecreated', 'timemodified',
            ]);
        $videos = new backup_nested_element('videos');
        $video = new backup_nested_element('video', ['id'],
            ['title', 'description', 'source', 'sourceurl', 'minpercent', 'sortorder', 'enabled', 'timecreated', 'timemodified']);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'],
            [
                'videoid', 'userid', 'duration', 'lastposition', 'uniquewatched', 'totalwatchtime',
                'percent', 'watchedsegments', 'completed', 'timecreated', 'timemodified',
            ]);
        $activity->add_child($videos);
        $videos->add_child($video);
        $activity->add_child($progresses);
        $progresses->add_child($progress);
        $activity->set_source_table('videoplaylist', ['id' => backup::VAR_ACTIVITYID]);
        $video->set_source_table('videoplaylist_videos', ['playlistid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $progress->set_source_table('videoplaylist_progress', ['playlistid' => backup::VAR_ACTIVITYID]);
        }
        $progress->annotate_ids('user', 'userid');
        $video->annotate_files('mod_videoplaylist', 'video', 'id');
        return $this->prepare_activity_structure($activity);
    }
}
