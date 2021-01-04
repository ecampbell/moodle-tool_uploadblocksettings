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
 * Library of functions for uploading a course block settings CSV file.
 *
 * @package    tool_uploadblocksettings
 * @copyright  2020 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Validates and processes files for uploading a course enrolment methods CSV file
 *
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadblocksettings_processor {

    /** @var csv_import_reader */
    protected $cir;

    /** @var array CSV columns. */
    protected $columns = array();

    /** @var int line number. */
    protected $linenb = 0;

    /**
     * Constructor, sets the CSV file reader
     *
     * @param csv_import_reader $cir import reader object
     */
    public function __construct(csv_import_reader $cir) {
        $this->cir = $cir;
        $this->columns = $cir->get_columns();
        $this->validate();
        $this->reset();
        $this->linenb++;
    }

    /**
     * Processes the file to handle the enrolment methods
     *
     * Opens the file, loops through each row. Cleans the values in each column,
     * checks that the operation is valid and the methods exist. If all is well,
     * adds, updates or deletes the enrolment method metalink in column 3 to/from the course in column 2
     * context as specified.
     * Returns a report of successes and failures.
     *
     * @see open_file()
     * @uses enrol_meta_sync() Meta plugin function for syncing users
     * @return string A report of successes and failures.
     *
     * @param object $tracker the output tracker to use.
     * @return void
     */
    public function execute($tracker = null) {
        global $DB;

        // Initialise the output heading row labels.
        $reportheadings = array('line' => get_string('csvline', 'tool_uploadcourse'),
            'operation' => get_string('operation', 'tool_uploadblocksettings'),
            'courseid' => get_string('id', 'tool_uploadcourse'),
            'block' => get_string('block'),
            'region' => get_string('region', 'tool_uploadblocksettings'),
            'weight' => get_string('weight', 'tool_uploadblocksettings')
            );

        $report = array();

        // Set a counter so we can report line numbers for errors.
        $line = 0;

        // Remember the last course, to avoid reloading all blocks on each line.
        $previouscourse = '';
        $courseblock = null;

        // Prepare reporting message strings.
        $strings = new stdClass;
        $strings->linenum = $line;
        $strings->line = get_string('csvline', 'tool_uploadcourse');
        $strings->skipped = get_string('skipped');

        // Loop through each row of the file.

        $total = 0;
        while ($line = $this->cir->next()) {
            $this->linenb++;
            $total++;

            // Read in clean parameters to prevent sql injection.
            // operation	courseid	block	region	weight

            $data = $this->parse_line($line);
            $op = $data['operation'];
            $courseshortname = $data['courseid'];
            $blockname = $data['block'];
            $region = $data['region'];
            $weight = $data['weight'];

            // Prepare reporting message strings.
            $strings->op = $op;
            $strings->coursename = $courseshortname;
            $strings->blockname = $blockname;
            $strings->region = $region;
            $strings->weight = $weight;

            if ($op == 'add') {
                $strings->oplabel = get_string('add');
            } else if ($op == 'del' || $op == 'delete') {
                $strings->oplabel = get_string('delete');
                $op = 'del';
            } else if ($op == 'res' || $op == 'reset') {
                $strings->oplabel = get_string('reset');
                $op = 'res';
            }

            // Check the line is valid and if not, add a message to the report and skip it.

            // Check that the operation is valid.
            if (!in_array($op, array('add', 'del', 'res'))) {
                $report[] = get_string('operationunknown', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that the row specifies a course.
            if ($courseshortname == '') {
                $report[] = get_string('coursenotspecified', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that the specified course exists.
            if (!$course = $DB->get_record('course', array('shortname' => $courseshortname))) {
                $report[] = get_string('coursenotfound', 'tool_uploadblocksettings', $strings);
                continue;
            }
            $strings->courseid = $course->id;

            // Set up the course context, keeping the last context if the course is the same.
            if ($courseshortname != $previouscourse) {
                $courseblock = new tool_uploadblocksettings_courseblock($course);
                // Get the list of fixed blocks, i.e. Administration and Navigation.
                $protectedblocks = $courseblock->get_undeletable_block_types();
                $previouscourse = $courseshortname;
            }

            // Handle special case of course block reset, which doesn't require a block to be specified.
            if ($op == 'res') {
                $context = context_course::instance($course->id);
                blocks_delete_all_for_context($context->id);
                blocks_add_default_course_blocks($course);

                $report[] = get_string('courseblocksreset', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check that the row specifies a block.
            if ($blockname == '') {
                $report[] = get_string('blocknotspecified', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that the row specifies a region.
            if ($region == '') {
                $report[] = get_string('regionnotspecified', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that a valid block is specified, and get its name if it is.
            if (!($courseblock->is_known_block_type($blockname))) {
                $report[] = get_string('blocknotinstalled', 'tool_uploadblocksettings', $strings);
                continue;
            }
            $strings->blocktitle = get_string('pluginname', 'block_' . $blockname);
            // Check that the block is not a special case like Administration or Navigation.
            if (in_array($blockname, $protectedblocks)) {
                $report[] = get_string('operationnotvalid', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that the block is present, if it is being deleted.
            if (($op == 'del') && !$courseblock->is_block_present($blockname)) {
                $report[] = get_string('blockdoesntexist', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that a valid region is specified.
            if (!$courseblock->is_known_region($region)) {
                $report[] = get_string('regionnotvalid', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check that a valid weight is specified.
            if (!is_int($weight) || $weight > 10 || $weight < -10 ) {
                $report[] = get_string('weightnotvalid', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Initial checks complete, now attempt to implement each operation.
            if ($op == 'del') {
                // Get the block instance (only delete the first one we find that matches all criteria).
                if (($bi = $courseblock->find_courseblock_instance($blockname, $region, $weight)) === false) {
                    $report[] = get_string('blockinstancenotfound', 'tool_uploadblocksettings', $strings);
                    continue;
                }
                blocksettings_delete_instance($bi);
                $report[] = get_string('blockdeleted', 'tool_uploadblocksettings', $strings);
            } else if ($op == 'add') {
                // Check that the block can be added to the course.
                if (array_key_exists($blockname, $courseblock->get_addable_blocks())) {
                    // Create a block instance and add it to the database.
                    $courseblock->add_block($blockname, $region, $weight);
                    $report[] = get_string('blockadded', 'tool_uploadblocksettings', $strings);
                } else {
                    $report[] = get_string('blockalreadyadded', 'tool_uploadblocksettings', $strings);
                    continue;
                }
            }
        } // End of while loop.

        $message = array(
            get_string('methodstotal', 'tool_uploadblocksettings', $total)
        );
    }

    /**
     * Parse a line to return an array(column => value)
     *
     * @param array $line returned by csv_import_reader
     * @return array
     */
    protected function parse_line($line) {
        $data = array();
        foreach ($line as $keynum => $value) {
            if (!isset($this->columns[$keynum])) {
                // This should not happen.
                continue;
            }

            $key = $this->columns[$keynum];
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * Reset the current process.
     *
     * @return void.
     */
    public function reset() {
        $this->processstarted = false;
        $this->linenb = 0;
        $this->cir->init();
        $this->errors = array();
    }

    /**
     * Validation.
     *
     * @return void
     */
    protected function validate() {
        if (empty($this->columns)) {
            throw new moodle_exception('cannotreadtmpfile', 'error');
        } else if (count($this->columns) < 2) {
            throw new moodle_exception('csvfewcolumns', 'error');
        }
    }
}


/**
 * An exception for reporting errors when processing files
 *
 * Extends the moodle_exception with an http property, to store an HTTP error
 * code for responding to AJAX requests.
 *
 * @copyright   2010 Tauntons College, UK
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadblocksettings_processor_exception extends moodle_exception {

    /**
     * Stores an HTTP error code
     *
     * @var int
     */
    public $http;

    /**
     * Constructor, creates the exeption from a string identifier, string
     * parameter and HTTP error code.
     *
     * @param string $errorcode
     * @param string $a
     * @param int $http
     */
    public function __construct($errorcode, $a = null, $http = 200) {
        parent::__construct($errorcode, 'tool_uploadblocksettings', '', $a);
        $this->http = $http;
    }
}
