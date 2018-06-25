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

$string['blockadded']            = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blocktitle}" ({$a->blockname}) added to course "{$a->coursename}" ({$a->courseid}).';
$string['blockadderror']         = '{$a->line} {$a->linenum} [{$a->oplabel}]: Error: block "{$a->blocktitle}" ({$a->blockname}) not added to course "{$a->coursename}" ({$a->courseid}). {$a->skipped}.';
$string['blockalreadyadded']     = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blocktitle}" ({$a->blockname}) already added to course "{$a->coursename}" ({$a->courseid}). {$a->skipped}.';
$string['blockdeleted']          = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blocktitle}" ({$a->blockname}) deleted from course "{$a->coursename}" ({$a->courseid}).';
$string['blockinstancenotfound']          = '{$a->line} {$a->linenum} [{$a->oplabel}]: Warning: Block "{$a->blocktitle}" ({$a->blockname}) not found in course "{$a->coursename}" ({$a->courseid}).';
$string['blockdoesntexist']      = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blocktitle}" ({$a->blockname}) not added to course "{$a->coursename}" ({$a->courseid}). {$a->skipped}.';
$string['blockmoved']            = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block "{$a->blocktitle}" ({$a->blockname}) in course "{$a->coursename}" ({$a->courseid}) moved.';
$string['blocknotinstalled']     = '{$a->line} {$a->linenum} [{$a->oplabel}]: Block ({$a->blockname}) not installed. {$a->skipped}.';
$string['coursenotfound']        = '{$a->line} {$a->linenum} [{$a->oplabel}]: Course "{$a->courseshortname}" not found. {$a->skipped}.';
$string['csvfile'] = '';
$string['csvfile_help']          = 'The format of the CSV file must be as follows:

* Each line of the file contains one record.
* Each record is a series of data in a fixed order separated by commas.
* The required fields are operation, course shortname, block, region, weight.
* The allowed operations are add, del, upd.
* The allowed regions are side-pre and side-post.
* The allowed weights are -10 to 10 (0 is neutral)';
$string['fieldscannotbeblank']   = '{$a->line} {$a->linenum} [{$a->oplabel}]: Fields cannot be blank: \
block ({$a->blockname}), course ($a->coursename), region ($a->region), weight ($a->weight). {$a->skipped}.';
$string['heading']               = 'Upload course block settings from a CSV file';
$string['operationnotvalid']     = '{$a->line} {$a->linenum} [{$a->oplabel}]: Operation not valid for block "{$a->blocktitle}" ({$a->blockname}). {$a->skipped}.';
$string['operationunknown']      = '{$a->line} {$a->linenum} [{$a->oplabel}]: Unknown operation "{$a->op}".';
$string['pluginname']            = 'Upload block settings';
$string['pluginname_help']       = 'Upload block settings from a CSV file to set block settings for a range of courses in a single operation.';
$string['privacy:metadata']      = 'The Upload block settings administration tool does not store personal data.';
$string['regionnotvalid']        = '{$a->line} {$a->linenum} [{$a->oplabel}]: Region "{$a->region}" is unknown. {$a->skipped}.';
$string['toofewcols']            = '{$a->line} {$a->linenum} [{$a->oplabel}]: Too few columns, expecting 5. {$a->skipped}.';
$string['toomanycols']           = '{$a->line} {$a->linenum} [{$a->oplabel}]: Too many columns, expecting 5. {$a->skipped}.';
$string['weightnotvalid']        = '{$a->line} {$a->linenum} [{$a->oplabel}]: Weight "{$a->weight}" is not valid. {$a->skipped}.';
