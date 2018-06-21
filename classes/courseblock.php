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
 * Utilities for checking course blocks
 *
 * These utilities are copied and adjusted from the block library, which deals with the current page only.
 *
 * @copyright   2018 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_uploadblocksettings_courseblock {

    /**
     * The ID of the course being processed.
     *
     * @var object
     */
    private $course;

    /**
     * The context of the course being processed.
     *
     * @var object
     */
    private $context;

    /** @var array region name => 1.*/
    protected $regions = array(BLOCK_POS_LEFT, BLOCK_POS_RIGHT);

    /** @var array will be $DB->get_records('blocks') */
    protected $allblocks = null;

    /**
     * @var array blocks that this user can add to this page. Will be a subset
     * of $allblocks, but with array keys block->name. Access this via the
     * {@link get_addable_blocks()} method to ensure it is lazy-loaded.
     */
    protected $addableblocks = null;

 /**
     * Constructor, sets the filename
     *
     * @param string $filename
     */
    public function __construct($course) {
        $this->course = $course;
        // Get the course context so that we can identify its block instances.
        $context = context_course::instance($course->id);
    }
    
    /**
     * Add a block to a course page.
     *
     * @param string $blockname The type of block to add.
     * @param string $region the block region on this page to add the block to.
     * @param integer $weight determines the order where this block appears in the region.
     * @param boolean $showinsubcontexts whether this block appears in subcontexts, or just the current context.
     * @param string|null $pagetypepattern which page types this block should appear on. Defaults to just the current page type.
     * @param string|null $subpagepattern which subpage this block should appear on. NULL = any (the default), otherwise only the specified subpage.
     */
    public function add_block($blockname, $region, $weight, $showinsubcontexts, $pagetypepattern = NULL, $subpagepattern = NULL) {
        global $DB;

        if (empty($pagetypepattern)) {
            $pagetypepattern = 'course-view-*';
        }

        $blockinstance = new stdClass;
        $blockinstance->blockname = $blockname;
        $blockinstance->parentcontextid = $context->id;
        $blockinstance->showinsubcontexts = $showinsubcontexts;
        $blockinstance->pagetypepattern = $pagetypepattern;
        $blockinstance->subpagepattern = $subpagepattern;
        $blockinstance->defaultregion = $region;
        $blockinstance->defaultweight = $weight;
        $blockinstance->configdata = '';
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);
    }

    /**
     * Delete a block, and associated data.
     *
     * This function is a ripoff of blocklib/blocks_delete_instance.
     *
     * @param object $instance a row from the block_instances table
     * @param bool $nolongerused legacy parameter. Not used, but kept for backwards compatibility.
     * @param bool $skipblockstables for internal use only. Makes @see blocks_delete_all_for_context() more efficient.
     */
    public function delete_instance($instance, $nolongerused = false, $skipblockstables = false) {
        global $DB;

        if (!$skipblockstables) {
            $DB->delete_records('block_positions', array('blockinstanceid' => $instance->id));
            $DB->delete_records('block_instances', array('id' => $instance->id));
            $DB->delete_records_list('user_preferences', 'name', array('block'.$instance->id.'hidden','docked_block_instance_'.$instance->id));
        }
    }

    /**
     * Find out if a region exists on a page
     *
     * @param string $region a region name
     * @return boolean true if this region exists on this page.
     */
    public function is_known_region($region) {
        if (empty($region)) {
            return false;
        }
        return array_key_exists($region, $this->regions);
    }

    /**
     * Find out if a block type is known by the system
     *
     * @param string $blockname the name of the type of block.
     * @param boolean $includeinvisible if false (default) only check 'visible' blocks, that is, blocks enabled by the admin.
     * @return boolean true if this block in installed.
     */
    public function is_known_block_type($blockname, $includeinvisible = false) {
        $blocks = $this->get_installed_blocks();
        foreach ($blocks as $block) {
            if ($block->name == $blockname && ($includeinvisible || $block->visible)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get an array of all of the installed blocks.
     *
     * @return array contents of the block table.
     */
    private function get_installed_blocks() {
        global $DB;
        if (is_null($this->allblocks)) {
            $this->allblocks = $DB->get_records('block');
        }
        return $this->allblocks;
    }

    /**
     * Get the list of "protected" blocks via admin block manager ui.
     *
     * @return array names of block types that cannot be added or deleted. E.g. array('navigation','settings').
     */
    public static function get_undeletable_block_types() {
        global $CFG;
        $undeletableblocks = false;
        if (isset($CFG->undeletableblocktypes)) {
            $undeletableblocks = $CFG->undeletableblocktypes;
        }

        if (empty($undeletableblocks)) {
            return array();
        } else if (is_string($undeletableblocks)) {
            return explode(',', $undeletableblocks);
        } else {
            return $undeletableblocks;
        }
    }

    /**
     * The list of block types that may be added to this page.
     *
     * @return array block name => record from block table.
     */
    public function get_addable_blocks() {
        if (!is_null($this->addableblocks)) {
            return $this->addableblocks;
        }

        // Lazy load.
        $this->addableblocks = array();

        $allblocks = $this->get_installed_blocks();
        if (empty($allblocks)) {
            return $this->addableblocks;
        }

        // See blocklib/get_addable_blocks() for original code.
        $unaddableblocks = self::get_undeletable_block_types();
        foreach($allblocks as $block) {
            if ($block->visible && !in_array($block->name, $unaddableblocks) &&
                    ($bi->instance_allow_multiple() || !$this->is_block_present($block->name)) &&
                    blocks_name_allowed_in_format($block->name, $pageformat) &&
                    $bi->user_can_addto($this->page)) {
                $block->title = $bi->get_title();
                $this->addableblocks[$block->name] = $block;
            }
        }

        core_collator::asort_objects_by_property($this->addableblocks, 'title');
        return $this->addableblocks;
    }

    /**
     * Given a block name, find out of any of them are currently present in the page

     * @param string $blockname - the basic name of a block (eg "navigation")
     * @return boolean - is there one of these blocks in the current page?
     */
    public function is_block_present($blockname) {
        if (empty($this->blockinstances)) {
            return false;
        }

        // Get the set of blocks added to this course.
        // Ignoring blocks required by the course theme for the moment.
        foreach ($this->blockinstances as $region) {
            foreach ($region as $instance) {
                if (empty($instance->instance->blockname)) {
                    continue;
                }
                if ($instance->instance->blockname == $blockname) {
                    if ($instance->instance->requiredbytheme) {
                        if (!in_array($blockname, $requiredbythemeblocks)) {
                            continue;
                        }
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Ensure block instances exist for a given region
     *
     * @param string $region Check for bi's with the instance with this name
     */
    protected function ensure_instances_exist($region) {
        if (!array_key_exists($region, $this->blockinstances)) {
            $this->blockinstances[$region] =
                    $this->create_block_instances($this->birecordsbyregion[$region]);
        }
    }

    /**
     * Create a set of new block instance from a record array
     *
     * @param array $birecords An array of block instance records
     * @return array An array of instantiated block_instance objects
     */
    protected function create_block_instances($birecords) {
        $results = array();
        foreach ($birecords as $record) {
            if ($blockobject = block_instance($record->blockname, $record, $this->page)) {
                $results[] = $blockobject;
            }
        }
        return $results;
    }
}
