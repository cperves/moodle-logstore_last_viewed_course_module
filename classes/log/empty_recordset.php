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
 * Standard log reader/writer.
 *
 * @package    logstore_last_viewed_course_module
 * @copyright  2020 Université de Strasbourg {@link https://unistra.fr}
 * @author  Céline Pervès <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_last_viewed_course_module\log;

defined('MOODLE_INTERNAL') || die();

class empty_recordset extends moodle_recordset {
    protected $records;

    /**
     * Constructor
     * @param $table as for {@link testing_db_record_builder::build_db_records()}
     *      but does not need a unique first column.
     */
    public function __construct() {
        $this->records = array();
        reset($this->records);
    }

    public function __destruct() {
        $this->close();
    }

    public function current() {
        return (object) current($this->records);
    }

    public function key() {
        if (is_null(key($this->records))) {
            return false;
        }
        $current = current($this->records);
        return reset($current);
    }

    public function next() {
        next($this->records);
    }

    public function valid() {
        return !is_null(key($this->records));
    }

    public function close() {
        $this->records = null;
    }
}