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
 * Data provider tests.
 *
 * @package    logstore_last_viewed_course_module
 * @copyright  2020 Université de Strasbourg {@link https://unistra.fr}
 * @author  Céline Pervès <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_last_viewed_course_module;

global $CFG;

use context_module;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\approved_userlist;
use logstore_last_viewed_course_module\privacy\provider;

require_once($CFG->libdir . '/tests/fixtures/events.php');

class privacy_provider_test extends provider_testcase {

    private $user1;
    private $user2;
    private $course1;
    private $course2;
    private $resource1;
    private $resourcecontext1;
    private $cmresource1;
    private $resource2;
    private $resourcecontext2;
    private $cmresource2;

    protected function setUp() : void {
        parent::setUp();
        $this->setup_datas();
    }

    /**
     * test get_users_in_context function
     */
    public function test_get_users_in_context() {
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        $this->setUser($this->user2);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        get_log_manager(true);
        $userlist = new userlist($this->resourcecontext1, 'logstore_last_viewed_course_module');
        provider::get_users_in_context($userlist);
        $users = $userlist->get_users();
        $this->assertCount(2, $users);
        $this->assertTrue(in_array($this->user1, $users));
        $this->assertTrue(in_array($this->user2, $users));
    }

    /**
     * Tets get_contexts_for_userid function.
     * Function that get the list of contexts that contain user information for the specified user.
     * @throws coding_exception
     */
    public function test_user_contextlist() {
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(2, $contextlist->get_contexts());
        $this->assertContains($this->resourcecontext1, $contextlist->get_contexts());
        $this->assertContains($this->resourcecontext2, $contextlist->get_contexts());
    }

    /**
     * Test export_all_data_for_user function.
     * funciton that export all data for a component for the specified user.
     * @throws coding_exception
     */
    public function test_export_user_data() {
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $approvedcontextlist = new approved_contextlist(
                $this->user1,
                'logstore_last_viewed_module_course',
                [$this->resourcecontext1->id, $this->resourcecontext2->id]
        );
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->resourcecontext1);
        $data = $writer->get_data([get_string('pluginname', 'logstore_last_viewed_course_module')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass'
, $data);
        $this->assertTrue(property_exists($data, 'logstore_lastviewed_log_records'));
        $this->assertCount(1, $data->logstore_lastviewed_log_records);
        foreach ($data->logstore_lastviewed_log_records as $logstorelastviewedlogrecord) {
            $this->assertEquals($this->user1->id, $logstorelastviewedlogrecord->userid);
            $this->assertEquals($this->cmresource1->id, $logstorelastviewedlogrecord->cmid);
        }
        writer::reset();
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->resourcecontext2);
        $data = $writer->get_data([get_string('pluginname', 'logstore_last_viewed_course_module')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass'
, $data);
        $this->assertTrue(property_exists($data, 'logstore_lastviewed_log_records'));
        $this->assertCount(1, $data->logstore_lastviewed_log_records);
        foreach ($data->logstore_lastviewed_log_records as $logstorelastviewedlogrecord) {
            $this->assertEquals($this->user1->id, $logstorelastviewedlogrecord->userid);
            $this->assertEquals($this->cmresource2->id, $logstorelastviewedlogrecord->cmid);
        }
    }

    /**
     * Test Add contexts that contain user information for the specified user.
     * @return void
     */
    public function test_add_contexts_for_userid() {
        $this->setUser($this->user1);
        $addedcontextlist = new contextlist();
        provider::add_contexts_for_userid($addedcontextlist, $this->user1->id);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(0, $contextlist);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $addedcontextlist = new contextlist();
        provider::add_contexts_for_userid($addedcontextlist, $this->user1->id);
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertCount(2, $contextlist);
        $this->assertContains($this->resourcecontext1, $contextlist);
        $this->assertContains($this->resourcecontext2, $contextlist);
    }
    /**
     * Test add_userids_for_context function
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public function test_add_userids_for_context() {
        $userlist = new userlist($this->resourcecontext1, 'logstore_last_viewed_course_module');
        $userids = $userlist->get_userids();
        $this->assertEmpty($userids);
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        $this->setUser($this->user2);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        get_log_manager(true);
        provider::add_userids_for_context($userlist);
        get_log_manager(true);
        $userids = $userlist->get_userids();
        $this->assertCount(2, $userids);
        $this->assertContains((int)$this->user1->id, $userids);
        $this->assertContains((int)$this->user2->id, $userids);
    }

    /**
     * * Test delete_data_for_user function
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $this->setUser($this->user2);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $this->assertCount(4, $DB->get_records('logstore_lastviewed_log'));
        $this->assertEquals(2, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user1->id)));
        $this->assertEquals(2, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user2->id)));
        provider::delete_data_for_user(
                new approved_contextlist(
                        $this->user1, 'logstore_last_viewed_course_module', [$this->resourcecontext1->id]
                )
        );
        $this->assertFalse(
                $DB->record_exists('logstore_lastviewed_log',
                        array('userid' => $this->user1->id, 'cmid' => $this->cmresource1->id)
                )
        );
        $this->assertEquals(1, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user1->id)));
        $this->assertEquals(2, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user2->id)));
        provider::delete_data_for_user(
                new approved_contextlist(
                        $this->user2, 'logstore_last_viewed_course_module',
                        [$this->resourcecontext1->id, $this->resourcecontext2->id]
                )
        );
        $this->assertFalse($DB->record_exists('logstore_lastviewed_log', array('userid' => $this->user2->id)));
        $this->assertEquals(1, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user1->id)));
        $this->assertEquals(0, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user2->id)));
    }

    /**
     * test delete_data_for_all_users_in_context function
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $this->setUser($this->user2);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        get_log_manager(true);
        $this->assertCount(4, $DB->get_records('logstore_lastviewed_log'));
        provider::delete_data_for_all_users_in_context($this->resourcecontext1);
        $this->assertCount(2, $DB->get_records('logstore_lastviewed_log'));
        $this->assertFalse($DB->record_exists('logstore_lastviewed_log', array('cmid' => $this->cmresource1->id)));
        $this->assertEquals(1, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user1->id)));
        $this->assertEquals(1, $DB->count_records('logstore_lastviewed_log', array('userid' => $this->user2->id)));
    }

    /**
     * test delete_data_for_userlist function
     */
    public function test_delete_data_for_userlist() {
        global $DB;
        $this->lauch_resourceview_for_users();
        get_log_manager(true);
        $this->assertCount(4, $DB->get_records('logstore_lastviewed_log'));
        // Delete for resource1 context.
        $userlist = new approved_userlist(
                $this->resourcecontext1, 'logstore_last_viewed_course_module', array($this->user1->id, $this->user2->id)
        );
        provider::delete_data_for_userlist($userlist);
        $this->assertCount(2, $DB->get_records('logstore_lastviewed_log'));
        $this->assertFalse($DB->record_exists('logstore_lastviewed_log', array('cmid' => $this->cmresource1->id)));
    }

    /**
     * internal function to setu test datas
     * @throws coding_exception
     */
    private function setup_datas() {
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        $this->set_logstore();
        $this->setAdminUser();
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->course1 = $this->getDataGenerator()->create_course();
        $this->resource1 = $this->getDataGenerator()->create_module('resource', array('course' => $this->course1));
        $this->resourcecontext1 =  context_module::instance($this->resource1->cmid);
        $this->cmresource1 = get_coursemodule_from_instance('resource', $this->resource1->id);
        $this->course2 = $this->getDataGenerator()->create_course();
        $this->resource2 = $this->getDataGenerator()->create_module('resource', array('course' => $this->course2));
        $this->resourcecontext2 =  context_module::instance($this->resource2->cmid);
        $this->cmresource2 = get_coursemodule_from_instance('resource', $this->resource2->id);
    }

    /**
     * Set up logstore to test
     */
    private function set_logstore() {
        set_config('enabled_stores', '', 'tool_log');
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_last_viewed_course_module', 'tool_log');
        set_config('logguests', 1, 'logstore_last_viewed_course_module');
        // Force reload.
        get_log_manager(true);
    }

    /**
     * launch resource_view events for cms and users
     */
    private function lauch_resourceview_for_users() {
        $this->setUser($this->user1);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
        $this->setUser($this->user2);
        resource_view($this->resource1, $this->course1, $this->cmresource1, $this->resourcecontext1);
        resource_view($this->resource2, $this->course2, $this->cmresource2, $this->resourcecontext2);
    }

}
