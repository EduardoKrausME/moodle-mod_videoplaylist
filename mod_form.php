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
 * mod_form.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Class mod_videoplaylist_mod_form.
 */
class mod_videoplaylist_mod_form extends moodleform_mod {
    /**
     * Method definition.
     *
     * @return void Return value.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videoplaylistname', 'videoplaylist'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('header', 'playlistsettings', get_string('playlistsettings', 'videoplaylist'));
        $mform->addElement('selectyesno', 'sequential', get_string('sequential', 'videoplaylist'));
        $mform->setDefault('sequential', 0);
        $mform->addElement('selectyesno', 'allowseek', get_string('allowseek', 'videoplaylist'));
        $mform->setDefault('allowseek', 1);
        $mform->addElement('select', 'resumeplayback', get_string('resumeplayback', 'videoplaylist'), [
            1 => get_string('resumeautomatic', 'videoplaylist'),
            0 => get_string('resumefromstart', 'videoplaylist'),
        ]);
        $mform->setDefault('resumeplayback', 1);
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Method add_completion_rules.
     *
     * @return array Return value.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $field = 'completionpercent_videoplaylist';
        $mform->addElement('text', $field, get_string('completionpercent', 'videoplaylist'), ['size' => 5]);
        $mform->setType($field, PARAM_INT);
        $mform->setDefault($field, 80);
        $mform->addRule($field, null, 'numeric', null, 'client');
        return [$field];
    }

    /**
     * Method completion_rule_enabled.
     *
     * @param mixed $data Parameter data.
     * @return bool Return value.
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionpercent_videoplaylist']);
    }

    /**
     * Method data_preprocessing.
     *
     * @param mixed $defaultvalues Parameter defaultvalues.
     * @return void Return value.
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (array_key_exists('completionpercent', $defaultvalues)) {
            $defaultvalues['completionpercent_videoplaylist'] = $defaultvalues['completionpercent'];
        }
    }

    /**
     * Method get_data.
     *
     * @return mixed Return value.
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data && property_exists($data, 'completionpercent_videoplaylist')) {
            $data->completionpercent = $data->completionpercent_videoplaylist;
            unset($data->completionpercent_videoplaylist);
        }
        return $data;
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
        if (isset($data['completionpercent_videoplaylist']) &&
            ((int)$data['completionpercent_videoplaylist'] < 1 || (int)$data['completionpercent_videoplaylist'] > 100)) {
            $errors['completionpercent_videoplaylist'] = get_string('errorpercent', 'videoplaylist');
        }
        return $errors;
    }
}
