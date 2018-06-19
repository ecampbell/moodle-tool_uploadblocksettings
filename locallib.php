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
            if (count($csvrow) < 6) {
                $report[] = get_string('toofewcols', 'tool_uploadblocksettings', $line);
                continue;
            }
            if (count($csvrow) > 6) {
                $report[] = get_string('toomanycols', 'tool_uploadblocksettings', $line);
                continue;
            }

            // Read in clean parameters to prevent sql injection.
            $op = clean_param($csvrow[0], PARAM_TEXT);
            $courseshortname = clean_param($csvrow[1], PARAM_TEXT);
            $blockname = clean_param($csvrow[2], PARAM_TEXT);
            $region = clean_param($csvrow[3], PARAM_TEXT);

            // Prepare reporting message strings.
            $strings = new stdClass;
            $strings->linenum = $line;
            $strings->op = $op;
            $strings->coursename = $courseshortname;
            $strings->blockname = $blockname;
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

            // Check the course we're assigning the block to exists.
            if (!$course = $DB->get_record('course', array('shortname' => $targetshortname))) {
                $report[] = get_string('coursenotfound', 'tool_uploadblocksettings', $strings);
                continue;
            }
            // Check the block we're assigning exists.
            if (!($block = $DB->get_record('block', array('name' => $blockname)))) {
                $report[] = get_string('blocknotfound', 'tool_uploadblocksettings', $strings);
                continue;
            }

            $strings->courseid = $course->id;
            $strings->blockid = $block->id;

            if ($op == 'del') {
                // If we're deleting, check the block is already in the course, and remove it.
                // Skip the line if they're not.
                $instanceparams = array(
                    'courseid' => $course->id,
                    'block' => $block->id
                );
                if ($instance = $DB->get_record('block', $instanceparams)) {
                    $enrol->delete_instance($instance);
                    $report[] = get_string('blockdeleted', 'tool_uploadblocksettings', $strings);
                } else {
                    $report[] = get_string('blockdoesntexist', 'tool_uploadblocksettings', $strings);
                }
            } else if ($op == 'upd') {
                // If we're modifying, check the parent is already linked to the target, and change the status.
                // Skip the line if they're not.
                $instanceparams = array(
                    'courseid' => $target->id,
                    'customint1' => $parent->id,
                    'enrol' => $method
                );
                if ($instance = $DB->get_record('enrol', $instanceparams)) {
                    // Found a valid  instance, so  enable or disable it.
                    $strings->instancename = $enrol->get_instance_name($instance);
                    if ($disabledstatus == '1') {
                        $strings->status = get_string('statusdisabled', 'enrol_manual');
                    }
                    $enrol->update_status($instance, $disabledstatus);
                    $report[] = get_string('relupdated', 'tool_uploadblocksettings', $strings);
                } else {
                    $report[] = get_string('reldoesntexist', 'tool_uploadblocksettings', $strings);
                }
            } else if ($op == 'add') {
                // If we're adding, check that the parent is not already linked to the target, and add them.
                // Skip the line if they are.
                $instanceparams1 = array(
                    'courseid' => $parent->id,
                    'customint1' => $target->id,
                    'enrol' => $method
                );
                $instanceparams2 = array(
                    'courseid' => $target->id,
                    'customint1' => $parent->id,
                    'enrol' => $method
                );
                if ($method == 'meta' && ($instance = $DB->get_record('enrol', $instanceparams1))) {
                    $report[] = get_string('targetisparent', 'tool_uploadblocksettings', $strings);
                } else if ($instance = $DB->get_record('enrol', $instanceparams2)) {
                    $report[] = get_string('relalreadyexists', 'tool_uploadblocksettings', $strings);
                } else if ($instance = $enrol->add_instance($target, array('customint1' => $parent->id))) {
                    // Successfully added a valid new instance, so now instantiate it.
                    // Synchronise the enrolment.
                    if ($method == 'meta') {
                        enrol_meta_sync($instance->courseid);
                    } else if ($method == 'cohort') {
                        $trace = new null_progress_trace();
                        enrol_cohort_sync($trace, $instance->courseid);
                        $trace->finished();
                    }

                    // Is it initially disabled?
                    if ($disabledstatus == '1') {
                        $instance = $DB->get_record('enrol', $instanceparams2);
                        $enrol->update_status($instance, $disabledstatus);
                        $strings->status = get_string('statusdisabled', 'enrol_manual');
                    }

                    $strings->instancename = $enrol->get_instance_name($instance);
                    $report[] = get_string('reladded', 'tool_uploadblocksettings', $strings);
                } else {
                    // Instance not added for some reason, so report an error and go to the next line.
                    $report[] = get_string('reladderror', 'tool_uploadblocksettings', $strings);
                }
            }
        }
        fclose($file);
        return implode("<br/>", $report);
    }
}

/**
 * An exception for reporting errors when processing metalink files
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
