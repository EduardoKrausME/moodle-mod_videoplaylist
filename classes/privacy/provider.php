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
 * Privacy provider.
 *
 * @package   mod_videoplaylist
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoplaylist\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Video Playlist.
 */
class provider implements metadata_provider, plugin_provider, core_userlist_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videoplaylist_progress', [
            'playlistid' => 'privacy:metadata:progress:playlistid',
            'videoid' => 'privacy:metadata:progress:videoid',
            'userid' => 'privacy:metadata:progress:userid',
            'duration' => 'privacy:metadata:progress:duration',
            'lastposition' => 'privacy:metadata:progress:lastposition',
            'uniquewatched' => 'privacy:metadata:progress:uniquewatched',
            'totalwatchtime' => 'privacy:metadata:progress:watchtime',
            'percent' => 'privacy:metadata:progress:percent',
            'watchedsegments' => 'privacy:metadata:progress:segments',
            'completed' => 'privacy:metadata:progress:completed',
            'timecreated' => 'privacy:metadata:progress:timecreated',
            'timemodified' => 'privacy:metadata:progress:timemodified',
        ], 'privacy:metadata:progress');

        $collection->add_database_table('videoplaylist_sessions', [
            'playlistid' => 'privacy:metadata:sessions:playlistid',
            'videoid' => 'privacy:metadata:sessions:videoid',
            'userid' => 'privacy:metadata:sessions:userid',
            'sessionkey' => 'privacy:metadata:sessions:sessionkey',
            'sequence' => 'privacy:metadata:sessions:sequence',
            'lastheartbeat' => 'privacy:metadata:sessions:lastheartbeat',
            'timecreated' => 'privacy:metadata:sessions:timecreated',
            'timemodified' => 'privacy:metadata:sessions:timemodified',
        ], 'privacy:metadata:sessions');

        return $collection;
    }

    /**
     * Get module contexts containing data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'videoplaylist',
            'userid' => $userid,
        ];

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {videoplaylist_progress} p
                    ON p.playlistid = cm.instance
                 WHERE p.userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {videoplaylist_sessions} s
                    ON s.playlistid = cm.instance
                 WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export user data from all approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('videoplaylist', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $params = ['playlistid' => $cm->instance, 'userid' => $userid];
            $progress = $DB->get_records('videoplaylist_progress', $params);
            $sessions = $DB->get_records('videoplaylist_sessions', $params);
            if (!$progress && !$sessions) {
                continue;
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'videoplaylist')],
                (object)[
                    'progress' => array_values($progress),
                    'sessions' => array_values($sessions),
                ]
            );
        }
    }

    /**
     * Delete all user data in a module context.
     *
     * @param \context $context Context to delete.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videoplaylist', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videoplaylist_sessions', ['playlistid' => $cm->instance]);
        $DB->delete_records('videoplaylist_progress', ['playlistid' => $cm->instance]);
    }

    /**
     * Delete data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('videoplaylist', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $params = ['playlistid' => $cm->instance, 'userid' => $userid];
            $DB->delete_records('videoplaylist_sessions', $params);
            $DB->delete_records('videoplaylist_progress', $params);
        }
    }

    /**
     * Add users who have data in the supplied module context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videoplaylist', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $params = ['playlistid' => $cm->instance];
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {videoplaylist_progress} WHERE playlistid = :playlistid',
            $params
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {videoplaylist_sessions} WHERE playlistid = :playlistid',
            $params
        );
    }

    /**
     * Delete data for selected users in a module context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videoplaylist', $context->instanceid, 0, false, IGNORE_MISSING);
        $userids = $userlist->get_userids();
        if (!$cm || !$userids) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        $params = array_merge(['playlistid' => $cm->instance], $userparams);
        $select = "playlistid = :playlistid AND userid {$usersql}";

        $DB->delete_records_select('videoplaylist_sessions', $select, $params);
        $DB->delete_records_select('videoplaylist_progress', $select, $params);
    }
}
