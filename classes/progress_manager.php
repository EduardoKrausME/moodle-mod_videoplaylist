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
 * progress_manager.php
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoplaylist;

use completion_info;
use context_module;
use stdClass;

/**
 * Class progress_manager.
 */
class progress_manager {
    /**
     * Method get_videos.
     *
     * @param int $playlistid Parameter playlistid.
     * @return array Return value.
     */
    public function get_videos(int $playlistid): array {
        global $DB;
        return array_values($DB->get_records('videoplaylist_videos',
            ['playlistid' => $playlistid, 'enabled' => 1], 'sortorder ASC, id ASC'));
    }

    /**
     * Method get_progress.
     *
     * @param int $videoid Parameter videoid.
     * @param int $userid Parameter userid.
     * @return ?stdClass Return value.
     */
    public function get_progress(int $videoid, int $userid): ?stdClass {
        global $DB;
        $record = $DB->get_record('videoplaylist_progress', ['videoid' => $videoid, 'userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Method get_overall_percent.
     *
     * @param int $playlistid Parameter playlistid.
     * @param int $userid Parameter userid.
     * @return float Return value.
     */
    public function get_overall_percent(int $playlistid, int $userid): float {
        $videos = $this->get_videos($playlistid);
        if (!$videos) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($videos as $video) {
            $progress = $this->get_progress($video->id, $userid);
            $sum += $progress ? min(100.0, max(0.0, (float)$progress->percent)) : 0.0;
        }
        return round($sum / count($videos), 2);
    }

    /**
     * Method is_video_unlocked.
     *
     * @param stdClass $playlist Parameter playlist.
     * @param stdClass $video Parameter video.
     * @param int $userid Parameter userid.
     * @return bool Return value.
     */
    public function is_video_unlocked(stdClass $playlist, stdClass $video, int $userid): bool {
        if (empty($playlist->sequential)) {
            return true;
        }
        $videos = $this->get_videos($playlist->id);
        $previous = null;
        foreach ($videos as $item) {
            if ((int)$item->id === (int)$video->id) {
                if ($previous === null) {
                    return true;
                }
                $progress = $this->get_progress($previous->id, $userid);
                return $progress && (float)$progress->percent >= (float)$previous->minpercent;
            }
            $previous = $item;
        }
        return false;
    }

    /**
     * Method normalise_segments.
     *
     * @param array $segments Parameter segments.
     * @param float $duration Parameter duration.
     * @return array Return value.
     */
    public function normalise_segments(array $segments, float $duration): array {
        $clean = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || count($segment) < 2) {
                continue;
            }
            $start = max(0.0, min($duration, (float)$segment[0]));
            $end = max(0.0, min($duration, (float)$segment[1]));
            if ($end <= $start) {
                continue;
            }
            $clean[] = [$start, $end];
        }
        usort($clean, static fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($clean as $segment) {
            if (!$merged || $segment[0] > $merged[count($merged) - 1][1] + 0.75) {
                $merged[] = $segment;
            } else {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $segment[1]);
            }
        }
        return $merged;
    }

    /**
     * Method update.
     *
     * @param stdClass $playlist Parameter playlist.
     * @param stdClass $video Parameter video.
     * @param stdClass $cm Parameter cm.
     * @param int $userid Parameter userid.
     * @param array $params Parameter params.
     * @return array Return value.
     */
    public function update(stdClass $playlist, stdClass $video, stdClass $cm, int $userid, array $params): array {
        global $DB;
        if (!$this->is_video_unlocked($playlist, $video, $userid)) {
            throw new \moodle_exception('videolocked', 'videoplaylist');
        }
        $now = time();
        $session = $DB->get_record('videoplaylist_sessions', [
            'videoid' => $video->id, 'userid' => $userid, 'sessionkey' => $params['sessionkey'],
        ]);
        if (!$session) {
            $session = (object)[
                'playlistid' => $playlist->id, 'videoid' => $video->id, 'userid' => $userid,
                'sessionkey' => $params['sessionkey'], 'sequence' => 0,
                'lastheartbeat' => $now, 'timecreated' => $now, 'timemodified' => $now,
            ];
            $session->id = $DB->insert_record('videoplaylist_sessions', $session);
        }
        if ((int)$params['sequence'] <= (int)$session->sequence) {
            return $this->response($playlist, $video, $userid, false, 'oldsequence');
        }
        $duration = min(604800.0, max(1.0, (float)$params['duration']));
        $start = max(0.0, min($duration, (float)$params['segmentstart']));
        $end = max($start, min($duration, (float)$params['segmentend']));
        $rate = min(4.0, max(0.25, (float)$params['playbackrate']));
        $elapsed = max(1, min(60, $now - (int)$session->lastheartbeat));
        $budget = min(65.0, $elapsed * $rate + 3.0);
        if ($end - $start > $budget) {
            $end = min($duration, $start + $budget);
        }

        $progress = $this->get_progress($video->id, $userid);
        if (!$progress) {
            $progress = (object)[
                'playlistid' => $playlist->id, 'videoid' => $video->id, 'userid' => $userid,
                'duration' => $duration, 'lastposition' => 0, 'uniquewatched' => 0, 'totalwatchtime' => 0,
                'percent' => 0, 'watchedsegments' => '[]', 'completed' => 0,
                'timecreated' => $now, 'timemodified' => $now,
            ];
        }
        $stored = json_decode((string)$progress->watchedsegments, true);
        if (!is_array($stored)) {
            $stored = [];
        }
        $existing = $this->normalise_segments($stored, $duration);
        if (empty($playlist->allowseek)) {
            $frontier = 0.0;
            foreach ($existing as $segment) {
                if ($segment[0] <= $frontier + 1.0) {
                    $frontier = max($frontier, $segment[1]);
                } else {
                    break;
                }
            }
            if ($start > $frontier + 3.0) {
                $start = $frontier;
                $end = min($duration, $start + $budget);
            }
        }
        if ($end > $start) {
            $stored[] = [$start, $end];
        }
        $segments = $this->normalise_segments($stored, $duration);
        $unique = 0.0;
        foreach ($segments as $segment) {
            $unique += $segment[1] - $segment[0];
        }
        $percent = min(100.0, round(($unique / $duration) * 100, 2));
        $progress->duration = $duration;
        $progress->lastposition = max(0.0, min($duration, (float)$params['currentposition']));
        $progress->uniquewatched = round($unique, 3);
        $progress->totalwatchtime = (float)$progress->totalwatchtime + max(0.0, $end - $start);
        $progress->percent = $percent;
        $progress->watchedsegments = json_encode($segments, JSON_UNESCAPED_SLASHES);
        $progress->completed = $percent >= (float)$video->minpercent ? 1 : 0;
        $progress->timemodified = $now;
        if (!empty($progress->id)) {
            $DB->update_record('videoplaylist_progress', $progress);
        } else {
            $progress->id = $DB->insert_record('videoplaylist_progress', $progress);
        }

        $session->sequence = (int)$params['sequence'];
        $session->lastheartbeat = $now;
        $session->timemodified = $now;
        $DB->update_record('videoplaylist_sessions', $session);

        videoplaylist_update_grades($playlist, $userid, false);
        $completion = new completion_info(get_course($playlist->course));
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
        return $this->response($playlist, $video, $userid, true, 'ok');
    }

    /**
     * Method response.
     *
     * @param stdClass $playlist Parameter playlist.
     * @param stdClass $video Parameter video.
     * @param int $userid Parameter userid.
     * @param bool $accepted Parameter accepted.
     * @param string $reason Parameter reason.
     * @return array Return value.
     */
    private function response(stdClass $playlist, stdClass $video, int $userid, bool $accepted, string $reason): array {
        $progress = $this->get_progress($video->id, $userid);
        $segments = $progress ? (string)$progress->watchedsegments : '[]';
        return [
            'accepted' => $accepted, 'reason' => $reason,
            'percent' => $progress ? (float)$progress->percent : 0.0,
            'lastposition' => $progress ? (float)$progress->lastposition : 0.0,
            'completed' => $progress ? !empty($progress->completed) : false,
            'overallpercent' => $this->get_overall_percent($playlist->id, $userid),
            'segments' => $segments,
        ];
    }
}
