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
 * video_form.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoplaylist\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("{$CFG->libdir}/formslib.php");

/**
 * Class video_form.
 */
class video_form extends \moodleform {
    /**
     * Method definition.
     *
     * @return void Return value.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'videoid');
        $mform->setType('videoid', PARAM_INT);
        $mform->addElement('text', 'title', get_string('videotitle', 'videoplaylist'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description', get_string('videodescription', 'videoplaylist'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('select', 'source', get_string('videosource', 'videoplaylist'), [
            'upload' => get_string('sourceupload', 'videoplaylist'), 'url' => get_string('sourceurl', 'videoplaylist'),
            'youtube' => get_string('sourceyoutube', 'videoplaylist'), 'vimeo' => get_string('sourcevimeo', 'videoplaylist'),
        ]);
        $mform->addElement('url', 'sourceurl', get_string('videourl', 'videoplaylist'), ['size' => 70], ['usefilepicker' => false]);
        $mform->setType('sourceurl', PARAM_URL);
        $mform->hideIf('sourceurl', 'source', 'eq', 'upload');
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videoplaylist'), null, [
            'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video'],
        ]);
        $mform->hideIf('videofile', 'source', 'neq', 'upload');
        $mform->addElement('text', 'minpercent', get_string('minpercent', 'videoplaylist'), ['size' => 5]);
        $mform->setType('minpercent', PARAM_INT);
        $mform->setDefault('minpercent', 80);
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'videoplaylist'));
        $mform->setDefault('enabled', 1);
        $this->add_action_buttons();
    }

    /**
     * Method validation.
     *
     * @param mixed $data Parameter data.
     * @param mixed $files Parameter files.
     * @return array Return value.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['minpercent'] < 1 || (int)$data['minpercent'] > 100) {
            $errors['minpercent'] = get_string('errorpercent', 'videoplaylist');
        }
        if ($data['source'] !== 'upload' && empty($data['sourceurl'])) {
            $errors['sourceurl'] = get_string('required');
        }
        if (!empty($data['sourceurl']) && !in_array(parse_url($data['sourceurl'], PHP_URL_SCHEME), ['http', 'https'], true)) {
            $errors['sourceurl'] = get_string('invalidurl', 'videoplaylist');
        }
        return $errors;
    }
}
