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
 * update_progress.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoplaylist\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use mod_videoplaylist\progress_manager;

/**
 * Class update_progress.
 */
class update_progress extends external_api {
    /**
     * Method execute_parameters.
     *
     * @return external_function_parameters Return value.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'videoid' => new external_value(PARAM_INT, 'Video id'),
            'currentposition' => new external_value(PARAM_FLOAT, 'Current position'),
            'duration' => new external_value(PARAM_FLOAT, 'Duration'),
            'segmentstart' => new external_value(PARAM_FLOAT, 'Segment start'),
            'segmentend' => new external_value(PARAM_FLOAT, 'Segment end'),
            'playbackrate' => new external_value(PARAM_FLOAT, 'Playback rate'),
            'sequence' => new external_value(PARAM_INT, 'Session sequence'),
            'sessionkey' => new external_value(PARAM_ALPHANUMEXT, 'Session key'),
        ]);
    }

    /**
     * Method execute.
     *
     * @param int $cmid Parameter cmid.
     * @param int $videoid Parameter videoid.
     * @param float $currentposition Parameter currentposition.
     * @param float $duration Parameter duration.
     * @param float $segmentstart Parameter segmentstart.
     * @param float $segmentend Parameter segmentend.
     * @param float $playbackrate Parameter playbackrate.
     * @param int $sequence Parameter sequence.
     * @param string $sessionkey Parameter sessionkey.
     * @return array Return value.
     */
    public static function execute(int $cmid, int $videoid, float $currentposition, float $duration,
                                   float $segmentstart, float $segmentend, float $playbackrate,
                                   int $sequence, string $sessionkey): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'videoid', 'currentposition',
            'duration', 'segmentstart', 'segmentend', 'playbackrate', 'sequence', 'sessionkey'));
        $cm = get_coursemodule_from_id('videoplaylist', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoplaylist:view', $context);
        if (isguestuser() || !is_enrolled($context, $USER, 'mod/videoplaylist:view', true)) {
            throw new \required_capability_exception($context, 'mod/videoplaylist:view', 'nopermissions', '');
        }
        $playlist = $DB->get_record('videoplaylist', ['id' => $cm->instance], '*', MUST_EXIST);
        $video = $DB->get_record('videoplaylist_videos',
            ['id' => $params['videoid'], 'playlistid' => $playlist->id, 'enabled' => 1], '*', MUST_EXIST);
        if ($params['duration'] <= 0 || $params['duration'] > 604800 || $params['currentposition'] < 0 ||
            $params['segmentstart'] < 0 || $params['segmentend'] < $params['segmentstart'] ||
            $params['playbackrate'] < 0.25 || $params['playbackrate'] > 4 || $params['sequence'] < 1 ||
            strlen($params['sessionkey']) < 16) {
            throw new invalid_parameter_exception(get_string('invalidtrackingdata', 'videoplaylist'));
        }
        return (new progress_manager())->update($playlist, $video, $cm, $USER->id, $params);
    }

    /**
     * Method execute_returns.
     *
     * @return external_single_structure Return value.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'accepted' => new external_value(PARAM_BOOL, 'Accepted'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Reason'),
            'percent' => new external_value(PARAM_FLOAT, 'Video percent'),
            'lastposition' => new external_value(PARAM_FLOAT, 'Last position'),
            'completed' => new external_value(PARAM_BOOL, 'Video completed'),
            'overallpercent' => new external_value(PARAM_FLOAT, 'Overall percent'),
            'segments' => new external_value(PARAM_RAW, 'Watched segments'),
        ]);
    }
}
