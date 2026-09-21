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
 * restore_videoplaylist_stepslib.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class restore_videoplaylist_activity_structure_step.
 */
class restore_videoplaylist_activity_structure_step extends restore_activity_structure_step {
    /**
     * Method define_structure.
     *
     * @return array Return value.
     */
    protected function define_structure(): array {
        $paths = [new restore_path_element('videoplaylist', '/activity/videoplaylist'),
            new restore_path_element('videoplaylist_video', '/activity/videoplaylist/videos/video')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videoplaylist_progress', '/activity/videoplaylist/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Method process_videoplaylist.
     *
     * @param array $data Parameter data.
     * @return void Return value.
     */
    protected function process_videoplaylist(array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->id = $DB->insert_record('videoplaylist', $data);
        $this->apply_activity_instance($data->id);
        $this->set_mapping('videoplaylist', $oldid, $data->id, true);
    }

    /**
     * Method process_videoplaylist_video.
     *
     * @param array $data Parameter data.
     * @return void Return value.
     */
    protected function process_videoplaylist_video(array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->playlistid = $this->get_new_parentid('videoplaylist');
        $data->id = $DB->insert_record('videoplaylist_videos', $data);
        $this->set_mapping('videoplaylist_video', $oldid, $data->id, true);
    }

    /**
     * Method process_videoplaylist_progress.
     *
     * @param array $data Parameter data.
     * @return void Return value.
     */
    protected function process_videoplaylist_progress(array $data): void {
        global $DB;
        $data = (object)$data;
        $data->playlistid = $this->get_new_parentid('videoplaylist');
        $data->videoid = $this->get_mappingid('videoplaylist_video', $data->videoid, 0);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if ($data->videoid && $data->userid) {
            $DB->insert_record('videoplaylist_progress', $data);
        }
    }

    /**
     * Method after_execute.
     *
     * @return void Return value.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videoplaylist', 'video', 'videoplaylist_video');
    }
}
