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

require_once($CFG->libdir.'/blocklib.php');

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
     * adds or removes the block in column 3 to/from the course in column 2
     * context as specified. Also resets the course blocks to the default, in which
     * case, no block needs to be specified.
     * Returns a report of successes and failures.
     *
     * @see open_file()
     * @return string A report of successes and failures.S
     */
    public function process() {
        global $DB, $CFG;
        $report = array();

        // Set a counter so we can report line numbers for errors.
        $line = 0;

        // Remember the last course, to avoid reloading all blocks on each line.
        $previouscourse = '';
        $courseblock = null;

        // Open the file.
        $file = $this->open_file();

        // Prepare reporting message strings.
        $strings = new stdClass;
        $strings->linenum = $line;
        $strings->line = get_string('csvline', 'tool_uploadcourse');
        $strings->skipped = get_string('skipped');

            // Loop through each row of the file.
        while ($csvrow = fgetcsv($file)) {
            $line++;
            $strings->linenum = $line;

            // Skip any comment lines starting with # or ;.
            if ($csvrow[0][0] == '#' or $csvrow[0][0] == ';') {
                $report[] = get_string('csvcomment', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Check for the correct number of columns.
            if (count($csvrow) < 5) {
                $report[] = get_string('toofewcols', 'tool_uploadblocksettings', $strings);
                continue;
            }
            if (count($csvrow) > 5) {
                $report[] = get_string('toomanycols', 'tool_uploadblocksettings', $strings);
                continue;
            }

            // Read in clean parameters to prevent sql injection.
            $op = clean_param($csvrow[0], PARAM_TEXT);
            $courseshortname = clean_param($csvrow[1], PARAM_TEXT);
            $blockname = clean_param($csvrow[2], PARAM_TEXT);
            $region = clean_param($csvrow[3], PARAM_TEXT);
            $weight = clean_param($csvrow[4], PARAM_INT);

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

            // Check that the row specifies an operation and a course.
            if ($op == '' or $courseshortname == '') {
                $report[] = get_string('fieldscannotbeblank', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check that the operation is valid.
            if (!in_array($op, array('add', 'del', 'res'))) {
                $report[] = get_string('operationunknown', 'tool_uploadblocksettings', $strings);
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

            // Check that the row specifies a block, region and weight.
            if ($blockname == '' or $region == '' or $weight == '') {
                $report[] = get_string('fieldscannotbeblank', 'tool_uploadblocksettings', $strings);
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
                $courseblock->blocks_delete_instance($bi);
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
