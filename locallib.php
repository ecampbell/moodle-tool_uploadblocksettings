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
 * @copyright  2018 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/blocklib.php');

/**
 * Validates and processes files for uploading a block settings CSV file
 *
 * @copyright   2018 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadblocksettings_handler {

    /**
     * The ID of the file uploaded through the form
     *
     * @var string
     */
    private $filename;

    /**
     * Constructor, sets the filename
     *
     * @param string $filename
     */
    public function __construct($filename) {
        $this->filename = $filename;
    }

    /**
     * Attempts to open the file
     *
     * Open an uploaded file using the File API.
     * Return the file handler.
     *
     * @throws uploadblocksettings_exception if the file can't be opened for reading
     * @return object File handler
     */
    public function open_file() {
        global $USER;
        if (is_file($this->filename)) {
            if (!$file = fopen($this->filename, 'r')) {
                throw new uploadblocksettings_exception('cannotreadfile', $this->filename, 500);
            }
        } else {
            $fs = get_file_storage();
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id,
                                         'user',
                                         'draft',
                                         $this->filename,
                                         'id DESC',
                                         false);
            if (!$files) {
                throw new uploadblocksettings_exception('cannotreadfile', $this->filename, 500);
            }
            $file = reset($files);
            if (!$file = $file->get_content_file_handle()) {
                throw new uploadblocksettings_exception('cannotreadfile', $this->filename, 500);
            }
        }
        return $file;
    }

    /**
     * Processes the file to handle the block settings
     *
     * Opens the file, loops through each row. Cleans the values in each column,
     * checks that the operation is valid and the blocks exist. If all is well,
     * adds, modifies or removes the block in column 3 to/from the course in column 2
     * context as specified.
     * Returns a report of successes and failures.
     *
     * @see open_file()
     * @return string A report of successes and failures.S
     */
    public function process() {
        global $DB;
        $report = array();

        // Set a counter so we can report line numbers for errors.
        $line = 0;

        // Open the file.
        $file = $this->open_file();

        // Loop through each row of the file.
        while ($csvrow = fgetcsv($file)) {
            $line++;

            // Check for the correct number of columns.
            if (count($csvrow) < 5) {
                $report[] = get_string('toofewcols', 'tool_uploadblocksettings', $line);
                continue;
            }
            if (count($csvrow) > 5) {
                $report[] = get_string('toomanycols', 'tool_uploadblocksettings', $line);
                continue;
            }

            // Read in clean parameters to prevent sql injection.
            $op = clean_param($csvrow[0], PARAM_TEXT);
            $courseshortname = clean_param($csvrow[1], PARAM_TEXT);
            $blockname = clean_param($csvrow[2], PARAM_TEXT);
            $region = clean_param($csvrow[3], PARAM_TEXT);
            $weight = (int) clean_param($csvrow[4], PARAM_INT);

            // Prepare reporting message strings.
            $strings = new stdClass;
            $strings->linenum = $line;
            $strings->op = $op;
            $strings->coursename = $courseshortname;
            $strings->blockname = $blockname;
            $strings->region = $region;
            $strings->weight = $weight;
            $strings->line = get_string('csvline', 'tool_uploadcourse');
            $strings->skipped = get_string('skipped');

            if ($op == 'add') {
                $strings->oplabel = get_string('add');
            } else if ($op == 'del' || $op == 'delete') {
                $strings->oplabel = get_string('delete');
                $op = 'del';
            } else if ($op == 'upd' || $op == 'update' || $op == 'mod' || $op == 'modify') {
                $strings->oplabel = get_string('update');
                $op = 'upd';
            }

            // Need to check the line is valid. If not, add a message to the report and skip the line.

            // Check we've got a valid operation.
            if (!in_array($op, array('add', 'del', 'upd'))) {
                $report[] = get_string('invalidop', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check that a valid region is specified.
            if (!in_array($region, array('side-pre', 'side-post'))) {
                $report[] = get_string('invalidregion', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check that a valid weight is specified.
            if (!is_int($weight) || $weight > 10 || $weight < -10 ) {
                $report[] = get_string('invalidweight', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check the course we're assigning the block to exists.
            if (!$course = $DB->get_record('course', array('shortname' => $courseshortname))) {
                $report[] = get_string('coursenotfound', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check that the block we're adding is installed.
            if (!($block = $DB->get_record('block', array('name' => $blockname)))) {
                $report[] = get_string('blocknotinstalled', 'tool_uploadblocksettings', $strings);
                continue;
            }

            $strings->courseid = $course->id;

            // Get the course context so that we can identify its block instances.
            $context = context_course::instance($course->id);
            $instanceparams = array(
                'parentcontextid' => $context->id,
                'blockname' => $blockname,
                'pagetypepattern' => 'course-view-*'
            );
            if ($op == 'del') {
                // Check the block is added to the course, and remove it.

                // Get the block instances (may be more than one).
                if ($instances = $DB->get_records('block_instances', $instanceparams)) {
                    foreach ($instances as $instance) {
                        blocks_delete_instance($instance);
                    }
                    $report[] = get_string('blockremoved', 'tool_uploadblocksettings', $strings);
                } else {
                    $report[] = get_string('blockdoesntexist', 'tool_uploadblocksettings', $strings);
                }
            } else if ($op == 'upd') {
                // We can modify the location or the weighting of a block.
                // Get the block instances (may be more than one).
                if ($instances = $DB->get_records('block_instances', $instanceparams)) {
                    foreach ($instances as $instance) {
                        blocks_delete_instance($instance);
                    }
                    $report[] = get_string('blockremoved', 'tool_uploadblocksettings', $strings);
                } else {
                    $report[] = get_string('blockdoesntexist', 'tool_uploadblocksettings', $strings);
                }
            } else if ($op == 'add') {
                // If we're adding, check that the block is not already added to the course.
                // Skip the line if it is.
                if ($instances = $DB->get_records('block_instances', $instanceparams)) {
                    $report[] = get_string('blockalreadyadded', 'tool_uploadblocksettings', $strings);
                } else {
                    // Create a block instance and add it to the database.
                    $blockinstance = new stdClass;
                    $blockinstance->blockname = $blockname;
                    $blockinstance->parentcontextid = $context->id;
                    $blockinstance->showinsubcontexts = false;
                    $blockinstance->pagetypepattern = 'course-view-*';
                    $blockinstance->subpagepattern = null;
                    $blockinstance->defaultregion = $region;
                    $blockinstance->defaultweight = $weight;
                    $blockinstance->configdata = '';
                    $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

                    $report[] = get_string('blockadded', 'tool_uploadblocksettings', $strings);
                }
            }
        }
        fclose($file);
        return implode("<br/>", $report);
    }
}

/**
 * An exception for reporting errors
 *
 * Extends the moodle_exception with an http property, to store an HTTP error
 * code for responding to AJAX requests.
 *
 * @copyright   2010 Tauntons College, UK
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploadblocksettings_exception extends moodle_exception {

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
