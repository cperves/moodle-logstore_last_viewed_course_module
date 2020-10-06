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
 * Data provider.
 *
 * @package    logstore_last_viewed_course_module
 * @copyright  2020 Université de Strasbourg {@link https://unistra.fr}
 * @author  Céline Pervès <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_last_viewed_course_module\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Data provider class.
 *
 * @package    logstore_last_viewed_course_module
 * @copyright  2020 Université de Strasbourg {@link https://unistra.fr}
 * @author  Céline Pervès <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\metadata\provider,
        \tool_log\local\privacy\logstore_provider,
        \tool_log\local\privacy\logstore_userlist_provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('logstore_lastviewed_log', [
                'userid' => 'privacy:metadata:log:userid',
                'cmid' => 'privacy:metadata:log:userid',
                'lasttimeviewed' => 'privacy:metadata:log:lasttimeviewed',
        ], 'privacy:metadata:log');
        return $collection;
    }

    /**
     * Add contexts that contain user information for the specified user.
     *
     * @param contextlist $contextlist The contextlist to add the contexts to.
     * @param int $userid The user to find the contexts for.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        $sql = "
            SELECT ctx.id
              FROM {logstore_lastviewed_log} l
              INNER JOIN {context} ctx on ctx.instanceid = l.cmid and ctx.contextlevel = :modulecontext
             WHERE l.userid = :userid";
        $contextlist->add_from_sql($sql, [
                'userid' => $userid,
                'modulecontext' => CONTEXT_MODULE,
        ]);
    }

    /**
     * Add user IDs that contain user information for the specified context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function add_userids_for_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "
            SELECT userid
              FROM {logstore_lastviewed_log}
             WHERE cmid = :cmid";
        $params = [
                'cmid' => $context->instanceid
        ];
        $userlist->add_from_sql('userid', $sql, $params);
    }


    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        // Sanitize contexts.
        $aprovedcontextlist = self::validate_contextlist_contexts($contextlist, [CONTEXT_MODULE]);
        if (empty($aprovedcontextlist)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $entries = array();
        // Return database entries.
        foreach ($aprovedcontextlist as $approvedcontext) {
                $params = array(
                        'cmid' => $approvedcontext->instanceid,
                        'userid' => $userid
                );
                $entries = $DB->get_records('logstore_lastviewed_log', $params);
            if (!empty($entries)) {
                writer::with_context($approvedcontext)->export_data(
                        [get_string('pluginname', 'logstore_last_viewed_course_module')],
                        (object)['logstore_lastviewed_log_records' => $entries]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_module) {
            $DB->delete_records_select('logstore_lastviewed_log', 'cmid=:cmid', array('cmid' => $context->instanceid));
        }
    }
    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                $DB->delete_records_select('logstore_lastviewed_log',
                        'cmid=:cmid and userid=:userid',
                        array('userid' => $userid, 'cmid' => $context->instanceid));
            }
        }

    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        foreach ($userlist as $user) {
            if ($context instanceof \context_module) {
                $DB->delete_records_select('logstore_lastviewed_log', 'cmid=:cmid and userid=:userid',
                        array('cmid' => $context->instanceid, 'userid' => $user->id));
            }
        }
    }

    /**
     * Delete all user data for the specified users, in the specified context.
     *
     * @param \core_privacy\local\request\approved_userlist $contextlist
     *   The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_userlist(\core_privacy\local\request\approved_userlist $userlist) {
        global $DB;
        list($insql, $inparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        if (! $userlist->get_context() instanceof \context_module) {
            return;
        }
        $params = array_merge($inparams, ['cmid' => $userlist->get_context()->instanceid]);
        $DB->delete_records_select('logstore_lastviewed_log', "cmid = :cmid AND userid $insql", $params);
    }

    /**
     * sanitize contextlist course and system context
     * @param approved_contextlist $contextlist
     * @return mixed
     */
    protected static function validate_contextlist_contexts(approved_contextlist $contextlist, $contextlevellist) {
        return array_reduce($contextlist->get_contexts(), function($carry, $context) use($contextlevellist) {
            if (in_array($context->contextlevel, $contextlevellist)) {
                $carry[$context->id] = $context;
            }
            return $carry;
        }, []);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof \context_module) {
            $sql = 'select userid from {logstore_lastviewed_log} where cmid=:cmid';
            $params = [
                    'cmid' => $context->instanceid
            ];
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Store datas in system and course context.
        // Since enrolment is a subsystem link does not return user enrolments in course.
        $contextlist = new contextlist();
        // Add linked course context.
        $sql = 'select ctx.id from {logstore_lastviewed_log} beb
                    inner join {context} ctx on ctx.instanceid=beb.cmid and ctx.contextlevel=:modulecontext
                    where beb.userid=:userid';
        $params = [
            'userid' => $userid,
            'modulecontext' => CONTEXT_MODULE
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }
}
