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
 * Strings for component 'tool_uploadblocksettings', language 'en'
 *
 * @package    tool_uploadblocksettings
 * @copyright  2018 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['blockadded']            = '{$a->line} {$a->linenum} [{$a->oplabel}]: Added "{$a->blockname}" block to course "{$a->coursename}" ({$a->courseid}).';
$string['blockadderror']         = '{$a->line} {$a->linenum} [{$a->oplabel}]: Error adding "{$a->blockname}" block to course "{$a->coursename}" ({$a->courseid}). {$a->skipped}.';
$string['blockalreadyadded']    = '{$a->line} {$a->linenum} [{$a->oplabel}]: "{$a->blockname}" block already added to course "{$a->coursename}" ({$a->courseid}). {$a->skipped}.';
$string['blockremoved']          = '{$a->line} {$a->linenum} [{$a->oplabel}]: Removed "{$a->blockname}" block from course "{$a->coursename}" ({$a->courseid}).';
$string['blockdoesntexist']      = '{$a->line} {$a->linenum} [{$a->oplabel}]: "{$a->blockname}" ({$a->courseid}) not added to course "{$a->coursename}" ({$a->courseid}), so can\'t be removed. {$a->skipped}.';
$string['blocknotinstalled']     = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blockname}" not installed. {$a->skipped}.';
$string['blocknotvalid']         = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blockname}" is not valid. {$a->skipped}.';
$string['coursenotfound']        = '{$a->line} {$a->linenum} [{$a->oplabel}]: Course "{$a->courseshortname}" not found. {$a->skipped}.';
$string['csvfile'] = '';
$string['csvfile_help']          = 'The format of the CSV file must be as follows:

* Each line of the file contains one record.
* Each record is a series of data in a fixed order separated by commas.
* The required fields are operation, course shortname, block, region, weight.
* The allowed operations are add, del, upd.
* The allowed regions are side-pre and side-post.
* The allowed weights are -10 to 10 (0 is neutral)';
$string['heading']               = 'Upload course block settings from a CSV file';
$string['invalidop']             = '{$a->line} {$a->linenum} [{$a->oplabel}]: Invalid operation "{$a->op}".';
$string['pluginname']            = 'Upload block settings';
$string['pluginname_help']       = 'Upload block settings from a CSV file to set block settings for a range of courses in a single operation.';
$string['privacy:metadata']      = 'The Upload block settings administration tool does not store personal data.';
$string['regionnotvalid']         = '{$a->line} {$a->linenum} [{$a->oplabel}]: Region "{$a->region}" is not valid. {$a->skipped}.';
$string['toofewcols']            = '{$a->line} {$a->linenum} [{$a->oplabel}]: Too few columns, expecting 5. {$a->skipped}.';
$string['toomanycols']           = '{$a->line} {$a->linenum} [{$a->oplabel}]: Too many columns, expecting 5. {$a->skipped}.';
$string['weightnotvalid']         = '{$a->line} {$a->linenum} [{$a->oplabel}]: Weight "{$a->weight}" is not valid. {$a->skipped}.';
