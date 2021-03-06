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
 * Base class for processing module html.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_ally\componentsupport;

use tool_ally\local;
use tool_ally\role_assignments;
use \context;

defined ('MOODLE_INTERNAL') || die();

/**
 * Base class for processing module html.
 * @author    Guy Thomas <citricity@gmail.com>
 * @copyright Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class component_base {

    const TYPE_CORE = 'core';

    const TYPE_MOD = 'mod';

    const TYPE_BLOCK = 'block';

    protected $tablefields = [];

    /**
     * Return component type for this component - a class constant beginning with TYPE_
     *
     * @return int
     */
    abstract public static function component_type();

    /**
     * @return bool
     */
    public function module_installed() {
        return \core_component::get_component_directory($this->get_component_name()) !== null;
    }

    /**
     * Get fields for a specific table.
     *
     * @param string $table
     * @return array|mixed
     */
    public function get_table_fields($table) {
        if (isset($this->tablefields[$table])) {
            return $this->tablefields[$table];
        }
        return [];
    }

    /**
     * @param string $table
     * @param string $field
     * @throws \coding_exception
     */
    protected function validate_component_table_field($table, $field) {
        if (empty($this->tablefields[$table]) || !is_array($this->tablefields)) {
            throw new \coding_exception('Table '.$table.' is not allowed for the requested component content');
        }
        if (!in_array($field, $this->tablefields[$table])) {
            throw new \coding_exception('Field '.$field.' is not allowed for the table '.$table);
        }
    }

    /**
     * Extract component name from class.
     * @return mixed
     */
    protected function get_component_name() {
        $reflect = new \ReflectionClass($this);
        $class = $reflect->getShortName();
        $matches = [];
        if (!preg_match('/(.*)_component/', $class, $matches) || count($matches) < 2) {
            throw new \coding_exception('Invalid component class '.$class);
        }

        return $matches[1];
    }

    /**
     * Get ids of approved content authors - teachers, managers, admin, etc.
     * @param context $context
     * @return array
     */
    public function get_approved_author_ids_for_context(context $context) {
        $admins = local::get_adminids();
        $ra = new role_assignments(local::get_roleids());
        $userids = $ra->user_ids_for_context($context);
        $userids = array_filter($userids, function($item) {
            return !!$item;
        });
        $userids = array_keys($userids);
        $result = array_unique(array_merge($admins, $userids));
        return $result;
    }

    /**
     * Is the user an approved content author? teachers, managers, admin, etc.
     * @param int $userid
     * @param context $context
     * @return bool
     */
    public function user_is_approved_author_type($userid, context $context) {
        return in_array($userid, $this->get_approved_author_ids_for_context($context));
    }

    /**
     * Get a file area for a specific table / field.
     *
     * Override this in your component if you need something more complicated.
     *
     * @param $table
     * @param $field
     * @return mixed
     */
    public function get_file_area($table, $field) {
        // The default is simply to return the field, if it's part of the tablefields array.
        if (isset($this->tablefields[$table]) && in_array($field, $this->tablefields[$table])) {
            return $field;
        }
    }

    /**
     * Get a file item id for a specific table / field / id.
     *
     * Override this in your component if you need something more complicated.
     *
     * @param string $table
     * @param string $field
     * @param int $id
     * @return int
     */
    public function get_file_item($table, $field, $id) {
        return 0;
    }

    /**
     * Get a file item path for a specific table / field / id.
     *
     * Override this in your component if you need something more complicated.
     *
     * @param string $table
     * @param string $field
     * @param int $id
     * @return int
     */
    public function get_file_path($table, $field, $id) {
        return '/';
    }

    /**
     * Attempt to resolve a module instance id from a specific table + id.
     * You may need to override this method in a component for tables that do not easily link back to the module's
     * main table (e.g. table 2 levels down from main module table).
     *
     * @param $table
     * @param $id
     * @return mixed
     * @throws \dml_exception
     */
    public function resolve_module_instance_id($table, $id) {
        global $DB;

        $component = $this->get_component_name();

        if ($this->component_type() !== self::TYPE_MOD) {
            $msg = <<<MSG
Attempt to get a module instance for a component that is not a module ($component)
MSG;

            throw new \coding_exception($msg);
        }

        if ($table === $component) {
            return $id;
        } else {
            $record = $DB->get_record($table, ['id' => $id]);
            if (!empty($record->{$component.'id'})) {
                $instanceid = $record->{$component.'id'};
            } else if (!empty($record->$component)) {
                $instanceid = $record->$component;
            } else {
                $method = __METHOD__;
                $msg = <<<MSG
Unable to resolve component from subtable "$table" with id $id. A developer needs to override the method "$method" in the
component $component so that it can cope with the table "$table".
MSG;
                throw new \coding_exception($msg);
            }
            $componentrecord = $DB->get_record($component, ['id' => $instanceid]);
            return $componentrecord->id;
        }
    }
}
