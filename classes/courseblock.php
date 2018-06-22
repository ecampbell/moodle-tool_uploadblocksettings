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
 * Library of functions for checking blocks in a course block settings CSV file.
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
    protected $regions = array(BLOCK_POS_LEFT => "1", BLOCK_POS_RIGHT => "1");

    /** @var string the region where new blocks are added.*/
    protected $defaultregion = BLOCK_POS_RIGHT;

    /** @var array will be $DB->get_records('blocks') */
    protected $allblocks = null;

    /**
     * @var array blocks that this user can add to this page. Will be a subset
     * of $allblocks, but with array keys block->name. Access this via the
     * {@link get_addable_blocks()} method to ensure it is lazy-loaded.
     */
    protected $addableblocks = null;

    /**
     * Will be an array region-name => array(db rows loaded in load_blocks);
     * @var array
     */
    protected $birecordsbyregion = null;

    /**
     * array region-name => array(block objects); populated as necessary by
     * the ensure_instances_exist method.
     * @var array
     */
    protected $blockinstances = array();

    /**
     * Constructor, sets the filename
     *
     * @param string $filename
     */
    public function __construct($course) {
        $this->course = $course;
        // Get the course context so that we can identify its block instances.
        $this->context = context_course::instance($course->id);
    }

    /**
     * Get an array of all region names on this course where a block may appear
     *
     * @return array the internal names of the regions on this course where block may appear.
     */
    public function get_regions() {
        // Taking a dumb approach here, and just hardcoding 'side-pre' and 'side-post' regions.
        return array_keys($this->regions);
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

        // Installed blocks - undeletable - once-only blocks already added to the course.
        // Should also remove blocks required by theme, but that's too hard for the moment.
        $unaddableblocks = self::get_undeletable_block_types();
        foreach ($allblocks as $block) {
            if ($block->visible && !in_array($block->name, $unaddableblocks) &&
                ($bi->instance_allow_multiple() || !$this->is_block_present($block->name))) {
                $block->title = $bi->get_title();
                $this->addableblocks[$block->name] = $block;
            }
        }

        core_collator::asort_objects_by_property($this->addableblocks, 'title');
        return $this->addableblocks;
    }

    /**
     * Given a block name, find out of any of them are currently present in the course

     * @param string $blockname - the basic name of a block (eg "navigation")
     * @return boolean - is there one of these blocks in the current course?
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
                    return true;
                }
            }
        }
        return false;
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
     * Find out if a region exists on a course - only side-post and side-pre accepted.
     *
     * @param string $region a region name
     * @return boolean true if this region exists on this course.
     */
    public function is_known_region($region) {
        if (empty($region)) {
            return false;
        }
        return array_key_exists($region, $this->regions);
    }

    /**
     * Get an array of all blocks within a given region
     *
     * @param string $region a block region that exists on this page.
     * @return array of block instances.
     */
    public function get_blocks_for_region($region) {
        $this->ensure_instances_exist($region);
        return $this->blockinstances[$region];
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
     * This method actually loads the blocks for our course from the database.
     *
     * @param boolean|null $includeinvisible
     *      null (default) - load hidden blocks if $this->page->user_is_editing();
     *      true - load hidden blocks.
     *      false - don't load hidden blocks.
     */
    public function load_blocks($includeinvisible = null) {
        global $DB, $CFG;

        if (!is_null($this->birecordsbyregion)) {
            // Already done.
            return;
        }

        if ($includeinvisible) {
            $visiblecheck = '';
        } else {
            $visiblecheck = 'AND (bp.visible = 1 OR bp.visible IS NULL)';
        }

        $contexttest = 'bi.parentcontextid IN (:contextid2, :contextid3)';

        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = bi.id AND ctx.contextlevel = :contextlevel)";

        $systemcontext = context_system::instance();
        $params = array(
            'contextlevel' => CONTEXT_BLOCK,
            'subpage1' => '',
            'subpage2' => '',
            'contextid1' => $this->context->id,
            'contextid2' => $this->context->id,
            'contextid3' => $systemcontext->id,
            'pagetype' => 'course-view-*',
        );
        $sql = "SELECT
                    bi.id,
                    bp.id AS blockpositionid,
                    bi.blockname,
                    bi.parentcontextid,
                    bi.showinsubcontexts,
                    bi.pagetypepattern,
                    bi.requiredbytheme,
                    bi.subpagepattern,
                    bi.defaultregion,
                    bi.defaultweight,
                    COALESCE(bp.visible, 1) AS visible,
                    COALESCE(bp.region, bi.defaultregion) AS region,
                    COALESCE(bp.weight, bi.defaultweight) AS weight,
                    bi.configdata
                    $ccselect

                FROM {block_instances} bi
                JOIN {block} b ON bi.blockname = b.name
                LEFT JOIN {block_positions} bp ON bp.blockinstanceid = bi.id
                                                  AND bp.contextid = :contextid1
                                                  AND bp.pagetype = :pagetype
                                                  AND bp.subpage = :subpage1
                $ccjoin

                WHERE
                $contexttest
                AND bi.pagetypepattern $pagetypepatterntest
                AND (bi.subpagepattern IS NULL OR bi.subpagepattern = :subpage2)
                $visiblecheck
                AND b.visible = 1

                ORDER BY
                    COALESCE(bp.region, bi.defaultregion),
                    COALESCE(bp.weight, bi.defaultweight),
                    bi.id";

        $allparams = $params;
        $blockinstances = $DB->get_recordset_sql($sql, $allparams);

        $this->birecordsbyregion = $this->prepare_per_region_arrays();
        $unknown = array();
        foreach ($blockinstances as $bi) {
            context_helper::preload_from_record($bi);
            if ($this->is_known_region($bi->region)) {
                $this->birecordsbyregion[$bi->region][] = $bi;
            } else {
                $unknown[] = $bi;
            }
        }

        // Pages don't necessarily have a defaultregion. The  one time this can
        // happen is when there are no theme block regions, but the script itself
        // has a block region in the main content area.
        if (!empty($this->defaultregion)) {
            $this->birecordsbyregion[$this->defaultregion] =
                    array_merge($this->birecordsbyregion[$this->defaultregion], $unknown);
        }
    }
    /**
     * Add a block to a course page.
     *
     * @param string $blockname The type of block to add.
     * @param string $region the block region on this page to add the block to.
     * @param integer $weight determines the order where this block appears in the region.
     */
    public function add_block($blockname, $region, $weight) {
        global $DB;

        if (empty($pagetypepattern)) {
            $pagetypepattern = 'course-view-*';
        }

        $blockinstance = new stdClass;
        $blockinstance->blockname = $blockname;
        $blockinstance->parentcontextid = $this->context->id;
        $blockinstance->showinsubcontexts = false;
        $blockinstance->pagetypepattern = null;
        $blockinstance->subpagepattern = null;
        $blockinstance->defaultregion = $region;
        $blockinstance->defaultweight = $weight;
        $blockinstance->configdata = '';
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);
    }

    /**
     * Move a block to a new position on this course.
     *
     * If this block cannot appear on any other pages, then we change defaultposition/weight
     * in the block_instances table. Otherwise we just set the position on this page.
     *
     * @param $blockinstanceid the block instance id.
     * @param $newregion the new region name.
     * @param $newweight the new weight.
     */
    public function reposition_block($blockinstanceid, $newregion, $newweight) {
        global $DB;

        $inst = $this->find_instance($blockinstanceid);

        $bi = $inst->instance;
        if ($bi->weight == $bi->defaultweight && $bi->region == $bi->defaultregion &&
                !$bi->showinsubcontexts && strpos($bi->pagetypepattern, '*') === false) {

            // Set default position
            $newbi = new stdClass;
            $newbi->id = $bi->id;
            $newbi->defaultregion = $newregion;
            $newbi->defaultweight = $newweight;
            $DB->update_record('block_instances', $newbi);

            if ($bi->blockpositionid) {
                $bp = new stdClass;
                $bp->id = $bi->blockpositionid;
                $bp->region = $newregion;
                $bp->weight = $newweight;
                $DB->update_record('block_positions', $bp);
            }
        } else {
            // Just set position on this page.
            $bp = new stdClass;
            $bp->region = $newregion;
            $bp->weight = $newweight;

            if ($bi->blockpositionid) {
                $bp->id = $bi->blockpositionid;
                $DB->update_record('block_positions', $bp);

            } else {
                $bp->blockinstanceid = $bi->id;
                $bp->contextid = $context->id;
                $bp->pagetype = 'course-view-topoics';
                $bp->subpage = '';
                $bp->visible = $bi->visible;
                $DB->insert_record('block_positions', $bp);
            }
        }
    }

    /**
     * Ensure block instances exist for a given region
     *
     * @param string $region Check for bi's with the instance with this name
     */
    protected function ensure_instances_exist($region) {
        if (!array_key_exists($region, $this->blockinstances)) {
            $this->blockinstances[$region] = $this->create_block_instances($this->birecordsbyregion[$region]);
        }
    }

    /**
     * Find a given block by its instance id
     *
     * @param integer $instanceid
     * @return block_base
     */
    public function find_instance($instanceid) {
        foreach ($this->regions as $region => $notused) {
            $this->ensure_instances_exist($region);
            foreach($this->blockinstances[$region] as $instance) {
                if ($instance->instance->id == $instanceid) {
                    return $instance;
                }
            }
        }
    }

    /**
     * Creates a new instance of the specified block class.
     *
     * @param string $blockname the name of the block.
     * @param $instance block_instances DB table row (optional).
     * @param moodle_page $page the page this block is appearing on.
     * @return block_base the requested block instance.
     */
    function block_instance($blockname, $instance = NULL) {
        if(!block_load_class($blockname)) {
            return false;
        }
        $classname = 'block_' . $blockname;
        $retval = new $classname;
        if($instance !== NULL) {
            return $retval;
        }
        return $retval;
    }
    
    /**
     * Load the block class for a particular type of block.
     *
     * @param string $blockname the name of the block.
     * @return boolean success or failure.
     */
    function block_load_class($blockname) {
        global $CFG;
    
        if(empty($blockname)) {
            return false;
        }
    
        $classname = 'block_'.$blockname;
    
        if(class_exists($classname)) {
            return true;
        }
    
        $blockpath = $CFG->dirroot.'/blocks/'.$blockname.'/block_'.$blockname.'.php';
    
        if (file_exists($blockpath)) {
            require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
            include_once($blockpath);
        }else{
            //debugging("$blockname code does not exist in $blockpath", DEBUG_DEVELOPER);
            return false;
        }
    
        return class_exists($classname);
    }

    /**
     * Delete a block, and associated data.
     *
     * @param object $instance a row from the block_instances table
     * @param bool $nolongerused legacy parameter. Not used, but kept for backwards compatibility.
     * @param bool $skipblockstables for internal use only. Makes @see blocks_delete_all_for_context() more efficient.
     */
    public function blocks_delete_instance($instance) {
        global $DB;

        $DB->delete_records('block_positions', array('blockinstanceid' => $instance->id));
        $DB->delete_records('block_instances', array('id' => $instance->id));
        $DB->delete_records_list('user_preferences', 'name', array(
                    'block' . $instance->id . 'hidden', 
                    'docked_block_instance_' . $instance->id
                    ));
    }


    /**
     * Returns an array of region names as keys and nested arrays for values
     *
     * @return array an array where the array keys are the region names, and the array
     * values are empty arrays.
     */
    protected function prepare_per_region_arrays() {
        $result = array();
        foreach ($this->regions as $region => $notused) {
            $result[$region] = array();
        }
        return $result;
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
            if ($blockobject = block_instance($record->blockname, $record)) {
                $results[] = $blockobject;
            }
        }
        return $results;
    }
}