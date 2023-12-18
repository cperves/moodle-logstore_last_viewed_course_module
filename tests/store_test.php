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
 * last_viewed_course_module log store tests.
 *
 * @package    logstore_last_viewed_course_module
 * @copyright  2020 Université de Strasbourg {@link https://unistra.fr}
 * @author  Céline Pervès <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_last_viewed_course_module;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use core\event\course_viewed;
use core\session\manager;
use core_component;
use logstore_last_viewed_course_module\log\store;
use logstore_last_viewed_course_module\task\cleanup_task;

class store_test extends advanced_testcase {
    /**
     * @var bool Determine if we disabled the GC, so it can be re-enabled in tearDown.
     */

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

    /**
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_logstore_enabling() {
        $this->setup_datas();
        // Test all plugins are disabled by this command.
        set_config('enabled_stores', '', 'tool_log');
        $manager = get_log_manager(true);
        $stores = $manager->get_readers();
        $this->assertCount(0, $stores);

        // Enable logging plugin.
        $this->set_log_store(false);

        $stores = $manager->get_readers();
        $this->assertCount(1, $stores);
        $this->assertEquals(array('logstore_last_viewed_course_module'), array_keys($stores));
        /** @var store $store */
        $store = $stores['logstore_last_viewed_course_module'];
        $this->assertInstanceOf('logstore_last_viewed_course_module\log\store', $store);
        $this->assertInstanceOf('tool_log\log\writer', $store);
        // This plugin is not Logging.
        $this->assertFalse($store->is_logging());
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_viewed(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        $this->setCurrentTimeStart();
        set_config('courseviewreinit', 0, 'local_digital_training_account_services');
        $this->setUser($this->user2->id);
        $event1 = course_viewed::create(
            array('context' => context_course::instance($this->course1->id)));
        $event1->trigger();
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        set_config('courseviewreinit', 1, 'local_digital_training_account_services');
        $event1 = course_viewed::create(
            array('context' => context_course::instance($this->course1->id)));
        $event1->trigger();
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(1, $logs);
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_module_viewed(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        $this->setCurrentTimeStart();
        $this->setUser($this->user1);
        $this->assertEquals(0, $DB->count_records('logstore_lastviewed_log'));
        resource_view($this->resource2, $this->course2, $this->cmresource2,  context_module::instance($this->resource2->cmid));
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(1, $logs);
        // Check datas.
        $log = array_shift($logs);
        $this->assertEquals($this->user1->id, $log->userid);
        $this->assertEquals($this->cmresource2->id, $log->cmid);
        $this->assertEquals($this->cmresource2->id, $log->cmid);

    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_module_viewed_loginas(bool $jsonformat) {
        global $DB;
        $this->set_log_store($jsonformat);
        $this->setup_datas();
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        $this->setCurrentTimeStart();
        $this->setAdminUser();
        manager::loginas($this->user1->id, context_system::instance());
        $this->assertTrue(manager::is_loggedinas());
        resource_view($this->resource2, $this->course2, $this->cmresource2,  context_module::instance($this->resource2->cmid));
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_deleted(bool $jsonformat) {
        global $DB;
        $this->setup_datas();
        $this->set_log_store($jsonformat);
        $this->setUser($this->user1);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        resource_view($this->resource2, $this->course2, $this->cmresource2,  context_module::instance($this->resource2->cmid));
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(1, $logs); // Other entry is for course 2.
        ob_start();
        delete_course($this->course2->id);
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        ob_get_contents();
        ob_end_clean();
    }

    /**
     * @param bool $jsonformat
     * @throws coding_exception
     * @throws dml_exception
     * @dataProvider test_provider
     */
    public function test_course_module_deleted(bool $jsonformat) {
        global $DB;
        $this->setup_datas();
        $this->set_log_store($jsonformat);
        $this->setUser($this->user1);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
        resource_view($this->resource2, $this->course2, $this->cmresource2,  context_module::instance($this->resource2->cmid));
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(1, $logs);
        course_delete_module($this->cmresource2->id);
        get_log_manager(true);
        $logs = $DB->get_records('logstore_lastviewed_log', array(), 'id ASC');
        $this->assertCount(0, $logs);
    }

    /**
     * Test logmanager::get_supported_reports returns all reports that require this store.
     */
    public function test_get_supported_reports() {
        $logmanager = get_log_manager();
        $allreports = core_component::get_plugin_list('report');

        $supportedreports = array(
            'report_log' => '/report/log',
            'report_loglive' => '/report/loglive',
            'report_outline' => '/report/outline',
            'report_participation' => '/report/participation',
            'report_stats' => '/report/stats'
        );

        // Make sure all supported reports are installed.
        $expectedreports = array_keys(array_intersect_key($allreports, $supportedreports));
        $reports = $logmanager->get_supported_reports('logstore_last_viewed_course_module');
        $reports = array_keys($reports);
        foreach ($expectedreports as $expectedreport) {
            $this->assertContains($expectedreport, $reports);
        }
    }

    /**
     * Test that the standard log cleanup works correctly.
     */
    public function test_cleanup_task() {
        global $DB;
        $this->setup_datas();
        // Create some records spread over various days; test multiple iterations in cleanup.

        $record = (object) array(
            'userid' => $this->user1->id,
            'lasttimeviewed' => time(),
            'cmid' => $this->cmresource1->id
        );
        $DB->insert_record('logstore_lastviewed_log', $record);
        $record->lasttimeviewed -= 3600 * 24 * 30;
        $record->cmid = $this->cmresource2->id;
        $DB->insert_record('logstore_lastviewed_log', $record);
        $record->lasttimeviewed -= 3600 * 24 * 30;
        $record->userid = $this->user2->id;
        $DB->insert_record('logstore_lastviewed_log', $record);
        $record->lasttimeviewed -= 3600 * 24 * 30;
        $record->cmid = $this->cmresource1->id;
        $DB->insert_record('logstore_lastviewed_log', $record);
        $this->assertEquals(4, $DB->count_records('logstore_lastviewed_log'));

        // Remove all logs before "today".
        set_config('loglifetime', 1, 'logstore_last_viewed_course_module');

        $this->expectOutputString(" Deleted old log records from last_viewed_course_module log store.\n");
        $clean = new cleanup_task();
        $clean->execute();

        $this->assertEquals(1, $DB->count_records('logstore_lastviewed_log'));
    }

    // Provider

    public static function test_provider(): array {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @param $course1
     * @param $resource1
     * @param $course2
     * @param $resource2
     * @throws coding_exception
     */
    private function setup_datas() {
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
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
        get_log_manager(true);
    }

    private function set_log_store($jsonformat) {
        set_config('enabled_stores', '', 'tool_log');
        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_last_viewed_course_module', 'tool_log');
        set_config('jsonformat', $jsonformat ? 1 : 0, 'logstore_database');
        set_config('buffersize', 0, 'logstore_last_viewed_course_module');
        //set_config('logguests', 1, 'logstore_database');
        // Force reload.
        get_log_manager(true);
    }
}
